<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\ReturnOutcome;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    /*
     * charge() returns a signed link to the bridge route, so that route has to
     * exist for the URL to be signable. Task 5 registers it for real in the
     * service provider; until then a stub stands in, and the guard makes this
     * a no-op once the real one is registered.
     */
    if (! Route::has('cashier.checkout.xoala')) {
        Route::get('/cashier/xoala/checkout/{transaction}', fn () => '')
            ->name('cashier.checkout.xoala');

        // A fluently-set name is not in the router's name lookup table until
        // it is refreshed — normally triggered by dispatching a real HTTP
        // request. Nothing in this test suite does that, so Route::has()
        // (and route()) would see this route as unnamed without this. See
        // MyfatoorahProviderTest for the same workaround.
        Route::getRoutes()->refreshNameLookups();
    }
});

function xoalaConfig(array $overrides = []): array
{
    return array_merge([
        'driver' => 'xoala',
        'base_url' => 'https://xoala.test',
        'member_id' => '11344',
        'secure_key' => 'secure-key',
        'totype' => 'PartnerName',
        'redirect_url' => 'https://app.test/payment/success',
        'webhook_url' => 'https://app.test/api/webhooks/xoala',
    ], $overrides);
}

function xoalaProvider(array $overrides = []): XoalaProvider
{
    return new XoalaProvider(xoalaConfig($overrides));
}

it('refuses to construct without credentials', function () {
    new XoalaProvider(['base_url' => 'https://xoala.test']);
})->throws(PaymentProcessingException::class);

it('names itself and reports its features', function () {
    $provider = xoalaProvider();

    expect($provider->getName())->toBe('xoala')
        ->and($provider->supports('charge'))->toBeTrue()
        ->and($provider->supports('webhook'))->toBeTrue()
        ->and($provider->supports('refund'))->toBeFalse();
});

it('makes no HTTP call when charging', function () {
    Http::fake();

    xoalaProvider()->charge(['amount' => 50.0, 'order_id' => 'DEP-1']);

    Http::assertNothingSent();
});

it('returns a pending result pointing at the signed bridge route', function () {
    $result = xoalaProvider()->charge(['amount' => 50.0, 'order_id' => 'DEP-1']);

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(50)
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toContain('/xoala/checkout/DEP-1')
        ->and($result->getRedirectUrl())->toContain('signature=');
});

it('mints an id when the caller supplies none', function () {
    $result = xoalaProvider()->charge(['amount' => 10.0]);

    expect($result->transactionId)->toStartWith('DEP-');
});

it('rejects a charge with no usable amount', function () {
    xoalaProvider()->charge(['amount' => 0]);
})->throws(Illuminate\Validation\ValidationException::class);

it('declares the connection currency before the charge', function () {
    $customer = Mockery::mock(Asciisd\CashierCore\Contracts\CustomerContract::class);

    $prepared = xoalaProvider(['currency' => 'sar'])
        ->prepareChargeData($customer, 'xoala', ['amount' => 50.0, 'currency' => 'USD']);

    // Upper-cased, so the value the engine compares against the account
    // currency is the value Xoala is sent.
    expect($prepared['currency'])->toBe('SAR');
});

it('leaves the currency alone when the connection configures none', function () {
    $customer = Mockery::mock(Asciisd\CashierCore\Contracts\CustomerContract::class);

    $prepared = xoalaProvider()
        ->prepareChargeData($customer, 'xoala', ['amount' => 50.0, 'currency' => 'EUR']);

    expect($prepared['currency'])->toBe('EUR');
});

it('carries only the optional extras into metadata', function () {
    $result = xoalaProvider()->charge([
        'amount' => 50.0,
        'order_id' => 'DEP-1',
        'metadata' => ['user_email' => 'john@example.com', 'user_name' => 'John Doe'],
    ]);

    $fields = $result->metadata['xoala_fields'];

    expect($fields['email'])->toBe('john@example.com')
        ->and($fields)->not->toHaveKey('amount')
        ->and($fields)->not->toHaveKey('checksum')
        ->and($fields)->not->toHaveKey('merchantTransactionId');
});

it('builds a signed checkout form from a stored transaction', function () {
    $transaction = new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 50.0,
        'currency' => 'USD',
        'metadata' => ['xoala_fields' => ['email' => 'john@example.com']],
    ]);

    $form = xoalaProvider()->checkoutForm($transaction);

    expect($form['action'])->toBe('https://xoala.test/transaction/Checkout')
        ->and($form['fields']['memberId'])->toBe('11344')
        ->and($form['fields']['totype'])->toBe('PartnerName')
        ->and($form['fields']['amount'])->toBe('50.00')
        ->and($form['fields']['currency'])->toBe('USD')
        ->and($form['fields']['merchantTransactionId'])->toBe('DEP-1')
        ->and($form['fields']['transactionType'])->toBe('DB')
        ->and($form['fields']['email'])->toBe('john@example.com')
        ->and($form['fields']['checksum'])->toBe(
            (new XoalaSignatureService('11344', 'secure-key'))->forCheckout(
                'PartnerName',
                '50.00',
                'DEP-1',
                'https://app.test/payment/success',
            )
        );
});

