<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\Enums\ReturnOutcome;

/**
 * The browser-return seam, for a provider that sends the customer back to
 * ONE URL whatever happened and expects the host to ask it the result.
 *
 * Read-only: implementations must never write to a transaction. The answer
 * only chooses the page the customer lands on.
 */
interface ResolvesReturnOutcome
{
    /**
     * Resolve the outcome from the query string the provider appended to the
     * return URL. Must not throw: anything unreadable is Unknown.
     *
     * @param  array<string, mixed>  $query
     */
    public function returnOutcome(array $query): ReturnOutcome;
}
