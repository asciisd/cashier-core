<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * One payout attempt, as the PSP needs it. Built by the workflow from a
 * Processing withdrawal row.
 */
readonly class PayoutRequest
{
    /**
     * @param  string  $externalOrderId  the attempt's order id (the row's provider_transaction_id)
     * @param  string  $customerId  MT5 login, falling back to the user id
     * @param  string  $amount  decimal string in $currency, e.g. "100.00"
     * @param  string  $currency  ISO code of the withdrawal, e.g. "USD"
     * @param  string  $payoutCurrency  PSP ticker the customer receives, e.g. "usdttrc20"
     */
    public function __construct(
        public string $externalOrderId,
        public string $customerId,
        public string $amount,
        public string $currency,
        public string $payoutCurrency,
        public string $payoutAddress,
        public ?string $payoutExtraId = null,
        public ?string $customerEmail = null,
    ) {}
}
