# MyFatoorah Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add MyFatoorah as a bundled driver in `asciisd/cashier-core`, supporting hosted-redirect deposits, V2 webhooks and sync against the MyFatoorah V3 API.

**Architecture:** Four classes under `src/Drivers/Myfatoorah/` — a client owning the HTTP surface and MyFatoorah's five-shaped error envelope, a signature service owning the canonical-string HMAC recipe, an adapter owning payload→DTO mapping, and a provider implementing `PaymentProcessorInterface` plus `ProvidesWebhookTransactionId` and `PreparesChargeData`. A webhook controller verifies against every configured connection's secret, exactly as `ApsWebhookController` does. Nothing is added to `PaymentService`.

**Tech Stack:** PHP 8.3, Laravel 11/12/13, Pest 2/3/4, Orchestra Testbench, Laravel HTTP client (`Illuminate\Support\Facades\Http`).

**Spec:** `docs/superpowers/specs/2026-08-19-myfatoorah-driver-design.md`
**Gateway contract:** `.claude/skills/myfatoorah/references/` — read `pitfalls.md` first.

## Global Constraints

- Namespace `Asciisd\CashierCore\Drivers\Myfatoorah`. Class prefix `Myfatoorah` (one capital), matching `Heropayment`.
- Driver string, route segment and `getName()` are all `myfatoorah`.
- Every PHP file starts `<?php` then a blank line then `declare(strict_types=1);`.
- Use `Asciisd\CashierCore\Logging\PaymentLogger` for anything it already has a method for. The webhook controller's own diagnostics — the version gate and the unhandled-event drop — use the `Log::` facade directly, because `PaymentLogger` has no method for either and `EnforcesSignatureVerification` already logs that way. Do not add `PaymentLogger` methods for them.
- Use `Asciisd\CashierCore\Support\PspHttp::client()` for POSTs and `PspHttp::idempotent()` for GETs. Charge POSTs must never retry.
- Never merge the V2 and V3 status vocabularies into one case-insensitive table (`pitfalls.md` entries 1–2).
- Run tests with `vendor/bin/pest`. Filter with `--filter`.
- Scope is deposits, webhooks and sync only. `refund()`, `capture()`, `authorize()` and `void()` throw `\BadMethodCallException`.
- `supports()` returns true only for `charge` and `webhook`.

### Amount precision — read once, then stop worrying

`transactions.amount` is `decimal(16,2)` and `PaymentResult::$amount` is `int`. KWD, BHD, OMR and JOD are three-decimal currencies. The driver sends `Order.Amount` as the exact float it received in `$paymentData['amount']`, which already came from a two-decimal world, so **the driver loses no precision** — MyFatoorah simply receives a value whose third decimal is zero, and the webhook echoes the same figure back, so the OnHold tolerance comparison matches exactly. Do not add rounding, scaling or minor-unit conversion anywhere. Do not widen the migration.

---

### Task 1: MyfatoorahSignatureService

The canonical-string HMAC recipe. Built first because the client, the controller and the test seams all depend on it, and because getting it wrong produces a well-formed hash that fails identically to every other mistake.

**Files:**
- Create: `src/Drivers/Myfatoorah/MyfatoorahSignatureService.php`
- Test: `tests/Unit/Drivers/MyfatoorahSignatureServiceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED` — `int`, value `1`.
  - `new MyfatoorahSignatureService(string $secret)`
  - `supports(int $eventCode): bool`
  - `canonical(int $eventCode, array $data): string`
  - `sign(int $eventCode, array $data): string` — base64
  - `verify(int $eventCode, array $data, string $signature): bool`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/MyfatoorahSignatureServiceTest.php`. The expected canonical string is transcribed **literally** from the "Webhook Signature" block of the `PAYMENT_STATUS_CHANGED` data model in `.claude/skills/myfatoorah/references/webhooks.md` — a vendor reference reproduced in the test, not the service checked against itself.

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;

beforeEach(function () {
    $this->service = new MyfatoorahSignatureService('test-webhook-secret');
});

/**
 * The `Data` object of the PAYMENT_STATUS_CHANGED sample event, verbatim from
 * references/webhooks.md, trimmed to the fields the signature reads plus
 * enough neighbours to prove the builder ignores them.
 *
 * @return array<string, mixed>
 */
function myfatoorahPaymentEventData(): array
{
    return [
        'Invoice' => [
            'Id' => '6409988',
            'Status' => 'PAID',
            'Reference' => '2026000073',
            'ExternalIdentifier' => 'asdqwd-f13sdf-fasjkz',
        ],
        'Transaction' => [
            'Id' => '86781',
            'Status' => 'SUCCESS',
            'PaymentId' => '07076409988323998875',
            'PaymentMethod' => 'VISA/MASTER',
        ],
        'Amount' => [
            'DisplayCurrency' => 'KWD',
            'ValueInDisplayCurrency' => '1',
        ],
    ];
}

describe('canonical', function () {
    it('builds the string MyFatoorah documents for PAYMENT_STATUS_CHANGED', function () {
        // Transcribed literally from references/webhooks.md, the "Webhook
        // Signature" block under the Payment Status Data Model.
        $expected = 'Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07076409988323998875,Invoice.ExternalIdentifier=asdqwd-f13sdf-fasjkz';

        expect($this->service->canonical(
            MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED,
            myfatoorahPaymentEventData(),
        ))->toBe($expected);
    });

    it('replaces a null value with the empty string and keeps the key', function () {
        $data = myfatoorahPaymentEventData();
        $data['Invoice']['ExternalIdentifier'] = null;

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data))
            ->toEndWith(',Invoice.ExternalIdentifier=');
    });

    it('keeps the key when the field is absent entirely', function () {
        $data = myfatoorahPaymentEventData();
        unset($data['Transaction']['PaymentId']);

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data))
            ->toContain(',Transaction.PaymentId=,');
    });

    /*
     * The field order is MyFatoorah's, not the payload's. Signing the fields
     * in payload order is one of the three mistakes that fail identically to
     * each other — see pitfalls.md entry 3.
     */
    it('takes its field order from the event definition, not the payload', function () {
        $data = myfatoorahPaymentEventData();
        $reordered = ['Transaction' => $data['Transaction'], 'Amount' => $data['Amount'], 'Invoice' => $data['Invoice']];

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $reordered))
            ->toBe($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data));
    });
});

describe('sign', function () {
    it('is base64 of the binary HMAC-SHA256, not hex', function () {
        $canonical = $this->service->canonical(
            MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED,
            myfatoorahPaymentEventData(),
        );

        expect($this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, myfatoorahPaymentEventData()))
            ->toBe(base64_encode(hash_hmac('sha256', $canonical, 'test-webhook-secret', true)));
    });
});

describe('verify', function () {
    it('accepts its own signature', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeTrue();
    });

    it('rejects a signature made with another secret', function () {
        $data = myfatoorahPaymentEventData();
        $other = (new MyfatoorahSignatureService('someone-elses-secret'))
            ->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $other))->toBeFalse();
    });

    it('rejects a tampered signed field', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        $data['Transaction']['Status'] = 'FAILED';

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeFalse();
    });

    it('ignores a tampered unsigned field', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        $data['Amount']['ValueInDisplayCurrency'] = '9999';

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeTrue();
    });

    it('rejects an empty signature', function () {
        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, myfatoorahPaymentEventData(), ''))->toBeFalse();
    });
});

describe('supports', function () {
    it('supports the payment status event only', function () {
        expect($this->service->supports(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED))->toBeTrue()
            ->and($this->service->supports(2))->toBeFalse()
            ->and($this->service->supports(7))->toBeFalse();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahSignatureService`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Myfatoorah/MyfatoorahSignatureService.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

/**
 * MyFatoorah webhook signatures.
 *
 * Nothing about this is the usual `hmac(rawBody, secret)`. MyFatoorah signs a
 * string it builds from a NAMED SUBSET of fields in a PRESCRIBED ORDER:
 *
 *   1. Take the fields for this event code, in the documented order.
 *   2. Join as `key=value,key2=value2` — no spaces, comma-separated. The keys
 *      are the dotted paths (`Invoice.Id`), not the JSON nesting.
 *   3. A null value becomes the empty string and the KEY STAYS.
 *   4. base64(hmac_sha256(string, secret)) — binary HMAC then base64, not hex
 *      — compared against the `MyFatoorah-Signature` header with hash_equals.
 *
 * Signing the raw body, the whole `Data` object, or the fields in payload
 * order all fail — and fail IDENTICALLY, so the error tells you nothing about
 * which mistake you made. See the myfatoorah skill, references/pitfalls.md
 * entries 3-4.
 *
 * Only PAYMENT_STATUS_CHANGED is implemented, because it is the only event
 * this driver acts on; every other event is ACKed and dropped by the
 * controller before any signature work happens. The builder itself is
 * event-agnostic, so adding an event later is a new SIGNED_FIELDS entry and
 * no new logic. The remaining six field lists are tabulated in the design
 * spec, docs/superpowers/specs/2026-08-19-myfatoorah-driver-design.md.
 */
final class MyfatoorahSignatureService
{
    public const PAYMENT_STATUS_CHANGED = 1;

    /**
     * Dotted paths into the webhook's `Data` object, in MyFatoorah's order.
     * The order is load-bearing: it is neither alphabetical nor payload order.
     *
     * @var array<int, list<string>>
     */
    private const SIGNED_FIELDS = [
        self::PAYMENT_STATUS_CHANGED => [
            'Invoice.Id',
            'Invoice.Status',
            'Transaction.Status',
            'Transaction.PaymentId',
            'Invoice.ExternalIdentifier',
        ],
    ];

    public function __construct(private readonly string $secret) {}

    public function supports(int $eventCode): bool
    {
        return isset(self::SIGNED_FIELDS[$eventCode]);
    }

