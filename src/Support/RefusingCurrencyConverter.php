<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Asciisd\CashierCore\Contracts\ConvertsChargeCurrency;
use Asciisd\CashierCore\DataObjects\ChargeConversion;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * Default binding for hosts that have configured no foreign-currency connection.
 *
 * Reaching this class means a driver declared a charge currency while the host
 * supplied no way to price it. Refusing is the only safe answer: returning the
 * amount unconverted would invoice a local figure as though it were the
 * account's currency — the exact defect this seam exists to prevent.
 */
class RefusingCurrencyConverter implements ConvertsChargeCurrency
{
    public function convert(float $amount, string $from, string $to): ChargeConversion
    {
        throw new PaymentProcessingException(
            "No ConvertsChargeCurrency implementation is bound, so a {$from} charge cannot be priced in {$to}. "
            .'Bind one in your service provider.'
        );
    }
}
