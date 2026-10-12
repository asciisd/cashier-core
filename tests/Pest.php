<?php

use Asciisd\CashierCore\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The final Heropayment deposit callback for production txn 1923: a $900
 * deposit invoiced at $906.90 (910.15 USDT), of which the customer sent only
 * 898.50 USDT. Heropayment settled 891.75 USDT to the merchant.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function heropaymentUnderpaidCallback(array $overrides = []): array
{
    return array_merge([
        'id' => 'ae648aab-55a8-44d4-92cb-67796db78a04',
        'fiat' => true,
        'status' => 'sending',
        'feeUSDT' => 4.48618751,
        'invoice' => [
            'id' => 'a53c3593-945c-412a-928c-21313399c08d',
            'orderId' => 'DEP-01M4PQ4K3S3PAPTFM7J6FHJ60E',
            'customerId' => '10930',
            'priceAmount' => 906.9,
            'priceCurrency' => 'usd',
        ],
        'payRate' => 0.99859488,
        'sequence' => 'original',
        'payAmount' => 910.14920439,
        'priceRate' => 1.00107114,
        'feePercent' => 0.5,
        'networkFee' => 1,
        'paidAmount' => 898.5,
        'payCurrency' => 'usdttrc20',
        'clientAmount' => 895.27,
        'outcomeAmount' => 891.75131482,
        'outcomeCurrency' => 'usdtbsc',
        'externalOrderId' => 'DEP-01M4PQ4K3S3PAPTFM7J6FHJ60E',
        'transactionType' => 'deposit',
        'clientAmountUsdt' => 897.23750233,
        'merchantAmountUsdt' => 891.75131482,
    ], $overrides);
}
