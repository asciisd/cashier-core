<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function myfatoorahConfig(array $overrides = []): array
{
    return array_merge([
        'driver' => 'myfatoorah',
        'base_url' => 'https://apitest.myfatoorah.com',
        'api_key' => 'test-api-key',
        'webhook_secret' => 'test-webhook-secret',
        'currency' => 'KWD',
        'redirect_url' => 'https://members.example.com/payment/success',
        'webhook_url' => 'https://members.example.com/api/webhooks/myfatoorah',
    ], $overrides);
}

function myfatoorahCustomer(): CustomerContract
{
    return new class implements CustomerContract
    {
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
            return 'ar';
        }
    };
}

describe('construction', function () {
    it('refuses a connection with no api key', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['api_key' => null])))
            ->toThrow(PaymentProcessingException::class, 'not configured');
    });

    it('refuses a connection with no base url', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['base_url' => null])))
            ->toThrow(PaymentProcessingException::class, 'not configured');
    });

    /*
     * A blank secret is not "no verification", it is a WORKING HMAC key, and
     * every field the canonical string is built from is public — the attacker
     * reads their own InvoiceId out of the redirect URL. Without this guard
     * they sign a forged SUCCESS with the empty key, the provider verifies
     * it, and the engine credits the ledger.
     */
    it('refuses a connection with no webhook secret', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['webhook_secret' => null])))
            ->toThrow(PaymentProcessingException::class, 'webhook secret');
    });

    it('refuses a connection whose webhook secret is a blank string', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['webhook_secret' => ''])))
            ->toThrow(PaymentProcessingException::class, 'webhook secret');
    });

    /*
     * `MYFATOORAH_WEBHOOK_SECRET=" "` reads as a non-empty string, so an
     * `=== ''` guard passes it and the connection resolves with a guessable
     * HMAC key — the same forged-callback hole, one space wide.
     */
    it('refuses a connection whose webhook secret is only whitespace', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['webhook_secret' => '   '])))
            ->toThrow(PaymentProcessingException::class, 'webhook secret');
    });

    /*
     * MyFatoorah cannot charge USD, so a connection with no currency would
     * charge in whatever PaymentService pinned and be rejected as an opaque
     * ValidationError on the first live charge.
     */
    it('refuses a connection with no currency', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['currency' => null])))
            ->toThrow(PaymentProcessingException::class, 'currency');
    });

    it('refuses a currency MyFatoorah cannot charge', function () {
        expect(fn () => new MyfatoorahProvider(myfatoorahConfig(['currency' => 'USD'])))
            ->toThrow(PaymentProcessingException::class, 'USD');
    });
});

describe('prepareChargeData', function () {
    /*
     * PaymentService pins every charge to cashier-core.currency.default and
     * forbids callers choosing one. MyFatoorah's Order.Currency accepts only
     * SAR, BHD, AED, QAR, OMR, KWD, JOD and EGP — USD is not among them — so
     * this hook is the documented escape.
     */
    it('overrides the currency PaymentService pinned', function () {
        $prepared = (new MyfatoorahProvider(myfatoorahConfig()))
            ->prepareChargeData(myfatoorahCustomer(), 'myfatoorah', ['amount' => 100.0, 'currency' => 'USD']);

        expect($prepared['currency'])->toBe('KWD');
    });

    it('attaches the customer name, which PaymentService does not merge', function () {
        $prepared = (new MyfatoorahProvider(myfatoorahConfig()))
            ->prepareChargeData(myfatoorahCustomer(), 'myfatoorah', ['amount' => 100.0]);

        expect($prepared['metadata']['user_name'])->toBe('Test Customer')
            ->and($prepared['metadata']['user_locale'])->toBe('ar');
    });

    it('leaves the amount untouched', function () {
        $prepared = (new MyfatoorahProvider(myfatoorahConfig()))
            ->prepareChargeData(myfatoorahCustomer(), 'myfatoorah', ['amount' => 106.25]);

        expect($prepared['amount'])->toBe(106.25);
    });
});

