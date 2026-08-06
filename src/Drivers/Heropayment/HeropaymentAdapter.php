<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class HeropaymentAdapter implements PaymentAdapterInterface
{
    /**
     * Transform an invoice creation response into a PaymentResult.
     * The hosted widget URL is in `invoiceUrl`.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['id'],
            status: PaymentStatus::Pending,
            amount: (int) round((float) ($response['priceAmount'] ?? 0)),
            currency: strtoupper((string) ($response['priceCurrency'] ?? config('cashier-core.currency.default', 'USD'))),
            metadata: array_filter([
                'redirect_url' => $response['invoiceUrl'] ?? null,
                'heropayment_external_order_id' => $response['externalOrderId'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
            paymentMethodSnapshot: $this->cryptoSnapshot($response),
        );
    }

    /**
     * Transform a payment status payload into a PaymentResult.
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->mapStatus($payload['status'] ?? null);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: (int) round((float) ($payload['priceAmount'] ?? 0)),
            currency: strtoupper((string) ($payload['priceCurrency'] ?? config('cashier-core.currency.default', 'USD'))),
            metadata: $this->metadataFromPayload($payload),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->cryptoSnapshot($payload),
        );
    }

    /**
     * Transform a Heropayment status callback into a TransactionWebhookUpdate.
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $rawStatus = strtolower((string) ($payload['status'] ?? ''));
        $status = $this->mapStatus($rawStatus);

        $metadata = $this->metadataFromPayload($payload);

        // `hold` means the user must pass KYC at Heropayment — surface to admins.
        if ($rawStatus === 'hold') {
            $metadata['requires_attention'] = true;
        }

        // A crypto invoice is a wallet address, so the customer decides what to
        // send. These generic keys let the webhook processor reconcile the
        // deposit to what actually arrived without knowing it is Heropayment.
        $metadata += array_filter([
            'settlement_expected_payment' => $payload['payAmount'] ?? null,
            'settlement_actually_paid' => $payload['actuallyPaid'] ?? null,
        ], fn ($value) => $value !== null);

        if ($rawStatus === 'partially_paid') {
            $metadata['requires_attention'] = true;
        }

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->cryptoSnapshot($payload),
            metadata: $metadata,
            errorMessage: $status === PaymentStatus::Failed ? ($payload['error'] ?? "Heropayment status: {$rawStatus}") : null,
            amount: isset($payload['priceAmount']) ? (int) round((float) $payload['priceAmount']) : null,
            currency: isset($payload['priceCurrency']) ? strtoupper((string) $payload['priceCurrency']) : null,
        );
    }

    /**
     * Map a Heropayment status to a cashier-core PaymentStatus.
     *
     * Statuses: waiting, confirming, exchanging, sending, finished, failed,
     * refunded, hold, expired, partially_paid. Per Heropayment, `sending`
     * should also be treated as success for deposits.
     *
     * `partially_paid` settles: the coins arrived and are ours, just fewer than
     * invoiced. Leaving it Pending — as the unmapped default did — strands the
     * customer's money with no record and no admin signal. The deposit is
     * reconciled down to what was actually paid before it is credited.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtolower((string) $providerStatus)) {
            'finished', 'sending', 'partially_paid' => PaymentStatus::Succeeded,
            'waiting', 'confirming' => PaymentStatus::Pending,
            'exchanging', 'hold' => PaymentStatus::Processing,
            'failed', 'expired' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Canceled,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'heropayment';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        return array_filter([
            'heropayment_payment_id' => $payload['id'] ?? $payload['paymentId'] ?? null,
            'heropayment_status' => $payload['status'] ?? null,
            'heropayment_external_order_id' => $payload['externalOrderId'] ?? $payload['orderID'] ?? $payload['orderId'] ?? null,
            'heropayment_pay_currency' => $payload['payCurrency'] ?? null,
            'heropayment_pay_amount' => $payload['payAmount'] ?? null,
            'heropayment_actually_paid' => $payload['actuallyPaid'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cryptoSnapshot(array $payload): PaymentMethodSnapshot
    {
        $payCurrency = strtoupper((string) ($payload['payCurrency'] ?? ''));

        return new PaymentMethodSnapshot(
            type: PaymentMethodType::Cryptocurrency,
            brand: self::brandFromTicker($payCurrency),
            displayName: $payCurrency !== '' ? "Crypto ({$payCurrency})" : 'Crypto',
        );
    }

    /**
     * Resolve the coin behind a Heropayment ticker.
     *
     * Tickers carry the settlement network as a suffix — `usdttrc20`, `usdt20`,
     * `usdtbsc`, `usdc`, `usdcbsc` — so the coin is the prefix and the suffix is
     * noise for branding purposes. An unrecognised ticker stays `Other` rather
     * than guessing a coin we hold no mark for.
     */
    private static function brandFromTicker(string $ticker): PaymentMethodBrand
    {
        $ticker = strtolower($ticker);

        return match (true) {
            str_starts_with($ticker, 'usdt') => PaymentMethodBrand::USDT,
            str_starts_with($ticker, 'usdc') => PaymentMethodBrand::USDC,
            str_starts_with($ticker, 'btc') => PaymentMethodBrand::Bitcoin,
            str_starts_with($ticker, 'eth') => PaymentMethodBrand::Ethereum,
            default => PaymentMethodBrand::Other,
        };
    }
}
