<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

/**
 * The PSP side of a withdrawal payout — ops-facing, kept apart from the
 * customer-facing `status`, which stays Processing until the payout is paid.
 *
 * Null on the row means the payout was never sent.
 */
enum PayoutState: string
{
    /** The PSP accepted the payout and has not finished it yet. */
    case Sent = 'sent';

    /** The send timed out or errored: the payout may or may not exist. */
    case Unknown = 'unknown';

    /** The PSP refused or failed the payout; nothing is in flight. */
    case Failed = 'failed';

    /** The PSP reported the payout finished. Final. */
    case Paid = 'paid';

    /**
     * Whether money may be on its way — the states in which the MT5 debit
     * must not be refunded.
     */
    public function isInFlight(): bool
    {
        return $this === self::Sent || $this === self::Unknown;
    }
}
