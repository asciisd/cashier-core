<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;

/**
 * A connection that can push a withdrawal payout to the customer.
 *
 * The workflow owns locking, persistence and events; implementations own the
 * PSP contract. The two send exceptions are the whole point of the interface:
 * a rejection is safe to correct and resend, an unknown outcome is not.
 */
interface SendsPayouts
{
    /**
     * Whether the payout can go right now: funds and minimums. A failed lookup
     * refuses — it never passes.
     *
     * @throws PaymentProcessingException on configuration or input errors
     */
    public function preflight(PayoutRequest $request): PayoutPreflight;

    /**
     * @throws PayoutRejectedException the PSP refused; nothing was created
     * @throws PayoutOutcomeUnknownException the payout may exist — look it up before resending
     * @throws PaymentProcessingException on configuration or input errors
     */
    public function send(PayoutRequest $request): PayoutReceipt;

    /**
     * The payout for our order id, or null only when the PSP definitively
     * reports that no payout exists for it. A lookup that failed must never
     * return null — callers resend on null, and a payout that landed would
     * be paid twice.
     *
     * @throws PayoutOutcomeUnknownException when the lookup itself failed (timeout, 5xx, unreadable answer)
     */
    public function lookup(string $externalOrderId): ?PayoutReceipt;

    /**
     * @param  array<string, mixed>  $payload  a verified payout callback
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt;
}