it('signs the charge leg, not the account amount, on a converted deposit', function () {
    $transaction = new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 100.0,
        'currency' => 'USD',
        'charge_amount' => 375.0,
        'charge_currency' => 'SAR',
    ]);

    $form = xoalaProvider()->checkoutForm($transaction);

    expect($form['fields']['amount'])->toBe('375.00')
        ->and($form['fields']['currency'])->toBe('SAR');
});

it('signs the grossed-up amount, not the ledger amount, when fees apply', function () {
    $transaction = new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 100.0,
        'requested_amount' => 103.09,
        'currency' => 'USD',
        'charge_amount' => null,
        'charge_currency' => null,
    ]);

    $form = xoalaProvider()->checkoutForm($transaction);

    expect($form['fields']['amount'])->toBe('103.09');
});

it('verifies a webhook signature through the signature service', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => 'DEP-1',
        'amount' => '50.00',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', 'DEP-1', '50.00', 'Y');

    expect(xoalaProvider()->verifyWebhookSignature($payload, $payload['checksum']))->toBeTrue()
        ->and(xoalaProvider(['secure_key' => 'other'])->verifyWebhookSignature($payload, $payload['checksum']))->toBeFalse();
});

it('correlates a webhook on our own merchant transaction id', function () {
    expect(xoalaProvider()->extractWebhookTransactionId(['merchantTransactionId' => 'DEP-1']))->toBe('DEP-1')
        ->and(xoalaProvider()->extractWebhookTransactionId(['merchantTransactionId' => '']))->toBeNull()
        ->and(xoalaProvider()->extractWebhookTransactionId([]))->toBeNull();
});

it('retrieves a transaction through the inquiry endpoint', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response([
            'paymentId' => '54289',
            'status' => 'capturesuccess',
            'transactionStatus' => 'Y',
            'amount' => '50.00',
            'currency' => 'USD',
        ]),
    ]);

    $result = xoalaProvider()->retrieve('DEP-1');

    expect($result?->status)->toBe(PaymentStatus::Succeeded)
        ->and($result?->transactionId)->toBe('DEP-1');
});

it('sends the partner id when generating the auth token', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['status' => 'capturesuccess', 'transactionStatus' => 'Y']),
    ]);

    xoalaProvider(['partner_id' => '478'])->retrieve('DEP-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/authToken')
        && $request['authentication.partnerId'] === '478');
});

it('leaves the partner id off the auth token request when unset', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['status' => 'capturesuccess', 'transactionStatus' => 'Y']),
    ]);

    xoalaProvider()->retrieve('DEP-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/authToken')
        && ! array_key_exists('authentication.partnerId', $request->data()));
});

it('returns null from retrieve when the lookup fails', function () {
    Http::fake(['*' => Http::response('down', 502)]);

    expect(xoalaProvider()->retrieve('DEP-1'))->toBeNull();
});

it('reports unknown when the status cannot be read', function () {
    Http::fake(['*' => Http::response('down', 502)]);

    expect(xoalaProvider()->getPaymentStatus('DEP-1'))->toBe('unknown');
});

it('throws for the operations it does not implement', function (string $method, array $args) {
    xoalaProvider()->{$method}(...$args);
})->throws(BadMethodCallException::class)->with([
    ['refund', ['DEP-1', 1.0]],
    ['capture', ['DEP-1', 1.0]],
    ['authorize', [['amount' => 1.0]]],
    ['void', ['DEP-1']],
]);

function xoalaReturnPayload(string $status, array $overrides = []): array
{
    $payload = array_merge([
        'paymentId' => '98765',
        'merchantTransactionId' => 'DEP-1',
        'amount' => '100.00',
        'status' => $status,
    ], $overrides);

    $payload['checksum'] ??= (new XoalaSignatureService('11344', 'secure-key'))->forCallback(
        $payload['paymentId'],
        $payload['merchantTransactionId'],
        $payload['amount'],
        $payload['status'],
    );

    return $payload;
}

it('resolves the outcome of a signed redirect-back post', function (string $status, ReturnOutcome $expected) {
    expect(xoalaProvider()->returnOutcome(xoalaReturnPayload($status)))->toBe($expected);
})->with([
    'approved' => ['Y', ReturnOutcome::Succeeded],
    'declined' => ['N', ReturnOutcome::Failed],
    'cancelled' => ['C', ReturnOutcome::Failed],
    'pending' => ['P', ReturnOutcome::Unknown],
    'at the ACS page' => ['3D', ReturnOutcome::Unknown],
]);

