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
}
