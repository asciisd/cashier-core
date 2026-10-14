<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A scripted Heropayments V2 API for payout tests. Every endpoint the payout
 * path touches answers from the options, so a test states only what differs.
 *
 * Defaults: balance 500.00 usdttrc20, every rate 1.0, minimum 5, withdrawal
 * network fee 1.0 — so a 100.00 USD payout needs
 * (100 × 1.006 + 1) × 1.02 = 103.632 in the wallet.
 */
final class HeropaymentPayoutApi
{
    public const CONFIG = [
        'driver' => 'heropayment',
        'class' => HeropaymentProvider::class,
        'base_url' => 'https://hero.test',
        'api_key' => 'test-key',
        'api_secret' => 'test-secret',
        'webhook_url' => 'https://members.example.com/api/webhooks/heropayment',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'fee_percent' => '0.6',
        'balance_buffer_percent' => '2',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function configure(array $overrides = []): array
    {
        $config = array_merge(self::CONFIG, $overrides);

        config()->set('cashier-core.connections.heropayment', $config);

        return $config;
    }

    /**
     * `balance`, `withdrawal` and `lookup` take an Http::response() or a
     * Closure(Request), which may throw (e.g. ConnectionException for a
     * timeout). `fee_ticker` is the one coin the withdrawal network-fee row
     * is listed for; `fee` may be null to emit that row's `networkfee` as
     * null (simulating a missing/unreadable withdrawal fee — see
     * HeropaymentQuoteService::networkFees(), which then omits the ticker
     * from withdrawalNetworkFees() rather than reading it as zero).
     *
     * @param  array{balance?: mixed, wallet?: string, rate?: ?string, min?: float, fee?: ?string, fee_ticker?: string, withdrawal?: mixed, lookup?: mixed}  $options
     */
    public static function fake(array $options = []): void
    {
        $o = $options + [
            'balance' => '500.00',
            'wallet' => 'usdttrc20',
            'rate' => '1.0',
            'min' => 5.0,
            'fee' => '1.0',
            'fee_ticker' => 'usdttrc20',
            'withdrawal' => null,
            'lookup' => null,
        ];

        Http::fake(function (Request $request) use ($o) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/v2/withdrawal') {
                $answer = $o['withdrawal'] ?? Http::response(self::withdrawal());

                return $answer instanceof Closure ? $answer($request) : $answer;
            }

            if (str_starts_with($path, '/v2/payments/order/')) {
                $answer = $o['lookup'] ?? Http::response(['message' => 'Not found'], 404);

                return $answer instanceof Closure ? $answer($request) : $answer;
            }

            if ($path === '/v2/balance') {
                if ($o['balance'] instanceof Closure) {
                    return $o['balance']($request);
                }

                return $o['balance'] === null
                    ? Http::response([], 500)
                    : Http::response(['walletAddress' => 'TMerchant', 'walletCurrency' => $o['wallet'], 'balance' => $o['balance']]);
            }

            return match ($path) {
                '/v2/rate' => $o['rate'] === null
                    ? Http::response([], 500)
                    : Http::response(['rate' => $o['rate']]),
                '/v2/min-amount' => Http::response(['minDeposit' => $o['min'], 'minWithdrawal' => $o['min'], 'currency' => 'usdttrc20']),
                '/v2/network-fees' => Http::response([
                    ['networkfee' => '9.0', 'ticker' => 'usdttrc20', 'type' => 'deposit'],
                    ['networkfee' => $o['fee'], 'ticker' => $o['fee_ticker'], 'type' => 'withdrawal'],
                ]),
                default => Http::response([], 404),
            };
        });
    }

    /**
     * A V2 create-withdrawal response / callback body (v2.md, callbacks.md).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function withdrawal(array $overrides = []): array
    {
        return array_merge([
            'id' => 'hero-wd-1',
            'status' => 'waiting',
            'invoice' => [
                'id' => 'hero-inv-1',
                'customerId' => '70001',
                'orderId' => null,
                'priceCurrency' => 'usd',
                'priceAmount' => 100,
            ],
            'externalOrderId' => 'WD-01TEST',
            'sequence' => 'original',
            'transactionType' => 'withdrawal',
            'payCurrency' => 'usdttrc20',
            'payAmount' => 101.6,
            'payHash' => null,
            'paidAmount' => 0,
            'outcomeAmount' => 0,
            'outcomeAddress' => 'TXyzCustomer',
            'outcomeCurrency' => 'usdttrc20',
            'feePercent' => 0.6,
            'networkFee' => 1,
            'clientAmount' => 100,
            'merchantAmountUsdt' => 101.6,
            'fiat' => true,
        ], $overrides);
    }
}
