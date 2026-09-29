<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\PayoutState;

/**
 * What the PSP says about a payout — from a create response, a lookup, or a
 * callback.
 */
readonly class PayoutReceipt
{
    /**
     * @param  string|null  $reference  the PSP's payment id
     * @param  array<string, mixed>  $payload  the raw PSP payload
     * @param  string|null  $error  the failure reason when $state is Failed
     */
    public function __construct(
        public ?string $reference,
        public string $rawStatus,
        public PayoutState $state,
        public array $payload = [],
        public ?string $error = null,
    ) {}
}
