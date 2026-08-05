<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Fees;

use Asciisd\CashierCore\Contracts\FeeConfigurationContract;
use Asciisd\CashierCore\Enums\SettlementMode;
use InvalidArgumentException;

/**
 * The single source of truth for what a deposit costs.
 *
 * Used by the quote endpoint, the charge path, and the persisted transaction
 * snapshot, so the number the customer is shown before redirecting is the same
 * number they are debited. Never re-derive a fee outside this class.
 */
final class FeeCalculator
{
    public function for(FeeConfigurationContract $method, float $amount): FeeBreakdown
    {
        $mode = $method->settlementMode();
        $rate = $method->feePercentage() / 100;
        $fixed = $method->feeFixed();

        if ($rate >= 1.0) {
            throw new InvalidArgumentException(
                "Payment method {$method->feeConfigurationId()} has a processor fee of {$method->feePercentage()}%, which cannot be grossed up.",
            );
        }

        $markup = $this->markupFor($method, $amount);
        $requested = $this->requestedAmount($mode, $amount, $markup, $rate, $fixed);
        $pspFee = $this->round($requested * $rate + $fixed);

        return new FeeBreakdown(
            amount: $this->round($amount),
            pspFee: $pspFee,
            markup: $markup,
            requestedAmount: $requested,
            // Only in `added` does the PSP debit more than we asked for; in the
            // other two the gross-up is already inside the request.
            chargedAmount: $mode->pspAddsAtCheckout()
                ? $this->round($requested + $pspFee)
                : $requested,
            settlementMode: $mode,
        );
    }

    /**
     * Our margin, always taken on the deposit itself rather than on the
     * grossed-up total — otherwise the markup would compound with the PSP fee
     * and drift from the configured percentage.
     */
    private function markupFor(FeeConfigurationContract $method, float $amount): float
    {
        return $this->round(
            $method->markupFixed() + ($amount * $method->markupPercentage() / 100),
        );
    }

    /**
     * What we send the PSP.
     *
     * `added`: the PSP appends its fee itself, so requesting the gross-up too
     * would charge the customer twice.
     *
     * `deducted` / `invoiced`: solved so that `requested - pspFee` lands exactly
     * on `amount + markup`. Taking the naive `amount * rate + fixed` instead
     * under-collects — at 6% + $0.25 on a $100 deposit it leaves ~$0.50 on the
     * table every time.
     */
    private function requestedAmount(
        SettlementMode $mode,
        float $amount,
        float $markup,
        float $rate,
        float $fixed,
    ): float {
        if ($mode->pspAddsAtCheckout()) {
            return $this->round($amount + $markup);
        }

        return $this->roundUp(($amount + $markup + $fixed) / (1 - $rate));
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }

    /**
     * Ceil to the cent, so a gross-up never rounds into under-collecting.
     */
    private function roundUp(float $value): float
    {
        return ceil($value * 100) / 100;
    }
}
