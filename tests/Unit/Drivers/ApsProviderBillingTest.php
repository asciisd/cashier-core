<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\ProvidesBillingDetails;
use Asciisd\CashierCore\Drivers\Aps\ApsProvider;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function apsBillingConfig(array $overrides = []): array
{
    return array_merge([
        'driver' => 'aps',
        'base_url' => 'https://aps-applepay.test',
        'merchant_guid' => 'merchant-guid',
        'app_token' => 'token',
        'app_secret' => 'secret',
        'callback_secret' => 'callback-secret',
        'deposit_method' => 'deposit-guid',
    ], $overrides);
}

function apsPlainCustomer(): CustomerContract
{
    return new class implements CustomerContract
    {
        public function cashierId(): int|string
        {
            return 7;
        }

        public function cashierEmail(): string
        {
            return 'plain@example.com';
        }

        public function cashierName(): string
        {
            return 'Plain Customer';
        }

        public function cashierLocale(): string
        {
            return 'en';
        }
    };
}

function apsBillingCustomer(array $details = []): CustomerContract
{
    return new class($details) implements CustomerContract, ProvidesBillingDetails
    {
        public function __construct(private array $details) {}

        public function cashierId(): int|string
        {
            return 42;
        }

        public function cashierEmail(): string
        {
            return 'customer@example.com';
        }

        public function cashierName(): string
        {
            return 'Test Customer';
        }

        public function cashierLocale(): string
        {
            return 'en';
        }

        public function cashierBillingDetails(): array
        {
            return array_merge([
                'email' => 'customer@example.com',
                'country' => 'MA',
                'street' => '12 Rue Test',
                'city' => 'Casablanca',
                'zip_code' => '20000',
                'region' => 'Casa-Settat',
                'phone' => '+212600000000',
            ], $this->details);
        }
    };
}

function apsDepositBlockFor(CustomerContract $customer): array
{
    Http::fake(['*' => Http::response(['id' => 'tx-1', 'how' => 'https://pay.test/1'], 200)]);

    $provider = new ApsProvider(apsBillingConfig());
    $data = $provider->prepareChargeData($customer, 'aps_apple_pay', ['amount' => 25.0]);
    $provider->charge($data);

    $sent = [];

    Http::assertSent(function ($request) use (&$sent) {
        $sent = $request->data()['fields']['transaction']['deposit'] ?? [];

        return true;
    });

    return $sent;
}

it('sends the five fields apple pay requires', function () {
    $deposit = apsDepositBlockFor(apsBillingCustomer());

    expect($deposit)
        ->toHaveKey('from_email', 'customer@example.com')
        ->toHaveKey('from_country', 'MA')
        ->toHaveKey('billing_street', '12 Rue Test')
        ->toHaveKey('billing_town', 'Casablanca')
        ->toHaveKey('billing_post_code', '20000');
});

it('sends the optional region and phone when present', function () {
    $deposit = apsDepositBlockFor(apsBillingCustomer());

    expect($deposit)
        ->toHaveKey('billing_state', 'Casa-Settat')
        ->toHaveKey('from_mobile', '+212600000000');
});

it('omits every billing key for a customer without the contract', function () {
    $deposit = apsDepositBlockFor(apsPlainCustomer());

    expect(array_keys($deposit))
        ->not->toContain('from_email', 'from_country', 'billing_street', 'billing_town', 'billing_post_code');
});

it('drops null and empty values rather than sending them blank', function () {
    $deposit = apsDepositBlockFor(apsBillingCustomer([
        'city' => null,
        'zip_code' => '',
        'region' => null,
    ]));

    expect($deposit)
        ->not->toHaveKey('billing_town')
        ->not->toHaveKey('billing_post_code')
        ->not->toHaveKey('billing_state')
        ->toHaveKey('billing_street', '12 Rue Test');
});

it('falls back to the customer contract email when billing details omit it', function () {
    $deposit = apsDepositBlockFor(apsBillingCustomer(['email' => null]));

    expect($deposit)->toHaveKey('from_email', 'customer@example.com');
});

it('logs the whole rejection body, not the truncated exception message', function () {
    $body = json_encode(['error' => 'transaction_info_needed', 'fields' => [
        'billing_post_code' => ['description' => 'Invalid format'],
        'billing_street' => ['description' => 'Invalid format'],
        'billing_town' => ['description' => 'Invalid format'],
        'from_country' => ['description' => 'Invalid format'],
        'from_email' => ['description' => 'Invalid format'],
    ]]);

    Http::fake(['*' => Http::response($body, 400)]);

    $logged = null;
    Log::listen(function ($message) use (&$logged) {
        $logged = $message->context['error'] ?? $logged;
    });

    $provider = new ApsProvider(apsBillingConfig());

    expect(fn () => $provider->charge(['amount' => 25.0]))
        ->toThrow(PaymentProcessingException::class);

    expect($logged)
        ->toContain('from_email')
        ->toContain('billing_town');
});

it('logs the raw exception message with a null status on a transport failure', function () {
    // A connection timeout is a ConnectionException — a sibling of
    // RequestException, not a subclass — so it carries no HTTP response to
    // read a body from, and must fall back to $e->getMessage() while still
    // surfacing as a PaymentProcessingException rather than an uncaught 500.
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    $context = null;
    Log::listen(function ($message) use (&$context) {
        $context = array_key_exists('error', $message->context) ? $message->context : $context;
    });

    $provider = new ApsProvider(apsBillingConfig());

    expect(fn () => $provider->charge(['amount' => 25.0]))
        ->toThrow(PaymentProcessingException::class);

    expect($context)
        ->not->toBeNull()
        ->and($context['error'])->toBe('cURL error 28: timed out')
        ->and($context['http_status'])->toBeNull();
});
