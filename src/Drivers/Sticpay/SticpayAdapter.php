<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Sticpay;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class SticpayAdapter implements PaymentAdapterInterface
{
    /**
     * Labels for the codes a callback or redirect can carry when it brings no
     * message of its own.
     *
     * @var array<int, string>
     */
    private const CODE_LABELS = [
        1 => 'Payment was cancelled by the customer.',
        100 => 'The payment request expired.',
        200 => 'Sticpay could not find the customer account.',
        300 => 'Sticpay could not find the merchant account.',
        400 => 'Sticpay rejected the transfer.',
        410 => 'The amount is below Sticpay’s minimum.',
        411 => 'The amount is above Sticpay’s maximum.',
        412 => 'The customer reached their daily Sticpay limit.',
        413 => 'The customer reached their monthly Sticpay limit.',
        414 => 'The customer reached their yearly Sticpay limit.',
        415 => 'The payment breaks the customer’s Sticpay limits.',
        416 => 'Sticpay does not allow this type of transfer.',
        417 => 'The recipient is not verified and the amount exceeds the unverified limit.',
        500 => 'The customer has no Sticpay wallet for this currency.',
        501 => 'The merchant has no Sticpay wallet for this currency.',
        600 => 'Sticpay could not execute the transfer.',
        700 => 'The customer’s Sticpay balance is insufficient.',
        701 => 'The merchant’s Sticpay balance is insufficient.',
        900 => 'Sticpay could not process the payment.',
        1000 => 'The Sticpay merchant API is disabled.',
        1100 => 'No callback URL was configured for this Sticpay merchant.',
        1200 => 'The request came from an IP Sticpay has not whitelisted.',
        1300 => 'This order reference has already been used.',
        1400 => 'Sticpay could not find the transaction.',
        1401 => 'Sticpay could not find the order.',
        1402 => 'The transaction is not refundable.',
        1500 => 'The payer’s Sticpay account email does not match the one required.',
    ];

    /**
     * Transform a `/rest_pay/pay` response into a PaymentResult.
     *
     * The hosted page URL is in `link` and is valid for five minutes. The order
     * number is assembled by the provider rather than read back, because it is
     * what the callback is correlated on.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['order_no'],
            status: PaymentStatus::Pending,
            amount: $this->wholeUnits($response['order_amount'] ?? null) ?? 0,
            currency: (string) ($response['order_currency'] ?? config('transactions.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['link'] ?? null,
                // The PAY link dies after five minutes. Nothing offers it as a
                // resumable payment today; anything that ever does must check
                // this rather than re-serving a dead token.
                'sticpay_link_expires_at' => $response['link_expires_at'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            processorResponse: $response['response'] ?? $response,
        );
    }

    /**
     * Transform a `/rest_transaction/detail` response.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->mapStatus($payload['status'] ?? null);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: $this->wholeUnits($payload['from_amount'] ?? null) ?? 0,
            currency: (string) ($payload['from_currency'] ?? config('transactions.currency.default', 'USD')),
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            metadata: $this->metadataFromPayload($payload),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->extractPaymentMethod($payload),
        );
    }

    /**
     * Transform a flat Sticpay callback into a TransactionWebhookUpdate.
     *
     * @param  array<string, mixed>  $payload  the flattened callback parameters
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->toUpdate($payload, $this->mapCallbackStatus($payload['callback_code'] ?? null));
    }

    /**
     * Build an update for a status resolved elsewhere — the Transaction Detail
     * API confirmation path, or a guard that is holding the transaction.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extraMetadata
     */
    public function toUpdate(array $payload, PaymentStatus $status, array $extraMetadata = []): TransactionWebhookUpdate
    {
        $failed = in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true);
        $code = $payload['callback_code'] ?? null;

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->extractPaymentMethod($payload),
            metadata: $this->metadataFromPayload($payload) + $extraMetadata,
            errorCode: $failed && $code !== null ? (string) $code : null,
            errorMessage: $failed ? $this->messageFor($payload) : null,
            amount: $this->wholeUnits($payload['order_amount'] ?? null),
            currency: $payload['order_currency'] ?? null,
        );
    }

    /**
     * Map a Sticpay transaction status, as returned by
     * `/rest_transaction/detail`. This is the only place Sticpay publishes a
     * real outcome — {@see mapCallbackStatus()} for why that matters.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtolower(trim((string) $providerStatus))) {
            'approved' => PaymentStatus::Succeeded,
            'rejected' => PaymentStatus::Failed,
            'pending' => PaymentStatus::Processing,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Map the code carried by a callback or redirect envelope.
     *
     * The transaction callback (§2-6) is always `-1`, which the docs describe
     * as having "no specific meaning" — Sticpay fires it only for a transfer it
     * has already saved, so it reads as success. The redirect envelopes carry
     * `0` (paid), `1` (cancelled) and the error codes.
     */
    public function mapCallbackStatus(mixed $providerStatus): PaymentStatus
    {
        if ($providerStatus === null || $providerStatus === '') {
            return PaymentStatus::Pending;
        }

        return match ((int) $providerStatus) {
            -1, 0 => PaymentStatus::Succeeded,
            1, 100 => PaymentStatus::Canceled,
            200, 300, 400, 410, 411, 412, 413, 414, 415, 416, 417,
            500, 501, 600, 700, 701,
            800, 801, 802, 803, 804, 805, 806, 807, 808, 809, 850,
            900, 1000, 1100, 1200, 1300, 1400, 1401, 1402, 1500 => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'sticpay';
    }

    /**
     * Sticpay amounts are whole currency units, matching PaymentResult::$amount.
     */
    private function wholeUnits(mixed $amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round((float) $amount);
    }

    /**
     * The best available human-readable reason for a failed payment.
     *
     * @param  array<string, mixed>  $payload
     */
    private function messageFor(array $payload): ?string
    {
        $messages = $payload['callback_message'] ?? [];

        if (is_array($messages)) {
            $first = $messages[0]['message'] ?? null;

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        $code = $payload['callback_code'] ?? null;

        return $code === null ? null : (self::CODE_LABELS[(int) $code] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        $metadata = [
            'sticpay_transaction_code' => $payload['transaction_code'] ?? null,
            'sticpay_transaction_time' => $payload['transaction_time'] ?? null,
            'sticpay_fee' => $payload['fee'] ?? null,
            'sticpay_fee_currency' => $payload['fee_currency'] ?? null,
            'sticpay_order_currency' => $payload['order_currency'] ?? $payload['from_currency'] ?? null,
            'sticpay_interface_version' => $payload['interface_version'] ?? null,
            'sticpay_customer_email' => $payload['customer_email'] ?? null,
            // The detail API's settlement leg, which can differ in currency from
            // what the customer was charged.
            'sticpay_to_amount' => $payload['to_amount'] ?? null,
            'sticpay_to_currency' => $payload['to_currency'] ?? null,
        ];

        return array_filter(
            $metadata + $this->settlementMetadata($payload),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * What Sticpay credits the merchant after its fee, for fee reconciliation.
     *
     * Only computed when the fee is denominated in the currency the customer
     * was charged in. Sticpay settles into the merchant's default wallet
     * currency when it holds none for the requested one, and subtracting a fee
     * across currencies would report a figure that is simply wrong — so the
     * mismatch is flagged for an admin instead.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function settlementMetadata(array $payload): array
    {
        $amount = $payload['order_amount'] ?? $payload['from_amount'] ?? null;
        $fee = $payload['fee'] ?? null;

        if ($amount === null || $amount === '' || $fee === null || $fee === '') {
            return [];
        }

        $orderCurrency = strtoupper(trim((string) ($payload['order_currency'] ?? $payload['from_currency'] ?? '')));
        $feeCurrency = strtoupper(trim((string) ($payload['fee_currency'] ?? '')));

        if ($feeCurrency !== '' && $orderCurrency !== '' && $feeCurrency !== $orderCurrency) {
            return ['requires_attention' => true];
        }

        return ['sticpay_merchant_amount' => round((float) $amount - (float) $fee, 2)];
    }

    /**
     * Sticpay is a wallet — there is no instrument to report beyond that.
     *
     * The payer's wallet email is deliberately kept out of the display name:
     * it is another person's contact detail and the snapshot is rendered in
     * the member UI and in Nova.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentMethod(array $payload): ?PaymentMethodSnapshot
    {
        $hasWalletContext = ($payload['customer_email'] ?? '') !== ''
            || ($payload['transaction_code'] ?? '') !== '';

        if (! $hasWalletContext) {
            return null;
        }

        return new PaymentMethodSnapshot(
            type: PaymentMethodType::DigitalWallet,
            brand: PaymentMethodBrand::Other,
            lastFour: null,
            displayName: 'Sticpay',
        );
    }
}
