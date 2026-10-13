<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * Builds the `receiver` block of a PayCrossBorder payout — the only part of
 * the payout body that differs between the crypto, bank and wallet rails.
 */
interface ReceiverBuilder
{
    public function rail(): Rail;

    /**
     * @throws PaymentProcessingException naming the first missing or invalid field
     */
    public function assertComplete(PayoutRequest $request): void;

    /**
     * @return array{customer: array<string, string>}
     *
     * @throws PaymentProcessingException
     */
    public function build(PayoutRequest $request): array;
}
