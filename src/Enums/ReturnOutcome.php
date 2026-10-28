<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

/**
 * What a provider says about a payment at the moment its customer is sent
 * back to the host.
 *
 * It decides which page the customer sees, nothing more. The webhook remains
 * the only thing that moves a transaction's status.
 */
enum ReturnOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Still in progress, not looked up, or the lookup failed. A host should
     * NOT show a failure page for this: the money may already have moved, and
     * telling the customer to pay again risks a second charge.
     */
    case Unknown = 'unknown';
}
