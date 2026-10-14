<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Fees;

/**
 * Resolves what a client-controlled payment actually settled at.
 *
 * Cards and bank rails fix the charge at checkout — the customer cannot pay
 * less than the page asks. Crypto can: the invoice is a wallet address that
 * accepts any amount, so the invoiced figure is a request, not a fact.
 */
final class SettledAmountResolver
{
    /**
     * @param  float  $invoicedAmount  the deposit amount `A` the invoice was opened for
     * @param  float  $expectedPayment  what the PSP asked the customer to send, in pay currency
     * @param  float  $actuallyPaid  what arrived, in the same pay currency
     * @return SettledAmount|null null when the pair cannot yield a ratio
     */
    public function resolve(
        float $invoicedAmount,
        float $expectedPayment,
        float $actuallyPaid,
    ): ?SettledAmount {
        // Without a positive expectation there is no ratio to take, and a zero
        // denominator must never reach the division.
        if ($expectedPayment <= 0.0 || $invoicedAmount <= 0.0) {
            return null;
        }

        $ratio = max(0.0, $actuallyPaid) / $expectedPayment;

        // The epsilon keeps a payment that is short by exactly the tolerance on
        // the inside of the band: 98/100 evaluates to 0.020000000000000018,
        // which would otherwise fall out of a 2% tolerance on a technicality.
        $withinTolerance = abs($ratio - 1.0) <= $this->tolerance() + 1e-9;

        return new SettledAmount(
            // The invoice already contains the deposit, our markup and the PSP
            // fee in fixed proportion, so paying a fraction of it pays that same
            // fraction of each part. Scaling the deposit by the ratio therefore
            // preserves the margin without subtracting it a second time — and it
            // stays continuous across the tolerance boundary, which subtracting
            // the markup would not.
            amount: $withinTolerance ? round($invoicedAmount, 2) : round($invoicedAmount * $ratio, 2),
            ratio: $ratio,
            withinTolerance: $withinTolerance,
        );
    }

    /**
     * The band inside which a shortfall is dust — network variance, a rounded
     * send — rather than a genuine underpayment.
     */
    private function tolerance(): float
    {
        return (float) config(
            'cashier-core.settlement.tolerance_percent',
            config('transactions.settlement.tolerance_percent', 2.0),
        ) / 100;
    }
}
