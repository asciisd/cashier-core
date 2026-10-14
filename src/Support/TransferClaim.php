<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Models\Transaction;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Serializes ledger money movement for a transaction across every path that
 * can attempt it — webhook credits, admin sync, retry tooling, and the
 * withdrawal workflow.
 *
 * A ledger transfer is not reversible, so checking `mt5_ticket_number` alone
 * is not enough: two processes can both read it as null and both move funds.
 * The claim is written under a row lock, so exactly one caller wins the right
 * to call the ledger; the others see either the ticket, a changed status, or
 * a live claim and back off.
 */
class TransferClaim
{
    public const METADATA_KEY = 'mt5_transfer_claimed_at';

    /**
     * A claim older than this is treated as abandoned (the claiming process
     * died mid-transfer) and may be taken over — matching the window an
     * operator needs to check the ledger history before retrying by hand.
     */
    private const STALE_MINUTES = 10;

    /**
     * Atomically claim the right to move ledger funds for this transaction.
     *
     * Re-reads the row under a lock — the caller's instance may be stale — and
     * refuses when the row is gone or trashed, when its status moved off
     * `$expectedStatus` (when given), when a transfer already landed
     * (`mt5_ticket_number` set, unless `$requireNoTicket` is false — refunds
     * claim a row whose debit ticket exists), or when another process holds a
     * live claim.
     *
     * @param  string  $context  driver or action name for log attribution
     * @return Transaction|null the locked, claimed instance; null when the
     *                          caller must not touch the ledger
     */
    public function acquire(
        Transaction $transaction,
        string $context,
        ?PaymentStatus $expectedStatus = null,
        bool $requireNoTicket = true,
    ): ?Transaction {
        return DB::transaction(function () use ($transaction, $context, $expectedStatus, $requireNoTicket): ?Transaction {
            $locked = $this->lockedRow($transaction);

            if (! $locked) {
                return null;
            }

            if ($expectedStatus !== null && $locked->status !== $expectedStatus) {
                return null;
            }

            if ($requireNoTicket && $locked->mt5_ticket_number) {
                PaymentLogger::providerFundsAlreadyTransferred($context, $locked->id, (string) $locked->mt5_ticket_number);

                return null;
            }

            if ($this->hasFreshClaim($locked)) {
                PaymentLogger::providerFundsCreditInFlight($context, $locked->id, (string) $locked->metadata[self::METADATA_KEY]);

                return null;
            }

            $locked->update([
                'metadata' => array_merge($locked->metadata ?? [], [self::METADATA_KEY => now()->toIso8601String()]),
            ]);

            return $locked;
        });
    }

    /**
     * Finalize a claimed transfer: apply the caller's attributes (ticket,
     * status, timestamps, …) and release the claim in one write.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function settle(Transaction $transaction, array $attributes): void
    {
        $metadata = Arr::except($transaction->metadata ?? [], [self::METADATA_KEY]);

        if (array_key_exists('metadata', $attributes)) {
            $metadata = Arr::except($attributes['metadata'], [self::METADATA_KEY]);
        }

        $transaction->update(array_merge($attributes, ['metadata' => $metadata]));
    }

    /**
     * Release a claim whose transfer definitively did not happen, so another
     * path can attempt it again immediately.
     *
     * Do NOT call this after an exception from the ledger call — a timeout may
     * mean the transfer landed without a response, and the claim expiring on
     * its own is the window for checking ledger history first.
     */
    public function release(Transaction $transaction): void
    {
        $transaction->update([
            'metadata' => Arr::except($transaction->metadata ?? [], [self::METADATA_KEY]),
        ]);
    }

    /**
     * Perform a status transition under the row lock, without touching the
     * ledger.
     *
     * Refuses when the row is gone, moved off `$expectedStatus`, or another
     * process holds a live claim (it may be mid-transfer — its settle decides
     * the next status, not this transition).
     *
     * @param  array<string, mixed>|Closure(Transaction): array<string, mixed>  $attributes
     */
    public function transition(Transaction $transaction, PaymentStatus $expectedStatus, array|Closure $attributes): ?Transaction
    {
        return DB::transaction(function () use ($transaction, $expectedStatus, $attributes): ?Transaction {
            $locked = $this->lockedRow($transaction);

            if (! $locked || $locked->status !== $expectedStatus || $this->hasFreshClaim($locked)) {
                return null;
            }

            $locked->update($attributes instanceof Closure ? $attributes($locked) : $attributes);

            return $locked;
        });
    }

    /**
     * Whether another process claimed this transaction's transfer within the
     * staleness window. Callers must hold the row lock for the answer to be
     * trustworthy.
     */
    public function hasFreshClaim(Transaction $transaction): bool
    {
        $claimedAt = $transaction->metadata[self::METADATA_KEY] ?? null;

        return $claimedAt !== null
            && Carbon::parse((string) $claimedAt)->gt(now()->subMinutes(self::STALE_MINUTES));
    }

    private function lockedRow(Transaction $transaction): ?Transaction
    {
        $model = Cashier::transactionModel();

        return $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->whereKey($transaction->getKey())
            ->lockForUpdate()
            ->first();
    }
}