    /**
     * The exact string MyFatoorah signed, built from the event's field list.
     *
     * @param  array<string, mixed>  $data  the webhook's `Data` object
     */
    public function canonical(int $eventCode, array $data): string
    {
        $pairs = [];

        foreach (self::SIGNED_FIELDS[$eventCode] ?? [] as $path) {
            $value = data_get($data, $path);

            // Null AND absent both become the empty string with the key kept.
            $pairs[] = $path.'='.($value === null ? '' : (string) $value);
        }

        return implode(',', $pairs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sign(int $eventCode, array $data): string
    {
        return base64_encode(
            hash_hmac('sha256', $this->canonical($eventCode, $data), $this->secret, true)
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function verify(int $eventCode, array $data, string $signature): bool
    {
        return hash_equals($this->sign($eventCode, $data), $signature);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=MyfatoorahSignatureService`
Expected: PASS — 11 tests

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Myfatoorah/MyfatoorahSignatureService.php tests/Unit/Drivers/MyfatoorahSignatureServiceTest.php
git commit -m "feat(myfatoorah): add the webhook signature service

MyFatoorah signs a canonical key=value,key2=value2 string built from a
named field subset in a prescribed order, not the request body. Null and
absent both collapse to an empty value with the key kept. Checked against
the canonical string transcribed literally from the vendored docs.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: MyfatoorahClient

The HTTP surface plus the five-shaped error envelope. Isolated from the adapter so the envelope's ugliness has one home.

**Files:**
- Create: `src/Drivers/Myfatoorah/MyfatoorahClient.php`
- Test: `tests/Unit/Drivers/MyfatoorahClientTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces:
  - `new MyfatoorahClient(string $baseUrl, string $apiKey)` — `$baseUrl` already `rtrim`ed by the caller.
  - `createPayment(array $payload, string $idempotencyKey): array` — returns the `Data` object; throws `PaymentProcessingException` on any failure envelope.
  - `getInvoice(string $invoiceId): ?array` — returns the `Data` object, or `null` when MyFatoorah does not know the invoice.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/MyfatoorahClientTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahClient;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = new MyfatoorahClient('https://apitest.myfatoorah.com', 'test-api-key');
});

describe('createPayment', function () {
    it('posts to /v3/payments with bearer auth and the idempotency key', function () {
        Http::fake([
            '*/v3/payments' => Http::response([
                'IsSuccess' => true,
                'Message' => '',
                'ValidationErrors' => null,
                'Data' => [
                    'InvoiceId' => '6309730',
                    'PaymentId' => null,
                    'PaymentURL' => 'https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae',
                    'PaymentCompleted' => false,
                    'TransactionDetails' => null,
                ],
            ]),
        ]);

        $data = $this->client->createPayment(['Order' => ['Amount' => 10.0]], 'DEP-01KZQX');

        expect($data['InvoiceId'])->toBe('6309730')
            ->and($data['PaymentURL'])->toBe('https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://apitest.myfatoorah.com/v3/payments'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request->hasHeader('Idempotency-Key', 'DEP-01KZQX')
                && $request['Order']['Amount'] === 10.0;
        });
    });

    /*
     * The five shapes an `IsSuccess: false` arrives in. Only the first is
     * documented in the Response Model page — see pitfalls.md entry 6.
     */

    it('shape 1: raises the documented ValidationErrors envelope', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Message' => 'Invalid data',
            'ValidationErrors' => [['Name' => 'InvoiceValue', 'Error' => 'must be greater than 0']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'InvoiceValue: must be greater than 0');
    });

    it('shape 2: raises FieldsErrors, the same array under another key', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'FieldsErrors' => [['Name' => 'PaymentMethod', 'Error' => 'is not enabled']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'PaymentMethod: is not enabled');
    });

    it('shape 2b: falls back to the field name when Error is an empty string', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'ValidationErrors' => [['Name' => 'CustomerEmail', 'Error' => '']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'CustomerEmail');
    });

    it('shape 3: raises Data.ErrorMessage when there is no error array', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Data' => ['ErrorMessage' => 'The payment method is not available'],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'The payment method is not available');
    });

    /*
     * The dangerous one: no `IsSuccess` field at all. A parser that keys off
     * IsSuccess reads this routing error as a success.
     */
    it('shape 4: raises a Message/MessageDetail body carrying no IsSuccess field', function () {
        Http::fake(['*' => Http::response([
            'Message' => 'No HTTP resource was found that matches the request URI.',
            'MessageDetail' => 'No route data was found.',
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'No HTTP resource was found');
    });

    it('shape 5: raises an HTML 403 that is not JSON at all', function () {
        Http::fake(['*' => Http::response('<html><body>403 Forbidden</body></html>', 403)]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'non-JSON');
    });

    it('accepts IsSuccess as the string "true" as well as the boolean', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => 'true',
            'Data' => ['InvoiceId' => '1', 'PaymentURL' => 'https://pay.test'],
        ])]);

        expect($this->client->createPayment([], 'k')['InvoiceId'])->toBe('1');
    });

    it('raises when the envelope succeeds but carries no Data object', function () {
        Http::fake(['*' => Http::response(['IsSuccess' => true, 'Message' => 'ok', 'Data' => null])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class);
    });
});

describe('getInvoice', function () {
    it('gets /v3/invoices/{id} and returns the Data object', function () {
        Http::fake(['*/v3/invoices/6551972' => Http::response([
            'IsSuccess' => true,
            'Message' => 'Invoice Retrieved Successfully',
            'Data' => [
                'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
                'Transactions' => [['Id' => '77235', 'Status' => 'SUCCESS']],
            ],
        ])]);

        $data = $this->client->getInvoice('6551972');

        expect($data['Invoice']['Status'])->toBe('PAID');

        Http::assertSent(fn ($request) => $request->url() === 'https://apitest.myfatoorah.com/v3/invoices/6551972'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    });

    /*
     * MyFatoorah answers an unknown invoice with a 200 and a Message, not a
     * 404. retrieve() must get null so PaymentService reports "not found at
     * provider" rather than throwing mid-sync.
     */
    it('returns null when MyFatoorah does not know the invoice', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Message' => 'No invoices match this InvoiceId',
        ])]);

        expect($this->client->getInvoice('404404'))->toBeNull();
    });

    it('returns null on an HTTP failure rather than throwing', function () {
        Http::fake(['*' => Http::response('gateway down', 502)]);

        expect($this->client->getInvoice('6551972'))->toBeNull();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahClient`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahClient" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Myfatoorah/MyfatoorahClient.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * The MyFatoorah V3 HTTP surface, and the one place that knows how many ways
 * MyFatoorah can say "no".
 *
 * The base URL is per country: api.myfatoorah.com serves Kuwait, Bahrain,
 * Oman and Jordan; Saudi Arabia, the UAE, Qatar and Egypt each have their
 * own; every country shares the apitest.myfatoorah.com sandbox. One API key
 * belongs to exactly one country and must be sent to that country's host, so
 * a merchant trading in two countries is two connections — the host is
 * configuration, never derived here.
 */
final class MyfatoorahClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Create a payment. Returns the `Data` object: `InvoiceId`, `PaymentId`,
     * `PaymentURL`, `PaymentCompleted`, `TransactionDetails`.
     *
     * `$idempotencyKey` is opt-in, holds for 250 minutes, and is the only
     * protection against a double charge on a retry — nothing turns it on for
     * you. See pitfalls.md entry 11.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post("{$this->baseUrl}/v3/payments", $payload);

        return $this->envelope($response);
    }

    /**
     * Fetch an invoice with its transaction array. Returns null when
     * MyFatoorah does not know it.
     *
     * Keyed by InvoiceId rather than PaymentId: PaymentId is null on every
     * redirect-flow create response, so it is not an id we reliably hold.
     * An unknown invoice comes back as a 200 with `"Message": "No invoices
     * match this InvoiceId"`, not a 404, which is why the envelope decides
     * this rather than the status code.
     *
     * @return array<string, mixed>|null
     */
    public function getInvoice(string $invoiceId): ?array
    {
        $response = $this->idempotentRequest()->get("{$this->baseUrl}/v3/invoices/{$invoiceId}");

        try {
            return $this->envelope($response);
        } catch (PaymentProcessingException $e) {
            PaymentLogger::providerTransactionLookupFailed(
                'myfatoorah',
                $invoiceId,
                $response->status(),
                Str::limit($response->body(), 500),
            );

            return null;
        }
    }

    /**
     * Unwrap MyFatoorah's response envelope, or throw with the best message
     * the body affords.
     *
     * `IsSuccess: false` arrives in at least five shapes and the Response
     * Model page documents only the first (pitfalls.md entry 6):
     *
     *   1. `ValidationErrors: [{Name, Error}]`  — the documented envelope
     *   2. `FieldsErrors: [{Name, Error}]`      — same thing, other key
     *   3. `Data.ErrorMessage`                  — a string, no error array
     *   4. `Message` + `MessageDetail` and NO `IsSuccess` field at all
     *   5. an HTML page rather than JSON — a 403 from Azure App Gateway
     *
     * Shape 4 is the dangerous one: a parser keyed off `IsSuccess` reads a
     * routing error as a success.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    private function envelope(Response $response): array
    {
        $body = $response->json();

        // Shape 5. json() is null for an HTML body.
        if (! is_array($body)) {
            throw new PaymentProcessingException(
                "MyFatoorah returned a non-JSON response ({$response->status()})."
            );
        }

        // Shape 4, checked before the value so an absent key is not read as false.
        if (! array_key_exists('IsSuccess', $body)) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        // Documented as a string ("true"/"false") but sent as a boolean in
        // every example. filter_var accepts both.
        if (filter_var($body['IsSuccess'], FILTER_VALIDATE_BOOLEAN) !== true) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        $data = $body['Data'] ?? null;

        if (! is_array($data)) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        return $data;
    }

    /**
     * Assemble a message defensively: `ValidationErrors[].Error` may be an
     * empty string with the field name in `Name`, so a message built only
     * from `Error` values comes out blank.
     *
     * @param  array<string, mixed>  $body
     */
    private static function errorMessage(array $body): string
    {
        $errors = $body['ValidationErrors'] ?? $body['FieldsErrors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $parts = [];

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    $parts[] = trim((string) $error);

                    continue;
                }

                $parts[] = trim(trim((string) ($error['Name'] ?? '')).': '.trim((string) ($error['Error'] ?? '')), ': ');
            }

            $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

            if ($parts !== []) {
                return 'MyFatoorah rejected the request: '.implode('; ', $parts);
            }
        }

        $dataError = data_get($body, 'Data.ErrorMessage');

        if (is_string($dataError) && trim($dataError) !== '') {
            return 'MyFatoorah rejected the request: '.trim($dataError);
        }

        $message = trim(trim((string) ($body['Message'] ?? '')).' '.trim((string) ($body['MessageDetail'] ?? '')));

        return $message !== ''
            ? 'MyFatoorah rejected the request: '.$message
            : 'MyFatoorah rejected the request.';
    }

    private function request(): PendingRequest
    {
        return $this->withAuth(PspHttp::client());
    }

    /**
     * For invoice lookups only — safely repeatable, so retries are allowed.
     * The charge POST deliberately does not retry: a create call that timed
     * out may have succeeded, and replaying it opens a second invoice.
     */
    private function idempotentRequest(): PendingRequest
    {
        return $this->withAuth(PspHttp::idempotent());
    }

    private function withAuth(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->apiKey)->acceptJson();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=MyfatoorahClient`
Expected: PASS — 12 tests

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Myfatoorah/MyfatoorahClient.php tests/Unit/Drivers/MyfatoorahClientTest.php
git commit -m "feat(myfatoorah): add the V3 HTTP client and envelope guard

createPayment and getInvoice, plus the one place that normalises the five
shapes an IsSuccess: false arrives in — including the routing error that
carries no IsSuccess field and would otherwise read as a success, and the
HTML 403 that is not JSON. Lookups key on InvoiceId because PaymentId is
null on every redirect-flow create response.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: PaymentMethodBrand::Knet and MyfatoorahAdapter

Payload→DTO mapping. The enum case comes first in the same task because the adapter's card snapshot depends on it and neither is testable without the other.

**Files:**
- Modify: `src/Enums/PaymentMethodBrand.php` — add a `Knet` case plus arms in `label()` and `getType()`
- Create: `src/Drivers/Myfatoorah/MyfatoorahAdapter.php`
- Test: `tests/Unit/Drivers/MyfatoorahAdapterTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–2.
- Produces:
  - `PaymentMethodBrand::Knet` — backed value `'knet'`, `getType()` returns `PaymentMethodType::DebitCard`.
  - `MyfatoorahAdapter` implementing `PaymentAdapterInterface`: `fromProviderResponse(mixed $response): PaymentResult`, `fromProviderPayload(string $transactionId, array $payload): PaymentResult`, `fromWebhook(array $payload): TransactionWebhookUpdate`, `mapStatus(mixed $providerStatus): PaymentStatus`, `getProviderName(): string`.
  - `fromProviderResponse()` expects the create-payment `Data` object with two extra keys merged in by the provider: `amount` (float, major units) and `currency` (string).
  - `fromProviderPayload()` expects the invoice `Data` object (`Invoice`, `Transactions`, `Customer`, `Amount`).
  - `fromWebhook()` expects the **whole** webhook body (`Event` + `Data`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/MyfatoorahAdapterTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new MyfatoorahAdapter;
});

