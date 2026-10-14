<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Carbon\CarbonImmutable;

/**
 * An indicative pre-redirect quote for a Heropayment crypto deposit.
 *
 * Every figure here is an estimate. Heropayments exposes no rate lock and no
 * quote expiry, so the widget re-quotes when the customer lands on it and the
 * final numbers arrive on the status callback (`payAmount`, `feePercent`,
 * `networkFee`, `merchantAmount`). Always label these values as indicative.
 */
final readonly class HeropaymentQuote
{
    public function __construct(
        public string $priceCurrency,
        public float $priceAmount,
        public string $payCurrency,
        public float $rate,
        public float $payAmount,
        public ?float $networkFee,
        public ?float $networkFeeInPriceCurrency,
        public ?float $providerFeePercent,
        public ?float $providerFeeAmount,
        public ?float $minDeposit,
        public ?float $minDepositInPriceCurrency,
        public bool $meetsMinimum,
        public ?float $estimatedNetCredit,
        public CarbonImmutable $quotedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'price_currency' => $this->priceCurrency,
            'price_amount' => $this->priceAmount,
            'pay_currency' => $this->payCurrency,
            'rate' => $this->rate,
            'pay_amount' => $this->payAmount,
            'network_fee' => $this->networkFee,
            'network_fee_unit' => $this->payCurrency,
            'network_fee_in_price_currency' => $this->networkFeeInPriceCurrency,
            'provider_fee_percent' => $this->providerFeePercent,
            'provider_fee_amount' => $this->providerFeeAmount,
            'min_deposit' => $this->minDeposit,
            'min_deposit_in_price_currency' => $this->minDepositInPriceCurrency,
            'meets_minimum' => $this->meetsMinimum,
            'estimated_net_credit' => $this->estimatedNetCredit,
            'quoted_at' => $this->quotedAt->toIso8601String(),
            'indicative' => true,
        ];
    }
}
