<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Fees;

use Asciisd\CashierCore\Enums\SettlementMode;

/**
 * Every number a deposit resolves to, from the amount the customer typed.
 *
 * The three that move money are distinct and must not be conflated:
 * `requestedAmount` goes to the PSP, `chargedAmount` is what the customer is
 * debited, and `amount` is what reaches MT5. They coincide only when both fees
 * are zero.
 */
final readonly class FeeBreakdown
{
    public function __construct(
        /** What the customer asked to deposit, and what MT5 is credited. */
        public float $amount,
        /** The PSP's cost, in the mode's terms. */
        public float $pspFee,
        /** Our margin on top. */
        public float $markup,
        /** What we send the PSP — grossed up unless the PSP adds its own fee. */
        public float $requestedAmount,
        /** What the customer is actually debited. */
        public float $chargedAmount,
        public SettlementMode $settlementMode,
    ) {}

    /**
     * Everything the customer pays beyond their deposit.
     *
     * Always equals `chargedAmount - amount`; kept as a field so callers and
     * the UI do not each re-derive it.
     */
    public function totalFee(): float
    {
        return round($this->pspFee + $this->markup, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'psp_fee' => $this->pspFee,
            'markup' => $this->markup,
            'total_fee' => $this->totalFee(),
            'requested_amount' => $this->requestedAmount,
            'charged_amount' => $this->chargedAmount,
            'settlement_mode' => $this->settlementMode->value,
        ];
    }
}