/**
 * The PAYMENT_STATUS_CHANGED sample event from references/webhooks.md, with
 * the amounts kept as MyFatoorah sends them: strings, and with the base and
 * display figures deliberately different so the adapter cannot pass by
 * reading the wrong one.
 *
 * @return array<string, mixed>
 */
function myfatoorahWebhook(string $transactionStatus = 'SUCCESS', string $invoiceStatus = 'PAID'): array
{
    return [
        'Event' => [
            'Code' => 1,
            'Name' => 'PAYMENT_STATUS_CHANGED',
            'CountryIsoCode' => 'KWT',
            'CreationDate' => '2026-01-04T08:15:00.9500000Z',
            'Reference' => 'WH-626519',
        ],
        'Data' => [
            'Invoice' => [
                'Id' => '6409988',
                'Status' => $invoiceStatus,
                'Reference' => '2026000073',
                'ExternalIdentifier' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            ],
            'Transaction' => [
                'Id' => '86781',
                'Status' => $transactionStatus,
                'PaymentMethod' => 'VISA/MASTER',
                'PaymentId' => '07076409988323998875',
                'Error' => ['Code' => '', 'Message' => ''],
                'Card' => [
                    'Number' => '512345xxxxxx0008',
                    'Brand' => 'Mastercard',
                    'ExpiryMonth' => '12',
                    'ExpiryYear' => '36',
                    'FundingMethod' => 'credit',
                ],
            ],
            'Amount' => [
                'BaseCurrency' => 'KWD',
                'ValueInBaseCurrency' => '30.75',
                'ServiceCharge' => '0.02',
                'ReceivableAmount' => '30.50',
                'DisplayCurrency' => 'KWD',
                'ValueInDisplayCurrency' => '100.00',
                'PayCurrency' => 'KWD',
                'ValueInPayCurrency' => '100.00',
            ],
        ],
    ];
}

describe('mapStatus', function () {
    it('maps the V3 transaction vocabulary', function () {
        expect($this->adapter->mapStatus('SUCCESS'))->toBe(PaymentStatus::Succeeded)
            ->and($this->adapter->mapStatus('FAILED'))->toBe(PaymentStatus::Failed)
            ->and($this->adapter->mapStatus('CANCELED'))->toBe(PaymentStatus::Canceled)
            ->and($this->adapter->mapStatus('INPROGRESS'))->toBe(PaymentStatus::Processing)
            ->and($this->adapter->mapStatus('AUTHORIZE'))->toBe(PaymentStatus::RequiresCapture);
    });

    it('maps the V3 invoice vocabulary', function () {
        expect($this->adapter->mapStatus('PAID'))->toBe(PaymentStatus::Succeeded)
            ->and($this->adapter->mapStatus('PENDING'))->toBe(PaymentStatus::Pending);
    });

    it('maps unknown and null to Pending', function () {
        expect($this->adapter->mapStatus('SOMETHING_NEW'))->toBe(PaymentStatus::Pending)
            ->and($this->adapter->mapStatus(null))->toBe(PaymentStatus::Pending);
    });

    /*
     * The single most important assertion in this file. V2 spells success
     * `Succss` with one `e` and V3 spells it `SUCCESS`; MyFatoorah's own
     * library compares against the misspelling literally. This driver is V3
     * only, so `Succss` must NOT be honoured — accepting it would mean the
     * two vocabularies had been merged into one table, which is exactly the
     * mistake that starts dropping payments the day either side is
     * corrected. See pitfalls.md entries 1-2.
     */
    it('does not honour the V2 spelling Succss', function () {
        expect($this->adapter->mapStatus('Succss'))->toBe(PaymentStatus::Pending);
    });

    it('is case sensitive, so the lowercase V2 casing does not match', function () {
        expect($this->adapter->mapStatus('success'))->toBe(PaymentStatus::Pending)
            ->and($this->adapter->mapStatus('Paid'))->toBe(PaymentStatus::Pending);
    });
});

describe('fromWebhook', function () {
    it('maps a successful payment event', function () {
        $update = $this->adapter->fromWebhook(myfatoorahWebhook());

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->currency)->toBe('KWD')
            ->and($update->errorMessage)->toBeNull();
    });

    /*
     * The amount basis. ValueInDisplayCurrency is what we asked MyFatoorah to
     * collect — the same basis as transactions.requested_amount, which
     * WebhookProcessor compares this against. ValueInBaseCurrency is the
     * figure converted to the account's base currency; reporting it would put
     * deposits outside the tolerance band and hold every one for review. This
     * is the same class of bug the APS adapter documents for amount_in.
     */
    it('reports the display-currency amount, never the base-currency one', function () {
        $update = $this->adapter->fromWebhook(myfatoorahWebhook());

        expect($update->amount)->toBe(100.0)
            ->and($update->amount)->not->toBe(30.75);
    });

    it('leaves the amount null when the display value is absent', function () {
        $payload = myfatoorahWebhook();
        unset($payload['Data']['Amount']['ValueInDisplayCurrency']);

        expect($this->adapter->fromWebhook($payload)->amount)->toBeNull();
    });

    it('maps a failed payment event and carries the error message', function () {
        $payload = myfatoorahWebhook('FAILED', 'PENDING');
        $payload['Data']['Transaction']['Error'] = ['Code' => '1201', 'Message' => 'Insufficient funds'];

        $update = $this->adapter->fromWebhook($payload);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('Insufficient funds')
            ->and($update->errorCode)->toBe('1201');
    });

    /*
     * WebhookProcessor writes error_message on Canceled as well as Failed.
     * Restricting the message to Failed leaves the customer and support
     * staring at a bare "Canceled" with the reason unread in metadata.
     */
    it('carries the error message on Canceled too', function () {
        $payload = myfatoorahWebhook('CANCELED', 'CANCELED');
        $payload['Data']['Transaction']['Error'] = ['Code' => '', 'Message' => 'Cancelled by customer'];

        $update = $this->adapter->fromWebhook($payload);

        expect($update->status)->toBe(PaymentStatus::Canceled)
            ->and($update->errorMessage)->toBe('Cancelled by customer');
    });

    it('does not invent an error message when the Error object is empty', function () {
        expect($this->adapter->fromWebhook(myfatoorahWebhook('FAILED'))->errorMessage)->toBeNull();
    });

    it('records the correlation and reconciliation fields in metadata', function () {
        $metadata = $this->adapter->fromWebhook(myfatoorahWebhook())->metadata;

        expect($metadata['myfatoorah_invoice_id'])->toBe('6409988')
            ->and($metadata['myfatoorah_payment_id'])->toBe('07076409988323998875')
            ->and($metadata['myfatoorah_transaction_status'])->toBe('SUCCESS')
            ->and($metadata['myfatoorah_invoice_status'])->toBe('PAID')
            ->and($metadata['myfatoorah_receivable_amount'])->toBe('30.50')
            ->and($metadata['myfatoorah_event_reference'])->toBe('WH-626519');
    });

    it('keeps the whole body as the processor response', function () {
        expect($this->adapter->fromWebhook(myfatoorahWebhook())->processorResponse)
            ->toBe(myfatoorahWebhook());
    });

    it('builds a card snapshot from the transaction card', function () {
        $snapshot = $this->adapter->fromWebhook(myfatoorahWebhook())->paymentMethodSnapshot;

        expect($snapshot)->not->toBeNull()
            ->and($snapshot->brand)->toBe(PaymentMethodBrand::Mastercard)
            ->and($snapshot->lastFour)->toBe('0008');
    });

    it('maps a KNET payment to the Knet brand as a debit card', function () {
        $payload = myfatoorahWebhook();
        $payload['Data']['Transaction']['PaymentMethod'] = 'KNET';
        $payload['Data']['Transaction']['Card'] = ['Number' => '', 'Brand' => 'KNET'];

        $snapshot = $this->adapter->fromWebhook($payload)->paymentMethodSnapshot;

        expect($snapshot->brand)->toBe(PaymentMethodBrand::Knet)
            ->and($snapshot->type)->toBe(PaymentMethodType::DebitCard)
            // Not "KNET •••• " — there is no card number on a KNET payment.
            ->and($snapshot->displayName)->toBe('KNET');
    });

    it('omits the snapshot when there is no card object', function () {
        $payload = myfatoorahWebhook();
        unset($payload['Data']['Transaction']['Card']);

        expect($this->adapter->fromWebhook($payload)->paymentMethodSnapshot)->toBeNull();
    });
});

