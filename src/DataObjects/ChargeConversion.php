<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * The price of one charge leg in a currency the account is not held in.
 *
 * `rate` and `marginPct` are recorded alongside the amount because a charge has
 * to be reproducible months later: the rate table is mutable, so a row that
 * stored only the result could never be explained.
 */
readonly class ChargeConversion
{
    public function __construct(
        public float $amount,
        public float $rate,
        public float $marginPct,
        public string $currency,
    ) {}

    /**
     * @return array{amount: float, rate: float, margin_pct: float, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'rate' => $this->rate,
            'margin_pct' => $this->marginPct,
            'currency' => $this->currency,
        ];
    }
}