describe('charge', function () {
    beforeEach(function () {
        Http::fake(['*/v3/payments' => Http::response([
            'IsSuccess' => true,
            'Data' => [
                'InvoiceId' => '6309730',
                'PaymentId' => null,
                'PaymentURL' => 'https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae',
                'PaymentCompleted' => false,
                'TransactionDetails' => null,
            ],
        ])]);
    });

    it('creates a payment and returns the redirect', function () {
        $result = (new MyfatoorahProvider(myfatoorahConfig()))->charge([
            'amount' => 100.0,
            'currency' => 'KWD',
            'metadata' => ['user_id' => 42, 'user_email' => 'customer@example.com', 'user_name' => 'Test Customer'],
        ]);

        expect($result->transactionId)->toBe('6309730')
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->currency)->toBe('KWD')
            ->and($result->getRedirectUrl())->toBe('https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae');
    });

    it('sends the order, the customer and the integration urls', function () {
        (new MyfatoorahProvider(myfatoorahConfig()))->charge([
            'amount' => 100.0,
            'metadata' => ['user_id' => 42, 'user_email' => 'customer@example.com', 'user_name' => 'Test Customer'],
        ]);

        Http::assertSent(function ($request) {
            return $request['Order']['Amount'] === 100.0
                && $request['Order']['Currency'] === 'KWD'
                && str_starts_with($request['Order']['ExternalIdentifier'], 'DEP-')
                && $request['Customer']['Name'] === 'Test Customer'
                && $request['Customer']['Email'] === 'customer@example.com'
                && $request['Customer']['Reference'] === '42'
                && $request['IntegrationUrls']['Redirection'] === 'https://members.example.com/payment/success'
                && $request['IntegrationUrls']['Webhook'] === 'https://members.example.com/api/webhooks/myfatoorah';
        });
    });

    /*
     * The idempotency key is opt-in, holds for 250 minutes and is the only
     * protection against a double charge on a retry. It carries the same ULID
     * as ExternalIdentifier so a retry is recognisable from either end.
     */
    it('sends an idempotency key matching the external identifier', function () {
        (new MyfatoorahProvider(myfatoorahConfig()))->charge(['amount' => 100.0]);

        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $request['Order']['ExternalIdentifier']));
    });

    it('sends a configured payment method', function () {
        (new MyfatoorahProvider(myfatoorahConfig(['payment_method' => 'KNET'])))->charge(['amount' => 100.0]);

        Http::assertSent(fn ($request) => $request['PaymentMethod'] === 'KNET');
    });

    /*
     * Omitting PaymentMethod is what lands the customer on MyFatoorah's own
     * picker showing every method enabled on the account. Sending it as null
     * is NOT the same thing.
     */
    it('omits the payment method entirely when the connection sets none', function () {
        (new MyfatoorahProvider(myfatoorahConfig()))->charge(['amount' => 100.0]);

        Http::assertSent(fn ($request) => ! array_key_exists('PaymentMethod', $request->data()));
    });

    /*
     * `MYFATOORAH_PAYMENT_METHOD=` in a .env leaves the key present holding
     * an empty string, so `??` never fires and the final array_filter drops
     * null and [] but not ''. Sending `PaymentMethod: ''` is NOT the picker —
     * that needs the key absent — and is a validation error on every charge.
     */
    it('omits the payment method when the connection sets it to an empty string', function () {
        (new MyfatoorahProvider(myfatoorahConfig(['payment_method' => ''])))->charge(['amount' => 100.0]);

        Http::assertSent(fn ($request) => ! array_key_exists('PaymentMethod', $request->data()));
    });

    /*
     * Worse on the IntegrationUrls: an empty `webhook_url` would skip the
     * route fallback and send `Webhook: ''`. Omitting the parameter routes
     * events to the dashboard-configured URL; an empty one is undefined and
     * could lose every webhook for that invoice.
     */
    it('falls back to the route when webhook_url is set to an empty string', function () {
        (new MyfatoorahProvider(myfatoorahConfig(['webhook_url' => '', 'redirect_url' => ''])))
            ->charge(['amount' => 100.0]);

        Http::assertSent(fn ($request) => $request['IntegrationUrls']['Webhook'] === route('cashier.webhooks.myfatoorah')
            && $request['IntegrationUrls']['Webhook'] !== '');
    });

    it('derives the language from the customer locale when unconfigured', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig());
        $data = $provider->prepareChargeData(myfatoorahCustomer(), 'myfatoorah', ['amount' => 100.0]);

        $provider->charge($data);

        Http::assertSent(fn ($request) => $request['Language'] === 'AR');
    });

    it('prefers a configured language over the customer locale', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig(['language' => 'EN']));
        $data = $provider->prepareChargeData(myfatoorahCustomer(), 'myfatoorah', ['amount' => 100.0]);

        $provider->charge($data);

        Http::assertSent(fn ($request) => $request['Language'] === 'EN');
    });

    it('rejects a non-positive amount before calling out', function () {
        expect(fn () => (new MyfatoorahProvider(myfatoorahConfig()))->charge(['amount' => 0]))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    /*
     * A connection timeout is a ConnectionException — a sibling of
     * RequestException, not a subclass — so catching RequestException alone
     * lets it escape as an uncaught 500 with nothing logged.
     */
    it('converts a transport failure into a PaymentProcessingException', function () {
        Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('cURL error 28: timed out'));

        expect(fn () => (new MyfatoorahProvider(myfatoorahConfig()))->charge(['amount' => 100.0]))
            ->toThrow(PaymentProcessingException::class, 'could not be created');
    });
});

