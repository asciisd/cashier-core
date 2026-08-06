<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Payport;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class PayportAdapter implements PaymentAdapterInterface
{
    /**
     * Transform an invoice creation response into a PaymentResult.
     *
     * The hosted page URL is in `url`. The context array is assembled by the
     * provider and carries the order id we generated, because the invoice
     * response echoes it back but the webhook is what we correlate on.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['order_id'],
            status: PaymentStatus::Pending,
            amount: $this->wholeUnits($response['amount_currency'] ?? null) ?? 0,
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['url'] ?? null,
                'payport_invoice_id' => $response['invoice_id'] ?? null,
                'payport_merchant_id' => $response['merchant_id'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response['response'] ?? $response,
        );
    }

    /**
     * Transform the `data` node of an invoice status response.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->mapStatus($payload['status'] ?? null);
        $invoice = is_array($payload['invoice'] ?? null) ? $payload['invoice'] : [];

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: $this->wholeUnits($invoice['amount_currency'] ?? null) ?? 0,
            currency: (string) ($invoice['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            metadata: $this->metadataFromPayload($payload + $invoice),
            processorResponse: $payload,
        );
    }

    /**
     * Transform a Payport callback into a TransactionWebhookUpdate.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->mapCallbackStatus($payload['status'] ?? null);

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->extractPaymentMethod($payload),
            metadata: $this->metadataFromPayload($payload),
            errorCode: $status === PaymentStatus::Canceled
                ? ($payload['cancellation_reason'] ?? null)
                : null,
            errorMessage: $status === PaymentStatus::Canceled
                ? ($payload['cancellation_reason'] ?? null)
                : null,
            amount: $this->wholeUnits($payload['amount_currency'] ?? null),
            currency: $payload['currency'] ?? null,
        );
    }

    /**
     * Map a Payport invoice status, as returned by `/api/v5/invoice/status`.
     *
     *   -1 cancelled  0 created  1 paid  2 user confirmed  3 trader confirmed
     *
     * `0` means the invoice exists and is awaiting payment. This differs from
     * the callback reading of the same code — {@see mapCallbackStatus()}.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match ((string) $providerStatus) {
            '1' => PaymentStatus::Succeeded,
            '-1' => PaymentStatus::Canceled,
            '2', '3' => PaymentStatus::Processing,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Map the status carried by a callback.
     *
     * Callbacks fire only on final statuses, so `0` cannot mean "created" here.
     * The vendor docs are self-inconsistent on the cancellation code — the
     * status table and every worked example use `-1`, while the API5 callback
     * prose says `0` — so both are treated as cancelled. That is the reading
     * under which the two documented variants agree.
     */
    public function mapCallbackStatus(mixed $providerStatus): PaymentStatus
    {
        return match ((string) $providerStatus) {
            '1' => PaymentStatus::Succeeded,
            '-1', '0' => PaymentStatus::Canceled,
            '2', '3' => PaymentStatus::Processing,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'payport';
    }

    /**
     * Payport amounts are whole currency units, matching PaymentResult::$amount.
     */
    private function wholeUnits(mixed $amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round((float) $amount);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        return array_filter([
            'payport_invoice_id' => $payload['invoice_id'] ?? null,
            'payport_merchant_id' => $payload['merchant_id'] ?? null,
            'payport_payment_system_type' => $payload['payment_system_type'] ?? null,
            'payport_fiat_currency' => $payload['fiat_currency'] ?? null,
            'payport_fiat_amount' => $payload['fiat_amount'] ?? null,
            // What Payport credits the merchant after its fee, in the invoice
            // currency. Recorded for reconciliation — never written to
            // `transactions.amount`, which stays the amount the customer owed.
            'payport_merchant_amount' => $payload['merchant_amount'] ?? null,
            'payport_cancellation_reason' => $payload['cancellation_reason'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentMethod(array $payload): ?PaymentMethodSnapshot
    {
        $systemType = strtolower((string) ($payload['payment_system_type'] ?? ''));
        $accountInfo = (string) ($payload['account_info'] ?? '');

        if ($systemType === '' && $accountInfo === '') {
            return null;
        }

        $type = $this->methodType($systemType);
        $lastFour = null;

        if ($type === PaymentMethodType::CreditCard) {
            $digits = preg_replace('/\D/', '', $accountInfo) ?? '';
            $lastFour = strlen($digits) >= 4 ? substr($digits, -4) : null;
        }

        return new PaymentMethodSnapshot(
            type: $type,
            brand: $systemType === 'swift' ? PaymentMethodBrand::SWIFT : PaymentMethodBrand::Other,
            lastFour: $lastFour,
            displayName: $this->displayName($systemType, $lastFour),
        );
    }

    private function methodType(string $systemType): PaymentMethodType
    {
        return match ($systemType) {
            'card_number', 'card_payment2', 'wise' => PaymentMethodType::CreditCard,
            'iban', 'swift', 'bank_account', 'common_account', 'korean_account',
            'imps', 'neft', 'rtgs', 'mexico_spei', 'mexico_spei_clabe' => PaymentMethodType::BankTransfer,
            'by_mobile', 'upi', 'phonepe', 'paytm', 'qiwi', 'advcash',
            'qr_image', 'qr_link', 'qr_code' => PaymentMethodType::DigitalWallet,
            'cash', 'oxxo', '7eleven' => PaymentMethodType::Cash,
            default => PaymentMethodType::Other,
        };
    }

    private function displayName(string $systemType, ?string $lastFour): string
    {
        $label = $systemType === ''
            ? 'Payport'
            : ucwords(str_replace('_', ' ', $systemType));

        return $lastFour === null ? $label : "{$label} •••• {$lastFour}";
    }
}
