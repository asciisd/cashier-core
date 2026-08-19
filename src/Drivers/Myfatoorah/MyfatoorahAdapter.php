<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentStatus;

class MyfatoorahAdapter implements PaymentAdapterInterface
{
    /**
     * Transform a V3 create-payment `Data` object into a PaymentResult.
     *
     * The provider merges `amount` and `currency` into the array first, since
     * MyFatoorah does not echo the order figures on this response.
     *
     * `PaymentCompleted` is true only for non-3DS payments, where the outcome
     * is already decided and sits in `TransactionDetails`. Every redirect flow
     * comes back false with a `PaymentURL` to send the customer to.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        $completed = ($response['PaymentCompleted'] ?? false) === true;
        $transaction = $completed ? (array) data_get($response, 'TransactionDetails.Transaction', []) : [];

        $status = $completed
            ? $this->mapStatus($transaction['Status'] ?? null)
            : PaymentStatus::Pending;

        return new PaymentResult(
            success: $status !== PaymentStatus::Failed,
            // InvoiceId, never PaymentId: PaymentId is null on every
            // redirect-flow create response, so it cannot be the key
            // `provider_transaction_id` is set from or webhooks correlate on.
            transactionId: (string) $response['InvoiceId'],
            status: $status,
            amount: (int) round((float) ($response['amount'] ?? 0)),
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $this->errorMessage($transaction),
            metadata: array_filter([
                'redirect_url' => $response['PaymentURL'] ?? null,
                'myfatoorah_invoice_id' => $response['InvoiceId'] ?? null,
                'myfatoorah_payment_id' => $response['PaymentId'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
            paymentMethodSnapshot: $this->snapshot($transaction),
        );
    }

    /**
     * Transform a V3 invoice `Data` object (retrieve/sync) into a
     * PaymentResult.
     *
     * An invoice holds an ARRAY of transactions, one per attempt, and
     * `Invoice.Status` does not track them — see pitfalls.md entry 7. So the
     * outcome is decided by scanning, not by reading one field.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $transaction = $this->decisiveTransaction((array) ($payload['Transactions'] ?? []));

        $status = $transaction === null
            ? $this->mapStatus(data_get($payload, 'Invoice.Status'))
            : $this->mapStatus($transaction['Status'] ?? null);

        $amount = data_get($payload, 'Amount.ValueInDisplayCurrency');

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: (int) round((float) ($amount ?? 0)),
            currency: (string) (data_get($payload, 'Amount.DisplayCurrency')
                ?? config('cashier-core.currency.default', 'USD')),
            message: $this->errorMessage($transaction ?? []),
            metadata: $this->metadata(
                (array) ($payload['Invoice'] ?? []),
                $transaction ?? [],
                (array) ($payload['Amount'] ?? []),
            ),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($transaction ?? []),
        );
    }

    /**
     * Transform a V2 `PAYMENT_STATUS_CHANGED` webhook body into a
     * TransactionWebhookUpdate. Takes the WHOLE body — `Event` and `Data`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $data = (array) ($payload['Data'] ?? []);
        $invoice = (array) ($data['Invoice'] ?? []);
        $transaction = (array) ($data['Transaction'] ?? []);
        $amount = (array) ($data['Amount'] ?? []);

        $status = $this->mapStatus($transaction['Status'] ?? null);

        $metadata = $this->metadata($invoice, $transaction, $amount);
        $metadata['myfatoorah_event_reference'] = data_get($payload, 'Event.Reference');

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($transaction),
            metadata: array_filter($metadata, fn ($value) => $value !== null),
            errorCode: $this->errorCode($transaction),
            // Carried on Canceled as well as Failed: WebhookProcessor writes
            // error_message on both, and restricting this to Failed leaves the
            // customer and support staring at a bare "Canceled" with the
            // reason unread in metadata.
            errorMessage: in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)
                ? $this->errorMessage($transaction)
                : null,
            // The DISPLAY-currency figure. This is what we asked MyFatoorah to
            // collect and therefore the same basis as
            // `transactions.requested_amount`, which WebhookProcessor compares
            // it against. `ValueInBaseCurrency` is the amount converted to the
            // account's base currency; reporting it would put every deposit
            // outside the tolerance band and hold it for review — the same
            // class of bug the APS adapter documents for `amount_in`.
            //
            // Left null when absent: the guard skips a null amount, and
            // falling back to the base-currency value would reinstate the bug.
            amount: isset($amount['ValueInDisplayCurrency'])
                ? (float) $amount['ValueInDisplayCurrency']
                : null,
            currency: isset($amount['DisplayCurrency']) ? (string) $amount['DisplayCurrency'] : null,
        );
    }

    /**
     * Map a MyFatoorah V3 status to a cashier-core PaymentStatus.
     *
     * V3 uses two vocabularies and they overlap without colliding:
     *
     * - Transaction: INPROGRESS, SUCCESS, FAILED, CANCELED, AUTHORIZE
     * - Invoice:     PENDING, PAID, CANCELED
     *
     * DELIBERATELY CASE SENSITIVE, and deliberately not merged with V2. V2
     * spells the same values in mixed case and spells success `Succss` with
     * one `e` — MyFatoorah's own library compares against that string
     * literally. A case-insensitive table hides that divergence and starts
     * silently dropping payments the day either side is corrected. When V2
     * support is added, populate the table below rather than loosening this
     * one. See pitfalls.md entries 1-2.
     *
     *   V2, for the future, NOT handled here:
     *   InProgress | Succss | Failed | Canceled | Authorize
     *   Pending | Paid | Canceled
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match ((string) $providerStatus) {
            'SUCCESS', 'PAID' => PaymentStatus::Succeeded,
            'FAILED' => PaymentStatus::Failed,
            // Not a refunded state: this package has no Refunded payment
            // status, and refund and void outcomes map to Canceled throughout.
            'CANCELED' => PaymentStatus::Canceled,
            'INPROGRESS' => PaymentStatus::Processing,
            'AUTHORIZE' => PaymentStatus::RequiresCapture,
            'PENDING' => PaymentStatus::Pending,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'myfatoorah';
    }

    /**
     * The transaction that decides the invoice's outcome.
     *
     * Any SUCCESS wins outright, whatever the other entries say and whatever
     * order they arrive in. Otherwise the latest attempt by TransactionDate
     * carries the reason the customer needs to see. Null when the invoice has
     * no attempts at all.
     *
     * @param  list<array<string, mixed>>  $transactions
     * @return array<string, mixed>|null
     */
    private function decisiveTransaction(array $transactions): ?array
    {
        if ($transactions === []) {
            return null;
        }

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && ($transaction['Status'] ?? null) === 'SUCCESS') {
                return $transaction;
            }
        }

        $latest = null;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            if ($latest === null
                || (string) ($transaction['TransactionDate'] ?? '') > (string) ($latest['TransactionDate'] ?? '')) {
                $latest = $transaction;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>  $amount
     * @return array<string, mixed>
     */
    private function metadata(array $invoice, array $transaction, array $amount): array
    {
        return array_filter([
            'myfatoorah_invoice_id' => $invoice['Id'] ?? null,
            'myfatoorah_invoice_status' => $invoice['Status'] ?? null,
            'myfatoorah_external_identifier' => $invoice['ExternalIdentifier'] ?? null,
            'myfatoorah_transaction_id' => $transaction['Id'] ?? null,
            'myfatoorah_transaction_status' => $transaction['Status'] ?? null,
            'myfatoorah_payment_id' => $transaction['PaymentId'] ?? null,
            'myfatoorah_payment_method' => $transaction['PaymentMethod'] ?? null,
            // The merchant settlement, which reaches the fee-drift check
            // through metadata rather than through the reported amount.
            'myfatoorah_receivable_amount' => $amount['ReceivableAmount'] ?? null,
            'myfatoorah_service_charge' => $amount['ServiceCharge'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * MyFatoorah sends `Error: {Code: "", Message: ""}` on success, so an
     * empty string means "no error" rather than "an error with no message".
     *
     * @param  array<string, mixed>  $transaction
     */
    private function errorMessage(array $transaction): ?string
    {
        $message = trim((string) data_get($transaction, 'Error.Message', ''));

        return $message !== '' ? $message : null;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function errorCode(array $transaction): ?string
    {
        $code = trim((string) data_get($transaction, 'Error.Code', ''));

        return $code !== '' ? $code : null;
    }

    /**
     * Brand and last four from the transaction's card object.
     *
     * PayloadSanitizer strips the card-shaped keys before anything is
     * persisted, so the brand and last four kept here are the only card facts
     * that survive — which is exactly what the payment_method_* columns are
     * for, and leaves the SAQ-A posture unchanged.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function snapshot(array $transaction): ?PaymentMethodSnapshot
    {
        $card = $transaction['Card'] ?? null;

        if (! is_array($card) || $card === []) {
            return null;
        }

        $brand = trim((string) ($card['Brand'] ?? ''));

        if ($brand === '') {
            return null;
        }

        // "512345xxxxxx0008" — the last four are the only digits we keep.
        $number = preg_replace('/\D/', '', (string) ($card['Number'] ?? '')) ?? '';
        $lastFour = strlen($number) >= 4 ? substr($number, -4) : '';

        $brandValue = str_replace(' ', '_', strtolower($brand));

        return PaymentMethodSnapshot::fromCardData(
            brand: $brandValue,
            lastFour: $lastFour,
            // fromCardData's default display name assumes a card number
            // ("Mastercard •••• 0008"). KNET and the wallet rails arrive with
            // no number at all, and "KNET •••• " reads as a bug in every
            // admin panel it is rendered in.
            displayName: $lastFour === ''
                ? (PaymentMethodBrand::tryFrom($brandValue) ?? PaymentMethodBrand::Other)->label()
                : null,
        );
    }
}
