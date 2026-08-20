<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\ChargeConversion;

/**
 * Prices a charge leg in a currency the account is not held in.
 *
 * Consulted only when a driver's `prepareChargeData()` declares a currency
 * differing from `cashier-core.currency.default` — a PSP that cannot be sent
 * the account's currency at all (MyFatoorah accepts only the eight GCC
 * currencies, never USD).
 *
 * Implementations MUST throw rather than return an unconverted or zero amount.
 * A silent fallback here invoices a local figure as though it were the account
 * currency, which is money moved at the wrong size with nothing on screen
 * saying so.
 */
interface ConvertsChargeCurrency
{
    /**
     * @param  float  $amount  In `$from`, the account currency.
     * @param  string  $from  ISO-4217 account currency.
     * @param  string  $to  ISO-4217 charge currency.
     *
     * @throws \Asciisd\CashierCore\Exceptions\PaymentProcessingException When no usable rate exists.
     */
    public function convert(float $amount, string $from, string $to): ChargeConversion;
}
