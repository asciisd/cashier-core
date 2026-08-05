<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\Enums\SettlementMode;

/**
 * The pricing facts FeeCalculator reads off a payment method.
 *
 * Implement on whatever your application uses as its method catalog — an
 * Eloquent model, a config-backed value object — the calculator only needs
 * these five numbers and the settlement mode.
 */
interface FeeConfigurationContract
{
    public function settlementMode(): SettlementMode;

    /**
     * The PSP's percentage fee (e.g. 6.0 for 6%).
     */
    public function feePercentage(): float;

    /**
     * The PSP's fixed fee per transaction, in the charge currency.
     */
    public function feeFixed(): float;

    /**
     * Our percentage margin on the deposit itself.
     */
    public function markupPercentage(): float;

    /**
     * Our fixed margin per transaction.
     */
    public function markupFixed(): float;

    /**
     * An identifier for error messages and logs.
     */
    public function feeConfigurationId(): int|string|null;
}