describe('fromProviderResponse', function () {
    it('maps a redirect-flow create response to a pending result carrying the payment URL', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6309730',
            'PaymentId' => null,
            'PaymentURL' => 'https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae',
            'PaymentCompleted' => false,
            'TransactionDetails' => null,
            'amount' => 100.0,
            'currency' => 'KWD',
        ]);

        expect($result->success)->toBeTrue()
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->transactionId)->toBe('6309730')
            ->and($result->currency)->toBe('KWD')
            ->and($result->getRedirectUrl())->toBe('https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae')
            ->and($result->requiresAction())->toBeTrue();
    });

    /*
     * The correlation key is InvoiceId, not PaymentId: PaymentId is null on
     * every redirect-flow create response, so it cannot be what
     * provider_transaction_id is set from.
     */
    it('uses InvoiceId as the transaction id even when PaymentId is present', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322611',
            'PaymentId' => '07076322611317711671',
            'PaymentURL' => 'https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076322611317711671',
            'PaymentCompleted' => false,
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->transactionId)->toBe('6322611');
    });

    it('maps the non-3DS completed case straight to Succeeded', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322611',
            'PaymentId' => '07076322611317711671',
            'PaymentURL' => 'https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076322611317711671',
            'PaymentCompleted' => true,
            'TransactionDetails' => [
                'Invoice' => ['Id' => '6322611', 'Status' => 'PAID'],
                'Transaction' => [
                    'Status' => 'SUCCESS',
                    'PaymentId' => '07076322611317711671',
                    'Card' => ['Number' => '512345xxxxxx0008', 'Brand' => 'Mastercard'],
                ],
            ],
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->success)->toBeTrue()
            ->and($result->paymentMethodSnapshot?->brand)->toBe(PaymentMethodBrand::Mastercard);
    });

    it('reports a completed-but-failed create as unsuccessful', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322612',
            'PaymentURL' => 'https://demo.MyFatoorah.com/result',
            'PaymentCompleted' => true,
            'TransactionDetails' => ['Transaction' => ['Status' => 'FAILED', 'Error' => ['Message' => 'Do not honour']]],
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->success)->toBeFalse()
            ->and($result->message)->toBe('Do not honour');
    });
});

describe('fromProviderPayload', function () {
    /*
     * An invoice holds an ARRAY of transactions, one per attempt, and
     * Invoice.Status does not track them. Scan for any SUCCESS first: if one
     * exists the invoice is paid whatever the other entries say. See
     * pitfalls.md entry 7.
     */
    it('finds a success among earlier failures whatever their order', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
            'Transactions' => [
                ['Id' => '1', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:00:00Z', 'Error' => ['Message' => 'Declined']],
                ['Id' => '2', 'Status' => 'SUCCESS', 'TransactionDate' => '2026-03-01T08:03:54Z', 'PaymentId' => 'PID-2'],
                ['Id' => '3', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:05:00Z', 'Error' => ['Message' => 'Declined']],
            ],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->transactionId)->toBe('6551972')
            ->and($result->metadata['myfatoorah_payment_id'])->toBe('PID-2');
    });

    it('falls back to the latest attempt by TransactionDate when none succeeded', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PENDING'],
            'Transactions' => [
                ['Id' => '1', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:00:00Z', 'Error' => ['Message' => 'First decline']],
                ['Id' => '2', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:09:00Z', 'Error' => ['Message' => 'Last decline']],
            ],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->message)->toBe('Last decline');
    });

    it('falls back to the invoice status when there are no transactions at all', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PENDING'],
            'Transactions' => [],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Pending);
    });

    it('reads the display-currency amount and currency', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
            'Transactions' => [['Id' => '1', 'Status' => 'SUCCESS', 'TransactionDate' => '2026-03-01T08:03:54Z']],
            'Amount' => ['BaseCurrency' => 'KWD', 'ValueInBaseCurrency' => '30.75', 'DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '100'],
        ]);

        expect($result->amount)->toBe(100)
            ->and($result->currency)->toBe('KWD');
    });
});

