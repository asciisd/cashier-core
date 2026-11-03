<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\Enums\FeeSource;

/**
 * Opt-in companion to {@see FeeConfigurationContract} for a method whose PSP
 * fee is only known once the payment settles: a wallet whose rate depends on
 * the card inside it, a crypto rail charging a network fee.
 *
 * A separate interface so existing fee configurations keep compiling; one that
 * does not implement it is {@see FeeSource::Configured}.
 */
interface UsesReportedFee
{
    public function feeSource(): FeeSource;
}
