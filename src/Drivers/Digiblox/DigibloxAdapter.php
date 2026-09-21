<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;

/**
 * Payload assembly and status mapping for Digiblox.
 *
 * Digiblox exposes three disjoint status vocabularies for the same money, and
 * they are not interchangeable: the deposits API reports a *lifecycle state*,
 * the webhook reports a *reconciliation verdict about the amount*, and
 * transfers have a lifecycle of their own. A PARTIALLY_PAID webhook and a
 * CONFIRMED deposit describe the same payment. Never compare them; never share
 * a mapper between them.
 */
class DigibloxAdapter
{
    /**
     * Relative tolerance for coverage. Absorbs decimal representation noise
     * without masking a genuine shortfall: at 1e-9 a 150.99 order is not
     * covered by 150.01, but the real 15023.8306 row is.
     */
    private const COVERAGE_TOLERANCE = 0.000000001;

    /**
     * Deposit lifecycle (GET /v3/deposits/merchant).
     *
     * Deliberately a three-way branch rather than an exhaustive match: any
     * status Digiblox adds later lands in Pending instead of breaking us.
     */
    public function mapDepositStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'CONFIRMED' => PaymentStatus::Succeeded,
            'REJECTED', 'REJECTED_BY_ADMIN' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Webhook reconciliation verdict.
     *
     * All three documented values mean money arrived. OVERPAID covers the order
     * and is fulfilled (the excess is settled separately); PARTIALLY_PAID does
     * not, and goes to review rather than to failure — the funds are real and
     * already credited. An unknown verdict must never reach Succeeded.
     */
    public function mapWebhookStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'COMPLETED', 'OVERPAID' => PaymentStatus::Succeeded,
            default => PaymentStatus::OnHold,
        };
    }

    /**
     * Centralized Transfer lifecycle. The spec warns this field is not a strict
     * enum, so anything unrecognised stays Pending and keeps being polled.
     */
    public function mapTransferStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'CONFIRMED' => PaymentStatus::Succeeded,
            'FAILED', 'REJECTED', 'DROPPED', 'EXPIRED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Digiblox rejects "150" and "150.5" with `fiat_amount must have 2
     * decimals`. number_format normalises binary-float artefacts (0.1 + 0.2).
     */
    public function formatFiatAmount(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Assemble the POST /v3/payments/guests body.
     *
     * @param  array<string, mixed>  $data    charge data
     * @param  array<string, mixed>  $config  the connection config
     * @return array{payload: array<string, mixed>, notification: array<string, string>}
     */
    public function buildLinkPayload(array $data, array $config): array
    {
        $cryptoCurrency = $config['crypto_currency'] ?? null;

        $payload = array_filter([
            'external_id' => (string) $data['external_id'],
            'merchant_id' => (string) ($config['merchant_id'] ?? ''),
            'payment_method' => 'CRYPTO_DEPOSIT',
            'fiat_currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
            'fiat_amount' => $this->formatFiatAmount($data['amount'] ?? 0),
            'crypto_currency' => $cryptoCurrency,
            // `network` without `crypto_currency` is rejected outright, so it
            // rides along with the asset or not at all.
            'network' => $cryptoCurrency ? ($config['network'] ?? null) : null,
            // Flow B. Absent for an anonymous guest — the widget identifies
            // the customer itself.
            'username' => $data['guest_email'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'payload' => $payload,
            // Required even when empty: omitting it fails with
            // `Invalid body - notification property is missing or invalid`.
            'notification' => array_filter([
                'success_url' => $config['success_url'] ?? null,
                'fail_url' => $config['fail_url'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /**
     * Was the order covered? Compares gross received against what was asked.
     *
     * `amount` is net of the platform fee — reconciling on it makes every
     * correct payment look short by exactly the fee. A deposit with no
     * recorded expectation is never covered: Digiblox deliberately refuses to
     * claim COMPLETED when it cannot verify the amount, and so do we.
     *
     * @param  array<string, mixed>  $webhookPayload
     */
    public function isCovered(array $webhookPayload): bool
    {
        $expected = (string) ($webhookPayload['expected_amount'] ?? '');

        if ($expected === '' || (float) $expected <= 0.0) {
            return false;
        }

        // A real settled deposit on this account arrived
        // 9.07e-7 short of expected -- 6.04e-11 relative -- and Digiblox still
        // booked it Completed. That is decimal representation noise, not an
        // underpayment, so an exact >= would misreport a fully paid order.
        // The tolerance is relative and explicit; never rely on bccomp()'s
        // default scale, which is 0 and truncates to whole units.
        $floor = (float) $expected * (1.0 - self::COVERAGE_TOLERANCE);

        return (float) ($webhookPayload['total_amount'] ?? 0) >= $floor;
    }

    /**
     * Gross received = credited amount + platform fee. Both arrive already
     * decimal-converted, so Currency.decimals is reference only.
     *
     * @param  array<string, mixed>  $row
     */
    public function grossReceived(array $row): string
    {
        return bcadd(
            (string) ($row['amount'] ?? '0'),
            (string) ($row['system_fee'] ?? '0'),
            9,
        );
    }

    /**
     * Turn a deposit webhook into a transaction update.
     *
     * Reconciliation runs on total_amount. PARTIALLY_PAID becomes OnHold, not
     * Failed: the funds are real and already credited, the order simply is not
     * covered.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->mapWebhookStatus($payload['status'] ?? null);

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            metadata: array_filter([
                'tx_hash' => $payload['tx_hash'] ?? null,
                'network' => $payload['network'] ?? null,
                'from_address' => $payload['from_address'] ?? null,
                'to_address' => $payload['to_address'] ?? null,
                'expected_amount' => $payload['expected_amount'] ?? null,
                'total_amount' => $payload['total_amount'] ?? null,
                'covered' => $this->isCovered($payload),
            ], fn ($value) => $value !== null),
            errorMessage: $status === PaymentStatus::OnHold
                ? 'Digiblox reported '.((string) ($payload['status'] ?? 'an unknown status')).' — held for review.'
                : null,
            // The gross the payer sent, not the fee-netted credit.
            amount: isset($payload['total_amount']) ? (float) $payload['total_amount'] : null,
            currency: isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
        );
    }
}