describe('getProviderName', function () {
    it('is myfatoorah', function () {
        expect($this->adapter->getProviderName())->toBe('myfatoorah');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahAdapter`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahAdapter" not found`

- [ ] **Step 3: Add the Knet brand case**

Modify `src/Enums/PaymentMethodBrand.php`. Three edits — `label()` and `getType()` are exhaustive `match ($this)` expressions, so omitting either is an `UnhandledMatchError` at runtime, not a missed case at compile time. `requiresLastFour()` and `getIcon()` both have `default` arms and need no change.

Add the case under the "Regional Payment Methods" block, after `case ValU = 'valu';`:

```php
    // KNET is Kuwait's national debit network, and MyFatoorah's most-used
    // method in that market. Without its own case it lands on Other, which
    // renders as "Other" in every admin panel and payment-method picker.
    case Knet = 'knet';
```

Add to `label()`, in the same "Regional Payment Methods" block after `self::ValU => 'valU',`:

```php
            self::Knet => 'KNET',
```

Add to `getType()`. KNET is a debit network, not a credit-card brand and not a wallet, so it goes on its own arm rather than joining either existing group — add immediately after the `CreditCard` arm:

```php
            self::Knet => PaymentMethodType::DebitCard,
```

- [ ] **Step 4: Write the adapter**

Create `src/Drivers/Myfatoorah/MyfatoorahAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentStatus;

class MyfatoorahAdapter implements PaymentAdapterInterface
{
    /**
     * Transform a V3 create-payment `Data` object into a PaymentResult.
     *
     * The provider merges `amount` and `currency` into the array first, since
     * MyFatoorah does not echo the order figures on this response.
     *
     * `PaymentCompleted` is true only for non-3DS payments, where the outcome
     * is already decided and sits in `TransactionDetails`. Every redirect flow
     * comes back false with a `PaymentURL` to send the customer to.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        $completed = ($response['PaymentCompleted'] ?? false) === true;
        $transaction = $completed ? (array) data_get($response, 'TransactionDetails.Transaction', []) : [];

        $status = $completed
            ? $this->mapStatus($transaction['Status'] ?? null)
            : PaymentStatus::Pending;

        return new PaymentResult(
            success: $status !== PaymentStatus::Failed,
            // InvoiceId, never PaymentId: PaymentId is null on every
            // redirect-flow create response, so it cannot be the key
            // `provider_transaction_id` is set from or webhooks correlate on.
            transactionId: (string) $response['InvoiceId'],
            status: $status,
            amount: (int) round((float) ($response['amount'] ?? 0)),
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $this->errorMessage($transaction),
            metadata: array_filter([
                'redirect_url' => $response['PaymentURL'] ?? null,
                'myfatoorah_invoice_id' => $response['InvoiceId'] ?? null,
                'myfatoorah_payment_id' => $response['PaymentId'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
            paymentMethodSnapshot: $this->snapshot($transaction),
        );
    }

    /**
     * Transform a V3 invoice `Data` object (retrieve/sync) into a
     * PaymentResult.
     *
     * An invoice holds an ARRAY of transactions, one per attempt, and
     * `Invoice.Status` does not track them — see pitfalls.md entry 7. So the
     * outcome is decided by scanning, not by reading one field.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $transaction = $this->decisiveTransaction((array) ($payload['Transactions'] ?? []));

        $status = $transaction === null
            ? $this->mapStatus(data_get($payload, 'Invoice.Status'))
            : $this->mapStatus($transaction['Status'] ?? null);

        $amount = data_get($payload, 'Amount.ValueInDisplayCurrency');

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: (int) round((float) ($amount ?? 0)),
            currency: (string) (data_get($payload, 'Amount.DisplayCurrency')
                ?? config('cashier-core.currency.default', 'USD')),
            message: $this->errorMessage($transaction ?? []),
            metadata: $this->metadata(
                (array) ($payload['Invoice'] ?? []),
                $transaction ?? [],
                (array) ($payload['Amount'] ?? []),
            ),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($transaction ?? []),
        );
    }

    /**
     * Transform a V2 `PAYMENT_STATUS_CHANGED` webhook body into a
     * TransactionWebhookUpdate. Takes the WHOLE body — `Event` and `Data`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $data = (array) ($payload['Data'] ?? []);
        $invoice = (array) ($data['Invoice'] ?? []);
        $transaction = (array) ($data['Transaction'] ?? []);
        $amount = (array) ($data['Amount'] ?? []);

        $status = $this->mapStatus($transaction['Status'] ?? null);

        $metadata = $this->metadata($invoice, $transaction, $amount);
        $metadata['myfatoorah_event_reference'] = data_get($payload, 'Event.Reference');

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($transaction),
            metadata: array_filter($metadata, fn ($value) => $value !== null),
            errorCode: $this->errorCode($transaction),
            // Carried on Canceled as well as Failed: WebhookProcessor writes
            // error_message on both, and restricting this to Failed leaves the
            // customer and support staring at a bare "Canceled" with the
            // reason unread in metadata.
            errorMessage: in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)
                ? $this->errorMessage($transaction)
                : null,
            // The DISPLAY-currency figure. This is what we asked MyFatoorah to
            // collect and therefore the same basis as
            // `transactions.requested_amount`, which WebhookProcessor compares
            // it against. `ValueInBaseCurrency` is the amount converted to the
            // account's base currency; reporting it would put every deposit
            // outside the tolerance band and hold it for review — the same
            // class of bug the APS adapter documents for `amount_in`.
            //
            // Left null when absent: the guard skips a null amount, and
            // falling back to the base-currency value would reinstate the bug.
            amount: isset($amount['ValueInDisplayCurrency'])
                ? (float) $amount['ValueInDisplayCurrency']
                : null,
            currency: isset($amount['DisplayCurrency']) ? (string) $amount['DisplayCurrency'] : null,
        );
    }

    /**
     * Map a MyFatoorah V3 status to a cashier-core PaymentStatus.
     *
     * V3 uses two vocabularies and they overlap without colliding:
     *
     * - Transaction: INPROGRESS, SUCCESS, FAILED, CANCELED, AUTHORIZE
     * - Invoice:     PENDING, PAID, CANCELED
     *
     * DELIBERATELY CASE SENSITIVE, and deliberately not merged with V2. V2
     * spells the same values in mixed case and spells success `Succss` with
     * one `e` — MyFatoorah's own library compares against that string
     * literally. A case-insensitive table hides that divergence and starts
     * silently dropping payments the day either side is corrected. When V2
     * support is added, populate the table below rather than loosening this
     * one. See pitfalls.md entries 1-2.
     *
     *   V2, for the future, NOT handled here:
     *   InProgress | Succss | Failed | Canceled | Authorize
     *   Pending | Paid | Canceled
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match ((string) $providerStatus) {
            'SUCCESS', 'PAID' => PaymentStatus::Succeeded,
            'FAILED' => PaymentStatus::Failed,
            // Not a refunded state: this package has no Refunded payment
            // status, and refund and void outcomes map to Canceled throughout.
            'CANCELED' => PaymentStatus::Canceled,
            'INPROGRESS' => PaymentStatus::Processing,
            'AUTHORIZE' => PaymentStatus::RequiresCapture,
            'PENDING' => PaymentStatus::Pending,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'myfatoorah';
    }

    /**
     * The transaction that decides the invoice's outcome.
     *
     * Any SUCCESS wins outright, whatever the other entries say and whatever
     * order they arrive in. Otherwise the latest attempt by TransactionDate
     * carries the reason the customer needs to see. Null when the invoice has
     * no attempts at all.
     *
     * @param  list<array<string, mixed>>  $transactions
     * @return array<string, mixed>|null
     */
    private function decisiveTransaction(array $transactions): ?array
    {
        if ($transactions === []) {
            return null;
        }

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && ($transaction['Status'] ?? null) === 'SUCCESS') {
                return $transaction;
            }
        }

        $latest = null;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            if ($latest === null
                || (string) ($transaction['TransactionDate'] ?? '') > (string) ($latest['TransactionDate'] ?? '')) {
                $latest = $transaction;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>  $amount
     * @return array<string, mixed>
     */
    private function metadata(array $invoice, array $transaction, array $amount): array
    {
        return array_filter([
            'myfatoorah_invoice_id' => $invoice['Id'] ?? null,
            'myfatoorah_invoice_status' => $invoice['Status'] ?? null,
            'myfatoorah_external_identifier' => $invoice['ExternalIdentifier'] ?? null,
            'myfatoorah_transaction_id' => $transaction['Id'] ?? null,
            'myfatoorah_transaction_status' => $transaction['Status'] ?? null,
            'myfatoorah_payment_id' => $transaction['PaymentId'] ?? null,
            'myfatoorah_payment_method' => $transaction['PaymentMethod'] ?? null,
            // The merchant settlement, which reaches the fee-drift check
            // through metadata rather than through the reported amount.
            'myfatoorah_receivable_amount' => $amount['ReceivableAmount'] ?? null,
            'myfatoorah_service_charge' => $amount['ServiceCharge'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * MyFatoorah sends `Error: {Code: "", Message: ""}` on success, so an
     * empty string means "no error" rather than "an error with no message".
     *
     * @param  array<string, mixed>  $transaction
     */
    private function errorMessage(array $transaction): ?string
    {
        $message = trim((string) data_get($transaction, 'Error.Message', ''));

        return $message !== '' ? $message : null;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function errorCode(array $transaction): ?string
    {
        $code = trim((string) data_get($transaction, 'Error.Code', ''));

        return $code !== '' ? $code : null;
    }

    /**
     * Brand and last four from the transaction's card object.
     *
     * PayloadSanitizer strips the card-shaped keys before anything is
     * persisted, so the brand and last four kept here are the only card facts
     * that survive — which is exactly what the payment_method_* columns are
     * for, and leaves the SAQ-A posture unchanged.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function snapshot(array $transaction): ?PaymentMethodSnapshot
    {
        $card = $transaction['Card'] ?? null;

        if (! is_array($card) || $card === []) {
            return null;
        }

        $brand = trim((string) ($card['Brand'] ?? ''));

        if ($brand === '') {
            return null;
        }

        // "512345xxxxxx0008" — the last four are the only digits we keep.
        $number = preg_replace('/\D/', '', (string) ($card['Number'] ?? '')) ?? '';
        $lastFour = strlen($number) >= 4 ? substr($number, -4) : '';

        $brandValue = str_replace(' ', '_', strtolower($brand));

        return PaymentMethodSnapshot::fromCardData(
            brand: $brandValue,
            lastFour: $lastFour,
            // fromCardData's default display name assumes a card number
            // ("Mastercard •••• 0008"). KNET and the wallet rails arrive with
            // no number at all, and "KNET •••• " reads as a bug in every
            // admin panel it is rendered in.
            displayName: $lastFour === ''
                ? (PaymentMethodBrand::tryFrom($brandValue) ?? PaymentMethodBrand::Other)->label()
                : null,
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=MyfatoorahAdapter`
Expected: PASS — 24 tests

Then confirm the enum change broke nothing:

Run: `vendor/bin/pest`
Expected: PASS — the whole suite, no new failures

- [ ] **Step 6: Commit**

```bash
git add src/Enums/PaymentMethodBrand.php src/Drivers/Myfatoorah/MyfatoorahAdapter.php tests/Unit/Drivers/MyfatoorahAdapterTest.php
git commit -m "feat(myfatoorah): add the payload adapter and a KNET brand

Case-sensitive V3 status table, kept separate from V2 so the Succss/SUCCESS
divergence cannot be papered over. Webhook amount reads the display-currency
value, the basis requested_amount is compared against — the base-currency
figure would hold every deposit for review. Invoice sync scans the
transaction array for any success rather than trusting InvoiceStatus.

KNET is Kuwait's debit network and MyFatoorah's most-used method there;
without its own brand case it renders as Other everywhere.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: MyfatoorahProvider

The driver proper.

**Files:**
- Create: `src/Drivers/Myfatoorah/MyfatoorahProvider.php`
- Test: `tests/Unit/Drivers/MyfatoorahProviderTest.php`

**Interfaces:**
- Consumes: `MyfatoorahClient` (Task 2), `MyfatoorahAdapter` (Task 3), `MyfatoorahSignatureService` (Task 1).
- Produces:
  - `new MyfatoorahProvider(array $config = [])` — throws `PaymentProcessingException` when `base_url`, `api_key` or `currency` is missing.
  - `charge(array $data): PaymentResult`
  - `prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array`
  - `retrieve(string $transactionId): ?PaymentResult`
  - `getPaymentStatus(string $transactionId): string`
  - `parseWebhook(array $payload): TransactionWebhookUpdate`
  - `verifyWebhookSignature(array $payload, string $signature): bool` — takes the whole body, reads `Event.Code` itself
  - `extractWebhookTransactionId(array $payload): ?string`
  - `getName(): string`, `supports(string $feature): bool`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/MyfatoorahProviderTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Http;

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

    it('returns null for an invoice MyFatoorah does not know', function () {
        Http::fake(['*' => Http::response(['IsSuccess' => false, 'Message' => 'No invoices match this InvoiceId'])]);

        expect((new MyfatoorahProvider(myfatoorahConfig()))->retrieve('404404'))->toBeNull();
    });

    it('reports the raw provider status string', function () {
        expect((new MyfatoorahProvider(myfatoorahConfig()))->getPaymentStatus('6551972'))->toBe('SUCCESS');
    });

    it('reports unknown when the invoice cannot be read', function () {
        Http::fake(['*' => Http::response(['IsSuccess' => false, 'Message' => 'No invoices match this InvoiceId'])]);

        expect((new MyfatoorahProvider(myfatoorahConfig()))->getPaymentStatus('404404'))->toBe('unknown');
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahProvider`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Myfatoorah/MyfatoorahProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * MyFatoorah, on the V3 API.
 *
 * One instance serves one MyFatoorah account, which means one country:
 * MyFatoorah issues a separate API key per country and each key must be sent
 * to that country's host. A merchant trading in two countries is two
 * connections on this driver, exactly as a second APS account is.
 *
 * Deposits, webhooks and sync only. Refunds are deliberately absent rather
 * than forgotten — a MyFatoorah refund is a request a human approves, takes
 * no currency, and has no server-side duplicate guard, so it does not map
 * onto processRefund()'s synchronous contract without a design of its own.
 */
class MyfatoorahProvider implements PaymentProcessorInterface, PreparesChargeData, ProvidesWebhookTransactionId
{
    /**
     * The eight currencies MyFatoorah's Order.Currency accepts. USD is not
     * among them, which is why this driver overrides the currency
     * PaymentService pins.
     */
    private const CURRENCIES = ['SAR', 'BHD', 'AED', 'QAR', 'OMR', 'KWD', 'JOD', 'EGP'];

    private MyfatoorahClient $client;

    private MyfatoorahAdapter $adapter;

    private MyfatoorahSignatureService $signatures;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['base_url']) || empty($config['api_key'])) {
            throw new PaymentProcessingException('MyFatoorah provider is not configured.');
        }

        $currency = strtoupper((string) ($config['currency'] ?? ''));

        if ($currency === '') {
            throw new PaymentProcessingException('MyFatoorah connection has no currency configured.');
        }

        // Fail at resolve time rather than as an opaque ValidationError on the
        // first live charge.
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new PaymentProcessingException(
                "MyFatoorah cannot charge in {$currency}; it accepts ".implode(', ', self::CURRENCIES).'.'
            );
        }

        $this->client = new MyfatoorahClient(
            baseUrl: rtrim((string) $config['base_url'], '/'),
            apiKey: (string) $config['api_key'],
        );

        $this->adapter = new MyfatoorahAdapter;
        $this->signatures = new MyfatoorahSignatureService((string) ($config['webhook_secret'] ?? ''));
    }

    /**
     * PaymentService pins every charge to `cashier-core.currency.default` and
     * forbids callers choosing one; this hook is the documented escape for a
     * driver that charges in a fixed currency. MyFatoorah cannot charge USD,
     * so without this every charge is rejected.
     *
     * The customer name rides along too: PaymentService merges `user_id` and
     * `user_email` into metadata but not the name, and MyFatoorah shows
     * `Customer.Name` on the hosted page.
     *
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        $paymentData['currency'] = $this->currency();

        $paymentData['metadata'] = array_merge($paymentData['metadata'] ?? [], [
            'user_name' => $customer->cashierName(),
            'user_locale' => $customer->cashierLocale(),
        ]);

        return $paymentData;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $externalId = $data['external_id'] ?? 'DEP-'.Str::ulid();

        $payload = array_filter([
            // Omitted, not nulled: leaving the key out is what lands the
            // customer on MyFatoorah's own picker showing every method
            // enabled on the account.
            'PaymentMethod' => $this->config['payment_method'] ?? null,
            'Order' => [
                // The exact figure we were handed. transactions.amount is
                // decimal(16,2) so this is already two-decimal; no scaling,
                // no minor units.
                'Amount' => (float) $validated['amount'],
                'Currency' => $this->currency(),
                'ExternalIdentifier' => $externalId,
            ],
            'Customer' => array_filter([
                'Name' => $data['metadata']['user_name'] ?? null,
                'Email' => $data['metadata']['user_email'] ?? null,
                'Reference' => isset($data['metadata']['user_id'])
                    ? (string) $data['metadata']['user_id']
                    : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'IntegrationUrls' => array_filter([
                'Redirection' => $this->config['redirect_url']
                    ?? (Route::has('payment.success') ? route('payment.success') : null),
                'Webhook' => $this->config['webhook_url']
                    ?? (Route::has('cashier.webhooks.myfatoorah') ? route('cashier.webhooks.myfatoorah') : null),
            ], fn ($value) => $value !== null),
            'Language' => $this->language($data),
            'IpAddress' => $data['metadata']['ip_address'] ?? request()->ip(),
            'MetaData' => ['UDF1' => $externalId],
        ], fn ($value) => $value !== null && $value !== []);

        try {
            // The idempotency key carries the same ULID as
            // Order.ExternalIdentifier, so a retry is recognisable from either
            // end. It is the only protection against a double charge.
            $response = $this->client->createPayment($payload, $externalId);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout
            // or DNS failure is a ConnectionException — a sibling, not a
            // subclass — and would otherwise escape as an uncaught 500 with
            // nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('myfatoorah', $status, $e->getMessage());

            throw new PaymentProcessingException('MyFatoorah payment could not be created.');
        }

        $result = $this->adapter->fromProviderResponse($response + [
            'amount' => (float) $validated['amount'],
            'currency' => $this->currency(),
        ]);

        return new PaymentResult(
            success: $result->success,
            transactionId: $result->transactionId,
            status: $result->status,
            amount: (int) $validated['amount'],
            currency: $this->currency(),
            message: $result->message,
            metadata: array_merge($result->metadata ?? [], ['myfatoorah_external_id' => $externalId]),
            processorResponse: $result->processorResponse,
            paymentMethodSnapshot: $result->paymentMethodSnapshot,
        );
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support refunds.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support void.');
    }

    /**
     * The recovery path, and it matters more here than for most drivers:
     * MyFatoorah's V1 webhooks retry four times and then give up permanently,
     * and V2's are capped at five, so a delivery can be lost for good.
     */
    public function retrieve(string $transactionId): ?PaymentResult
    {
        $payload = $this->client->getInvoice($transactionId);

        if ($payload === null) {
            return null;
        }

        return $this->adapter->fromProviderPayload($transactionId, $payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $payload = $this->client->getInvoice($transactionId);

        if ($payload === null) {
            return 'unknown';
        }

        $transactions = (array) ($payload['Transactions'] ?? []);

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && ($transaction['Status'] ?? null) === 'SUCCESS') {
                return 'SUCCESS';
            }
        }

        return (string) (data_get($payload, 'Invoice.Status') ?? 'unknown');
    }

    public function validatePaymentData(array $data): array
    {
        return validator($data, [
            'amount' => 'required|numeric|min:0.01',
        ])->validate();
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    /**
     * Verify a webhook body against this account's secret.
     *
     * Takes the WHOLE body: the event code selects the field list, and the
     * signature is over a canonical string built from `Data`, never over the
     * raw body. Returns false for an event this driver does not implement,
     * so an unverifiable event can never be mistaken for a verified one.
     *
     * Every MyFatoorah account posts to the same callback URL, so identifying
     * the sender means trying each account's secret in turn — a match is
     * itself the proof of which account sent it. That loop lives in
     * MyfatoorahWebhookController.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $eventCode = (int) data_get($payload, 'Event.Code', 0);

        if (! $this->signatures->supports($eventCode)) {
            return false;
        }

        return $this->signatures->verify($eventCode, (array) ($payload['Data'] ?? []), $signature);
    }

    /**
     * The correlation key is the invoice id. See MyfatoorahAdapter for why it
     * is not the payment id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookTransactionId(array $payload): ?string
    {
        $invoiceId = data_get($payload, 'Data.Invoice.Id');

        return $invoiceId !== null ? (string) $invoiceId : null;
    }

    public function getName(): string
    {
        return 'myfatoorah';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    private function currency(): string
    {
        return strtoupper((string) $this->config['currency']);
    }

    /**
     * MyFatoorah accepts EN or AR. An explicit connection setting wins;
     * otherwise the customer's locale decides, which prepareChargeData()
     * put in metadata.
     *
     * @param  array<string, mixed>  $data
     */
    private function language(array $data): string
    {
        $configured = $this->config['language'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return strtoupper($configured);
        }

        $locale = strtolower((string) ($data['metadata']['user_locale'] ?? ''));

        return str_starts_with($locale, 'ar') ? 'AR' : 'EN';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=MyfatoorahProvider`
Expected: PASS — 27 tests

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Myfatoorah/MyfatoorahProvider.php tests/Unit/Drivers/MyfatoorahProviderTest.php
git commit -m "feat(myfatoorah): add the provider

Hosted-redirect charge on POST /v3/payments, invoice-keyed retrieve, and
the two webhook seams. prepareChargeData overrides the USD that
PaymentService pins, because MyFatoorah's Order.Currency accepts eight GCC
currencies and USD is not one — a mismatched currency is refused at
construction rather than as an opaque ValidationError on the first charge.
PaymentMethod is omitted rather than nulled when unset, which is what
yields MyFatoorah's own method picker.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Registration, config and testing seams

Makes the driver resolvable through `ConnectionRegistry` and simulatable in tests. No controller yet.

**Files:**
- Modify: `src/CashierCoreServiceProvider.php` — `BUNDLED_DRIVERS`
- Modify: `config/cashier-core.php` — connection example in the `connections` docblock
- Modify: `src/Cashier.php` — `fakeConnection()` defaults
- Modify: `src/Testing/WebhookSimulator.php` — signing recipe
- Test: `tests/Unit/Drivers/MyfatoorahConnectionTest.php`

**Interfaces:**
- Consumes: `MyfatoorahProvider` (Task 4), `MyfatoorahSignatureService` (Task 1).
- Produces:
  - `Cashier::fakeConnection('myfatoorah')` yields a resolvable connection; prefix inference makes `fakeConnection('myfatoorah_sau')` a second account.
  - `WebhookSimulator::make('myfatoorah', $payload, connection: ?string)` returns a `SignedWebhook` with `format` `'json'` and headers `MyFatoorah-Signature` and `MyFatoorah-Webhook-Version: v2`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/MyfatoorahConnectionTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Testing\WebhookSimulator;

it('registers myfatoorah as a bundled driver', function () {
    expect(config('cashier-core.drivers.myfatoorah'))->toBe(MyfatoorahProvider::class);
});

it('resolves a faked connection to a provider', function () {
    Cashier::fakeConnection('myfatoorah');

    $provider = app(ConnectionRegistry::class)->get('myfatoorah');

    expect($provider)->toBeInstanceOf(MyfatoorahProvider::class)
        ->and($provider->getName())->toBe('myfatoorah');
});

/*
 * MyFatoorah issues one API key per country and each key must reach that
 * country's host, so a merchant trading in two countries is two connections
 * on one driver — the same shape a second APS account has.
 */
it('supports a second country as a second connection on the same driver', function () {
    Cashier::fakeConnection('myfatoorah');
    Cashier::fakeConnection('myfatoorah_sau', [
        'base_url' => 'https://apisa.myfatoorah.com',
        'api_key' => 'sau-key',
        'webhook_secret' => 'sau-secret',
        'currency' => 'SAR',
    ]);

    expect(Connections::forDriver('myfatoorah'))->toBe(['myfatoorah', 'myfatoorah_sau'])
        ->and(Connections::driverFor('myfatoorah_sau'))->toBe('myfatoorah');

    expect(app(ConnectionRegistry::class)->get('myfatoorah_sau'))->toBeInstanceOf(MyfatoorahProvider::class);
});

describe('WebhookSimulator', function () {
    it('signs a delivery the provider verifies', function () {
        Cashier::fakeConnection('myfatoorah');

        $payload = [
            'Event' => ['Code' => 1, 'Name' => 'PAYMENT_STATUS_CHANGED', 'Reference' => 'WH-1'],
            'Data' => [
                'Invoice' => ['Id' => '6409988', 'Status' => 'PAID', 'ExternalIdentifier' => 'DEP-1'],
                'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'PID-1'],
            ],
        ];

        $delivery = WebhookSimulator::make('myfatoorah', $payload);

        expect($delivery->uri)->toBe('/api/webhooks/myfatoorah')
            ->and($delivery->isJson())->toBeTrue()
            ->and($delivery->headers['MyFatoorah-Webhook-Version'])->toBe('v2');

        $provider = app(ConnectionRegistry::class)->get('myfatoorah');

        expect($provider->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeTrue();
    });

    it('signs with the named connection secret, not the default one', function () {
        Cashier::fakeConnection('myfatoorah');
        Cashier::fakeConnection('myfatoorah_sau', [
            'base_url' => 'https://apisa.myfatoorah.com',
            'api_key' => 'sau-key',
            'webhook_secret' => 'sau-secret',
            'currency' => 'SAR',
        ]);

        $payload = [
            'Event' => ['Code' => 1],
            'Data' => [
                'Invoice' => ['Id' => '1', 'Status' => 'PAID', 'ExternalIdentifier' => ''],
                'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'P'],
            ],
        ];

        $delivery = WebhookSimulator::make('myfatoorah', $payload, connection: 'myfatoorah_sau');
        $registry = app(ConnectionRegistry::class);

        expect($registry->get('myfatoorah_sau')->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeTrue()
            ->and($registry->get('myfatoorah')->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeFalse();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahConnection`
Expected: FAIL — `config('cashier-core.drivers.myfatoorah')` is null

- [ ] **Step 3: Register the bundled driver**

Modify `src/CashierCoreServiceProvider.php`. In the `BUNDLED_DRIVERS` constant, add after the `sticpay` line:

```php
        'myfatoorah' => Drivers\Myfatoorah\MyfatoorahProvider::class,
```

- [ ] **Step 4: Add the fakeConnection defaults**

Modify `src/Cashier.php`. In `fakeConnection()`'s `match ($driver)`, add a `myfatoorah` arm after the `sticpay` arm and before `default`:

```php
            'myfatoorah' => [
                // Every country shares this sandbox; the live host is per
                // country and the API key belongs to exactly one of them.
                'base_url' => 'https://apitest.myfatoorah.com',
                'api_key' => "{$name}-api-key",
                'webhook_secret' => "{$name}-webhook-secret",
                'currency' => 'KWD',
                'redirect_url' => 'https://members.example.com/payment/success',
                'webhook_url' => 'https://members.example.com/api/webhooks/myfatoorah',
            ],
```

- [ ] **Step 5: Add the WebhookSimulator recipe**

Modify `src/Testing/WebhookSimulator.php`. Add the import beside the other driver imports:

```php
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;
```

Add an arm to the `match ($driver)` in `make()`, after the `'heropayment' =>` arm:

```php
            'myfatoorah' => self::myfatoorah($uri, $payload, $config),
```

Add the private helper after `jenapay()`:

```php
    /**
     * MyFatoorah signs a canonical field list, not the body, and carries the
     * version in a header the docs never mention — a controller that does not
     * read it cannot know which of the two signing rules applies.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function myfatoorah(string $uri, array $payload, array $config): SignedWebhook
    {
        $service = new MyfatoorahSignatureService((string) ($config['webhook_secret'] ?? ''));

        $signature = $service->sign(
            (int) data_get($payload, 'Event.Code', 0),
            (array) ($payload['Data'] ?? []),
        );

        return new SignedWebhook($uri, $payload, [
            'MyFatoorah-Signature' => $signature,
            'MyFatoorah-Webhook-Version' => 'v2',
        ], 'json');
    }
```

- [ ] **Step 6: Add the config example**

Modify `config/cashier-core.php`. Inside the `Named Connections` docblock, after the `aps_apple_pay` example and before the closing `*/`, add:

```
    | MyFatoorah is one API key per COUNTRY, and the key must be sent to that
    | country's host — api.myfatoorah.com serves Kuwait, Bahrain, Oman and
    | Jordan, while Saudi Arabia, the UAE, Qatar and Egypt each have their
    | own. Every country shares the apitest.myfatoorah.com sandbox. The
    | authoritative host map is a public JSON file,
    | https://portal.myfatoorah.com/Files/API/mf-config.json — read it to fill
    | `base_url` in, but the package does not fetch it.
    |
    | 'myfatoorah' => [
    |     'driver' => 'myfatoorah',
    |     'base_url' => env('MYFATOORAH_BASE_URL'),
    |     'api_key' => env('MYFATOORAH_API_KEY'),
    |     // Enabled per webhook in the portal as the "secure key", and
    |     // mandatory for V2 deliveries. Without it every callback is refused.
    |     'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
    |     // Required. MyFatoorah cannot charge USD: Order.Currency accepts
    |     // only SAR, BHD, AED, QAR, OMR, KWD, JOD and EGP. The driver
    |     // overrides the pinned default with this, and refuses to resolve
    |     // when it is missing or unsupported.
    |     'currency' => env('MYFATOORAH_CURRENCY', 'KWD'),
    |     // Optional. CARD | KNET | APPLE_PAY | GOOGLE_PAY. Omit to land the
    |     // customer on MyFatoorah's own picker showing every method enabled
    |     // on the account — the docs are ambiguous about whether the picker
    |     // is reachable for redirection flows, so confirm it in the sandbox
    |     // before leaving this unset in production.
    |     'payment_method' => env('MYFATOORAH_PAYMENT_METHOD'),
    |     // Optional — these fall back to the `payment.success` and
    |     // `cashier.webhooks.myfatoorah` routes where the host defines them.
    |     'redirect_url' => env('MYFATOORAH_REDIRECT_URL'),
    |     'webhook_url' => env('MYFATOORAH_WEBHOOK_URL'),
    |     // Optional. EN | AR. Defaults to the customer's cashierLocale().
    |     'language' => env('MYFATOORAH_LANGUAGE'),
    | ],
    |
    | A second country is another connection on the same driver:
    |
    | 'myfatoorah_sau' => [
    |     'driver' => 'myfatoorah',
    |     'base_url' => 'https://apisa.myfatoorah.com',
    |     'api_key' => env('MYFATOORAH_SAU_API_KEY'),
    |     'webhook_secret' => env('MYFATOORAH_SAU_WEBHOOK_SECRET'),
    |     'currency' => 'SAR',
    | ],
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=MyfatoorahConnection`
Expected: PASS — 5 tests

- [ ] **Step 8: Commit**

```bash
git add src/CashierCoreServiceProvider.php src/Cashier.php src/Testing/WebhookSimulator.php config/cashier-core.php tests/Unit/Drivers/MyfatoorahConnectionTest.php
git commit -m "feat(myfatoorah): register the driver and add the test seams

Bundled driver entry, fakeConnection defaults, and a WebhookSimulator
recipe that signs the canonical string and sets the undocumented version
header. The config example spells out why a second country is a second
connection: one API key per country, and the key must reach that
country's host.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Webhook controller and route

**Files:**
- Create: `src/Http/Controllers/Webhooks/MyfatoorahWebhookController.php`
- Modify: `routes/webhooks.php`
- Modify: `tests/Feature/Webhooks/RouteRegistrationTest.php:9`
- Test: `tests/Feature/Webhooks/MyfatoorahWebhookTest.php`

**Interfaces:**
- Consumes: `MyfatoorahProvider` (Task 4), the test seams (Task 5).
- Produces: route named `cashier.webhooks.myfatoorah` at `POST api/webhooks/myfatoorah`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Webhooks/MyfatoorahWebhookTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Asciisd\CashierCore\Testing\WebhookSimulator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cashier::fakeConnection('myfatoorah');

    Cashier::fakeConnection('myfatoorah_sau', [
        'base_url' => 'https://apisa.myfatoorah.com',
        'api_key' => 'sau-key',
        'webhook_secret' => 'sau-webhook-secret',
        'currency' => 'SAR',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function myfatoorahEvent(string $invoiceId = '6409988', string $status = 'SUCCESS', int $code = 1): array
{
    return [
        'Event' => [
            'Code' => $code,
            'Name' => 'PAYMENT_STATUS_CHANGED',
            'CountryIsoCode' => 'KWT',
            'CreationDate' => '2026-01-04T08:15:00.9500000Z',
            'Reference' => 'WH-626519',
        ],
        'Data' => [
            'Invoice' => [
                'Id' => $invoiceId,
                'Status' => $status === 'SUCCESS' ? 'PAID' : 'PENDING',
                'ExternalIdentifier' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            ],
            'Transaction' => [
                'Id' => '86781',
                'Status' => $status,
                'PaymentId' => '07076409988323998875',
                'PaymentMethod' => 'VISA/MASTER',
                'Error' => ['Code' => '', 'Message' => ''],
            ],
            'Amount' => [
                'BaseCurrency' => 'KWD',
                'ValueInBaseCurrency' => '100',
                'DisplayCurrency' => 'KWD',
                'ValueInDisplayCurrency' => '100',
            ],
        ],
    ];
}

it('accepts a correctly signed delivery and dispatches the job', function () {
    Event::fake([WebhookReceived::class]);

    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
    Event::assertDispatched(WebhookReceived::class);
});

it('threads the connection whose secret verified the delivery', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(), connection: 'myfatoorah_sau');

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, function ($job) {
        return $job->connectionName === 'myfatoorah_sau';
    });
});

it('rejects a delivery with an invalid signature', function () {
    Event::fake([WebhookRejected::class]);

    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => 'not-a-real-signature',
        'MyFatoorah-Webhook-Version' => 'v2',
    ])->assertForbidden();

    Queue::assertNothingPushed();
    Event::assertDispatched(WebhookRejected::class);
});

it('rejects a delivery signed with a secret belonging to no connection', function () {
    $payload = myfatoorahEvent();

    $signature = base64_encode(hash_hmac(
        'sha256',
        'Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07076409988323998875,Invoice.ExternalIdentifier=DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
        'a-secret-nobody-configured',
        true,
    ));

    $this->postJson('/api/webhooks/myfatoorah', $payload, [
        'MyFatoorah-Signature' => $signature,
        'MyFatoorah-Webhook-Version' => 'v2',
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a delivery whose signed field was tampered with after signing', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $tampered = $delivery->payload;
    $tampered['Data']['Transaction']['Status'] = 'SUCCESS';
    $tampered['Data']['Invoice']['Id'] = '9999999';

    $this->postJson($delivery->uri, $tampered, $delivery->headers)->assertForbidden();

    Queue::assertNothingPushed();
});

/*
 * The version header is undocumented but MyFatoorah's own library reads it and
 * refuses without it. Inferring the version from the payload shape is not an
 * option — V1 and V2 bodies are close enough to confuse, and the two signing
 * rules are incompatible.
 */
it('rejects a delivery carrying no version header', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => $delivery->headers['MyFatoorah-Signature'],
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a v1 delivery rather than verifying it with the v2 rule', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => $delivery->headers['MyFatoorah-Signature'],
        'MyFatoorah-Webhook-Version' => 'v1',
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

/*
 * A refund, deposit or supplier event. Acknowledged so MyFatoorah stops
 * retrying — a non-200 for a transient reason can lose an event permanently —
 * but nothing is dispatched and nothing is acted on.
 */
it('acknowledges an event it does not handle without dispatching anything', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(code: 2));

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertNothingPushed();
});

it('acknowledges a duplicate delivery once and dispatches only the first', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();
    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    expect(WebhookEvent::query()->where('provider', 'myfatoorah')->count())->toBe(1);
});

it('accepts a failed payment event', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(status: 'FAILED'));

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=MyfatoorahWebhook`
Expected: FAIL — 404, the route does not exist

- [ ] **Step 3: Write the controller**

Create `src/Http/Controllers/Webhooks/MyfatoorahWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class MyfatoorahWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'myfatoorah';

    /**
     * The only webhook version this driver accepts. V1 signs the whole `Data`
     * object with its keys sorted case-insensitively; V2 signs a fixed
     * per-event field list. The rules are incompatible, and a webhook
     * configured fresh in the portal is V2.
     */
    private const WEBHOOK_VERSION = 'v2';

    public function __invoke(
        Request $request,
        ConnectionRegistry $registry,
        ReplayGuard $replayGuard,
    ): JsonResponse {
        $payload = $request->json()->all();

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            // The header MyFatoorah does not document. Its own library reads
            // it and refuses without it. The version cannot be inferred from
            // the payload shape — V1 (`EventType` + `Event` + `Data`) and V2
            // (`Event.Code` + `Event.Name` + `Data`) are close enough to
            // confuse, and guessing wrong produces a signature failure that
            // says nothing about why.
            $version = strtolower((string) $request->header('MyFatoorah-Webhook-Version', ''));

            if ($version !== self::WEBHOOK_VERSION) {
                Log::critical('cashier-core: refusing a MyFatoorah webhook of an unsupported version', [
                    'driver' => self::DRIVER,
                    'version' => $version !== '' ? $version : '(absent)',
                ]);

                WebhookRejected::dispatch(self::DRIVER, 'unsupported webhook version', $request->ip());

                return response()->json(['error' => 'Unsupported webhook version'], 403);
            }
        }

        $eventCode = (int) data_get($payload, 'Event.Code', 0);

        // Refund, deposit, supplier, recurring, dispute and supplier-update
        // events all land here. ACK so MyFatoorah stops retrying — a non-200
        // for a transient reason can lose an event permanently, and
        // GetWebhooks is the only way back — but dispatch nothing.
        //
        // This happens BEFORE verification deliberately: verifying an event we
        // then discard would mean carrying six more field lists to reach the
        // same outcome, and an unverified payload that is never acted on
        // cannot do harm.
        if ($eventCode !== MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED) {
            Log::info('cashier-core: ignoring an unhandled MyFatoorah webhook event', [
                'driver' => self::DRIVER,
                'event_code' => $eventCode,
                'event_name' => data_get($payload, 'Event.Name'),
            ]);

            return response()->json(['status' => 'ok']);
        }

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) $request->header('MyFatoorah-Signature', '');

            $matchedConnection = $this->connectionThatSigned($payload, $signature, $registry);

            if ($matchedConnection === null) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $signature);

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, $matchedConnection)) {
                // Duplicate delivery, and MyFatoorah says these are not rare —
                // some methods send several webhooks for one transaction and
                // MyFatoorah forwards them all. ACK so it stops, process
                // nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose MyFatoorah account signed this delivery, or null
     * when none did.
     *
     * Every account posts to this one URL and the payload names no merchant,
     * so each account's webhook secret is tried in turn — a match is itself
     * the proof of which account sent it. The docs never say whether one
     * account with several webhook endpoints gets one secret or several;
     * this is correct either way, and is required regardless the moment a
     * second country's connection exists.
     *
     * @param  array<string, mixed>  $payload
     */
    private function connectionThatSigned(
        array $payload,
        string $signature,
        ConnectionRegistry $registry
    ): ?string {
        foreach (Connections::forDriver(self::DRIVER) as $connection) {
            try {
                $provider = $registry->get($connection);
            } catch (PaymentProcessingException|ProcessorNotFoundException) {
                // An account whose credentials are not filled in yet. Skipping
                // keeps the accounts that are configured verifiable.
                continue;
            }

            if ($provider instanceof MyfatoorahProvider && $provider->verifyWebhookSignature($payload, $signature)) {
                return $connection;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Register the route**

Modify `routes/webhooks.php`. Add the import beside the others:

```php
use Asciisd\CashierCore\Http\Controllers\Webhooks\MyfatoorahWebhookController;
```

Add the route after the `sticpay` line:

```php
Route::post('/myfatoorah', MyfatoorahWebhookController::class)->name('myfatoorah');
```

- [ ] **Step 5: Add the driver to the route registration test**

Modify `tests/Feature/Webhooks/RouteRegistrationTest.php:9`. Change:

```php
    foreach (['aps', 'jenapay', 'heropayment', 'payport', 'sticpay'] as $driver) {
```

to:

```php
    foreach (['aps', 'jenapay', 'heropayment', 'payport', 'sticpay', 'myfatoorah'] as $driver) {
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=MyfatoorahWebhook`
Expected: PASS — 10 tests

Run: `vendor/bin/pest --filter=RouteRegistration`
Expected: PASS — 2 tests

- [ ] **Step 7: Run the whole suite**

Run: `vendor/bin/pest`
Expected: PASS — no failures anywhere

- [ ] **Step 8: Commit**

```bash
git add src/Http/Controllers/Webhooks/MyfatoorahWebhookController.php routes/webhooks.php tests/Feature/Webhooks/MyfatoorahWebhookTest.php tests/Feature/Webhooks/RouteRegistrationTest.php
git commit -m "feat(myfatoorah): add the webhook endpoint

Gates on the undocumented MyFatoorah-Webhook-Version header, because the
V1 and V2 signing rules are incompatible and the version cannot be
inferred from the payload shape. Events other than PAYMENT_STATUS_CHANGED
are acknowledged and dropped before verification — a non-200 can lose an
event permanently, and verifying something we discard would mean carrying
six more field lists to reach the same outcome.

The signing account is identified by trying each connection's secret, so
a second country's webhooks land on the same URL and still resolve to the
account that sent them.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Documentation

The driver lists in the README and the boost skill enumerate the bundled drivers; both are wrong until MyFatoorah is in them.

**Files:**
- Modify: `README.md`
- Modify: `resources/boost/skills/cashier-core-development/SKILL.md`

**Interfaces:**
- Consumes: everything. Produces nothing code depends on.

- [ ] **Step 1: Find every place that enumerates the bundled drivers**

Run:

```bash
grep -rn "Sticpay\|sticpay" README.md resources/boost/skills/cashier-core-development/SKILL.md
```

Expected: several matches — driver lists, the `Drivers/{...}` architecture line, and the skill's YAML `description`.

- [ ] **Step 2: Update the README**

Modify `README.md:13-17`. Replace:

```markdown
Five direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport,
Sticpay** — plus internal `manual`, `bank_transfer` and `crypto` providers. All
of them are hosted-redirect: no PAN or CVV ever touches your application
(SAQ-A posture). Paytiko and KNET remain separate plugins
(`asciisd/cashier-paytiko`, `asciisd/knet`) built on this core.
```

with:

```markdown
Six direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport,
Sticpay, MyFatoorah** — plus internal `manual`, `bank_transfer` and `crypto`
providers. All of them are hosted-redirect: no PAN or CVV ever touches your
application (SAQ-A posture). Paytiko and KNET remain separate plugins
(`asciisd/cashier-paytiko`, `asciisd/knet`) built on this core.

MyFatoorah covers the GCC (card, KNET, Apple Pay, Google Pay) on its V3 API,
and supports deposits, webhooks and sync — not refunds. It takes **one
connection per country**: MyFatoorah issues one API key per country and each
key must be sent to that country's host. Note that reaching KNET *through*
MyFatoorah is not the same as the standalone `asciisd/knet` plugin, which
integrates KNET directly.
```

- [ ] **Step 3: Update the boost skill**

In `resources/boost/skills/cashier-core-development/SKILL.md`:

Add `myfatoorah` to the YAML `description`'s driver list, changing
`the bundled APS/Jenapay/Heropayment/Payport/Sticpay drivers` to
`the bundled APS/Jenapay/Heropayment/Payport/Sticpay/MyFatoorah drivers`.

In the Package Overview, change `Five direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport, Sticpay**` to `Six direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport, Sticpay, MyFatoorah**`.

In the Architecture block, change `Drivers/{Aps, Jenapay, Heropayment, Payport, Sticpay, Internal}` to `Drivers/{Aps, Jenapay, Heropayment, Payport, Sticpay, Myfatoorah, Internal}`.

Under "Connections vs drivers", after the `aps_binance` line in the config sample, add:

```php
    // MyFatoorah is one API key per COUNTRY, sent to that country's host.
    // A merchant trading in two countries is two connections.
    'myfatoorah' => [
        'driver' => 'myfatoorah',
        'base_url' => env('MYFATOORAH_BASE_URL'),
        'api_key' => env('MYFATOORAH_API_KEY'),
        'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
        'currency' => 'KWD',            // MyFatoorah cannot charge USD
        'payment_method' => 'KNET',     // omit for MyFatoorah's own picker
    ],
```

- [ ] **Step 4: Verify nothing else enumerates the drivers**

Run:

```bash
grep -rn "Payport, Sticpay\|payport.*sticpay" --include="*.md" --include="*.php" . | grep -v vendor | grep -v docs/superpowers
```

Expected: no remaining list that omits MyFatoorah. Update any that turn up.

- [ ] **Step 5: Run the whole suite one last time**

Run: `vendor/bin/pest`
Expected: PASS — no failures

- [ ] **Step 6: Commit**

```bash
git add README.md resources/boost/skills/cashier-core-development/SKILL.md
git commit -m "docs(myfatoorah): list the driver as bundled

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Post-implementation: what is NOT verified

Record honestly when reporting completion. Every test above runs against fixtures transcribed from the vendored docs, not against MyFatoorah.

- **Nothing has touched the live or sandbox API.** The whole driver is verified against `Http::fake()`.
- **The picker path.** Leaving `payment_method` unset relies on MyFatoorah showing its own method page for a redirection flow, which the V3 spec contradicts elsewhere. Confirm in the sandbox before a production connection omits it.
- **The webhook version header value.** `v2` is what MyFatoorah's library expects; the header is undocumented, so the exact casing and value should be confirmed against a real delivery. A mismatch shows up as a 403 with the critical log line naming what actually arrived — which is why that log line exists.
- **Refund events are dropped.** A refund issued from the MyFatoorah portal produces a code 2 webhook this driver acknowledges and ignores, leaving the local transaction `Succeeded`. Expected, and the first thing to revisit when refunds are added.