it('treats an unsigned or tampered redirect-back post as unknown', function () {
    $provider = xoalaProvider();

    expect($provider->returnOutcome(xoalaReturnPayload('N', ['checksum' => 'forged'])))->toBe(ReturnOutcome::Unknown)
        ->and($provider->returnOutcome([]))->toBe(ReturnOutcome::Unknown);

    $tampered = xoalaReturnPayload('N');
    $tampered['status'] = 'Y';

    expect($provider->returnOutcome($tampered))->toBe(ReturnOutcome::Unknown);
});

function xoalaBillingCustomer(array $details, string $name = 'John Doe'): Asciisd\CashierCore\Contracts\CustomerContract
{
    return new class($details, $name) implements Asciisd\CashierCore\Contracts\CustomerContract, Asciisd\CashierCore\Contracts\ProvidesBillingDetails
    {
        public function __construct(private array $details, private string $name) {}

        public function cashierId(): int|string
        {
            return 42;
        }

        public function cashierEmail(): string
        {
            return 'contract@example.com';
        }

        public function cashierName(): string
        {
            return $this->name;
        }

        public function cashierLocale(): string
        {
            return 'en';
        }

        public function cashierBillingDetails(): array
        {
            return $this->details;
        }
    };
}

function xoalaPendingTransaction(): Transaction
{
    return new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 50.0,
        'currency' => 'USD',
    ]);
}

it('prefills the hosted page from the customer’s billing details', function () {
    $customer = xoalaBillingCustomer([
        'first_name' => 'Amr',
        'last_name' => 'Emad Eldin',
        'email' => 'amr@example.com',
        'phone' => '+201011441444',
        'phone_country_code' => '20',
        'country' => 'eg',
        'date_of_birth' => '1986-06-18',
        'street' => '2, Beta Gardens , October #4.',
        'city' => '6th of October',
        'region' => 'Giza',
        'zip_code' => '12 566',
    ]);

    $fields = xoalaProvider()->checkoutForm(xoalaPendingTransaction(), $customer, '203.0.113.9')['fields'];

    expect($fields)->toMatchArray([
        'firstName' => 'Amr',
        'lastName' => 'Emad Eldin',
        'email' => 'amr@example.com',
        'telnocc' => '20',
        'phone' => '1011441444',
        'country' => 'EG',
        'dateOfBirth' => '19860618',
        'street' => '2, Beta Gardens, October 4',
        'city' => '6th of October',
        'state' => 'Giza',
        'postcode' => '12566',
        'ip' => '203.0.113.9',
    ]);
});

it('drops a value Xoala’s pattern would refuse rather than sending it', function () {
    $customer = xoalaBillingCustomer([
        'first_name' => 'عمرو',
        'last_name' => 'Doe',
        'email' => 'not-an-email',
        'country' => 'EGY',
        'street' => 'شارع النيل',
        'zip_code' => '1',
        'date_of_birth' => 'unknown',
    ]);

    $fields = xoalaProvider()->checkoutForm(xoalaPendingTransaction(), $customer, '2001:db8::1')['fields'];

    expect($fields)->not->toHaveKeys(['firstName', 'email', 'country', 'street', 'postcode', 'dateOfBirth', 'ip'])
        ->and($fields['lastName'])->toBe('Doe');
});

it('sends the full number alone when no country code is known', function () {
    $fields = xoalaProvider()->checkoutForm(
        xoalaPendingTransaction(),
        xoalaBillingCustomer(['phone' => '+966 50 423 1856']),
    )['fields'];

    expect($fields['phone'])->toBe('+966504231856')
        ->and($fields)->not->toHaveKey('telnocc');
});

it('falls back to the contract name and email for a customer without billing details', function () {
    $customer = Mockery::mock(Asciisd\CashierCore\Contracts\CustomerContract::class);
    $customer->shouldReceive('cashierName')->andReturn('Jane Q Public');
    $customer->shouldReceive('cashierEmail')->andReturn('jane@example.com');

    $fields = xoalaProvider()->checkoutForm(xoalaPendingTransaction(), $customer)['fields'];

    expect($fields)->toMatchArray([
        'firstName' => 'Jane',
        'lastName' => 'Q Public',
        'email' => 'jane@example.com',
    ]);
});

it('sends no customer details when the connection turns them off', function () {
    $customer = xoalaBillingCustomer(['phone' => '+96550209185', 'country' => 'KW']);

    $fields = xoalaProvider(['send_customer_details' => false])
        ->checkoutForm(xoalaPendingTransaction(), $customer, '203.0.113.9')['fields'];

    expect($fields)->not->toHaveKeys(['phone', 'country', 'firstName', 'ip']);
});
