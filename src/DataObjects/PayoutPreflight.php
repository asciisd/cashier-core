<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * The answer to "can this payout go now?". Amounts are decimal strings in the
 * PSP wallet currency.
 */
readonly class PayoutPreflight
{
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';

    public const BELOW_MINIMUM = 'below_minimum';

    public const BALANCE_UNAVAILABLE = 'balance_unavailable';

    public const QUOTE_UNAVAILABLE = 'quote_unavailable';

    public function __construct(
        public bool $ok,
        public ?string $balance = null,
        public ?string $required = null,
        public ?string $walletCurrency = null,
        public ?string $reason = null,
        public ?string $message = null,
    ) {}

    public static function passed(string $balance, string $required, string $walletCurrency): self
    {
        return new self(true, $balance, $required, $walletCurrency);
    }

    public static function refused(
        string $reason,
        string $message,
        ?string $balance = null,
        ?string $required = null,
        ?string $walletCurrency = null,
    ): self {
        return new self(false, $balance, $required, $walletCurrency, $reason, $message);
    }
}
