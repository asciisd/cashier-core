<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

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
     * decimals`. bcadd normalises binary-float artefacts (0.1 + 0.2) that
     * number_format would carry through.
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
        $expected = $webhookPayload['expected_amount'] ?? null;

        if ($expected === null || (float) $expected <= 0.0) {
            return false;
        }

        $total = (string) ($webhookPayload['total_amount'] ?? '0');

        return bccomp($total, $expected) >= 0;
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
}