describe('retrieve and getPaymentStatus', function () {
    beforeEach(function () {
        Http::fake(['*/v3/invoices/6551972' => Http::response([
            'IsSuccess' => true,
            'Data' => [
                'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
                'Transactions' => [['Id' => '1', 'Status' => 'SUCCESS', 'TransactionDate' => '2026-03-01T08:03:54Z']],
                'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '100'],
            ],
        ])]);
    });

    it('retrieves an invoice as a PaymentResult', function () {
        $result = (new MyfatoorahProvider(myfatoorahConfig()))->retrieve('6551972');

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->transactionId)->toBe('6551972');
    });

    /*
     * The "No invoices match this InvoiceId" message means EITHER an unknown
     * invoice OR a real invoice with no payment attempt yet — api-v3.md says
     * so explicitly, and nothing in the response tells the two apart. Every
     * provider_transaction_id we hold came back from a successful
     * create-payment, so it is a healthy pending deposit. Reporting it as
     * missing made syncTransaction() log transactionNotFoundAtProvider and
     * return false for every one of them.
     */
    it('reports a healthy unattempted invoice as pending, not missing', function () {
        Http::fake(['*/v3/invoices/6600001' => Http::response([
            'IsSuccess' => false,
            'Message' => 'No invoices match this InvoiceId',
        ])]);

        $result = (new MyfatoorahProvider(myfatoorahConfig()))->retrieve('6600001');

        expect($result)->not->toBeNull()
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->transactionId)->toBe('6600001')
            ->and($result->success)->toBeFalse();
    });

    it('reports the raw provider status string', function () {
        expect((new MyfatoorahProvider(myfatoorahConfig()))->getPaymentStatus('6551972'))->toBe('SUCCESS');
    });

    it('reports PENDING rather than unknown for an unattempted invoice', function () {
        Http::fake(['*/v3/invoices/6600001' => Http::response([
            'IsSuccess' => false,
            'Message' => 'No invoices match this InvoiceId',
        ])]);

        expect((new MyfatoorahProvider(myfatoorahConfig()))->getPaymentStatus('6600001'))->toBe('PENDING');
    });

    /*
     * A genuine failure still has to come back null, or a dead gateway would
     * look like a wall of healthy pending deposits.
     */
    it('returns null and reports unknown when the lookup genuinely fails', function () {
        Sleep::fake();

        Http::fake(['*/v3/invoices/6600002' => Http::response('gateway down', 502)]);

        $provider = new MyfatoorahProvider(myfatoorahConfig());

        expect($provider->retrieve('6600002'))->toBeNull()
            ->and($provider->getPaymentStatus('6600002'))->toBe('unknown');
    });
});

describe('webhook seams', function () {
    it('extracts the invoice id as the correlation key', function () {
        $payload = ['Event' => ['Code' => 1], 'Data' => ['Invoice' => ['Id' => '6409988']]];

        expect((new MyfatoorahProvider(myfatoorahConfig()))->extractWebhookTransactionId($payload))->toBe('6409988');
    });

    it('returns null when the payload names no invoice', function () {
        expect((new MyfatoorahProvider(myfatoorahConfig()))->extractWebhookTransactionId(['Event' => ['Code' => 1]]))->toBeNull();
    });

    it('verifies a signature it can reproduce', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig());
        $payload = ['Event' => ['Code' => 1], 'Data' => [
            'Invoice' => ['Id' => '6409988', 'Status' => 'PAID', 'ExternalIdentifier' => 'DEP-1'],
            'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'PID-1'],
        ]];

        $signature = base64_encode(hash_hmac(
            'sha256',
            'Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=PID-1,Invoice.ExternalIdentifier=DEP-1',
            'test-webhook-secret',
            true,
        ));

        expect($provider->verifyWebhookSignature($payload, $signature))->toBeTrue()
            ->and($provider->verifyWebhookSignature($payload, 'not-the-signature'))->toBeFalse();
    });

    it('refuses to verify an event it does not implement', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig());

        expect($provider->verifyWebhookSignature(['Event' => ['Code' => 3], 'Data' => []], 'anything'))->toBeFalse();
    });

    it('parses a webhook body into an update', function () {
        $update = (new MyfatoorahProvider(myfatoorahConfig()))->parseWebhook([
            'Event' => ['Code' => 1, 'Reference' => 'WH-1'],
            'Data' => [
                'Invoice' => ['Id' => '6409988', 'Status' => 'PAID'],
                'Transaction' => ['Status' => 'SUCCESS'],
                'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '100'],
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(100.0);
    });
});

describe('capability reporting', function () {
    it('is named myfatoorah', function () {
        expect((new MyfatoorahProvider(myfatoorahConfig()))->getName())->toBe('myfatoorah');
    });

    it('supports charging and webhooks only', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig());

        expect($provider->supports('charge'))->toBeTrue()
            ->and($provider->supports('webhook'))->toBeTrue()
            ->and($provider->supports('refund'))->toBeFalse();
    });

    it('throws for every unsupported operation', function () {
        $provider = new MyfatoorahProvider(myfatoorahConfig());

        expect(fn () => $provider->refund('6409988'))->toThrow(BadMethodCallException::class)
            ->and(fn () => $provider->capture('6409988'))->toThrow(BadMethodCallException::class)
            ->and(fn () => $provider->authorize([]))->toThrow(BadMethodCallException::class)
            ->and(fn () => $provider->void('6409988'))->toThrow(BadMethodCallException::class);
    });
});
