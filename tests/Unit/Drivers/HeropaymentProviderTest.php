<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('falls back to the package webhook route for the deposit callback', function (?string $webhookUrl) {
    Http::fake(['hero.test/v2/invoices' => Http::response([
        'id' => 'hero-inv-1',
        'invoiceUrl' => 'https://pay.test/hero-inv-1',
        'priceAmount' => 100,
        'priceCurrency' => 'usd',
    ])]);

    (new HeropaymentProvider(array_merge(HeropaymentPayoutApi::CONFIG, ['webhook_url' => $webhookUrl])))
        ->charge(['amount' => 100]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/invoices')
        && $request['callbackUrl'] === route('cashier.webhooks.heropayment'));
})->with([
    'unset' => [null],
    'empty' => [''],
]);
