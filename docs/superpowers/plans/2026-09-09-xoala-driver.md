# Xoala Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Xoala (`checkout-docs.xoala.com`) as a bundled deposit driver in `asciisd/cashier-core`, covering hosted checkout, signed callbacks and inquiry-backed sync.

**Architecture:** Four driver classes under `src/Drivers/Xoala/` following the Payport shape (Client, SignatureService, Adapter, Provider), plus two controllers. Xoala's Standard Checkout is entered by a browser **form POST**, not a URL, so `charge()` performs no HTTP at all: it mints a `merchantTransactionId`, and returns a signed package route as `metadata.redirect_url`. That route recomputes the MD5 checksum from the transaction row and renders an auto-submitting form to Xoala.

**Tech Stack:** PHP 8.3, Laravel 11/12/13, Pest 2/3/4, Orchestra Testbench.

**Spec:** `docs/superpowers/specs/2026-09-09-xoala-driver-design.md` — read it before Task 1.

## Global Constraints

- `declare(strict_types=1);` at the top of every new PHP file.
- Namespace: `Asciisd\CashierCore\Drivers\Xoala`. Class prefix `Xoala`.
- Driver string, route segment and `getName()` are all the literal `xoala`.
- Every checksum is `md5()` over pipe-joined values. Compare with `hash_equals`, never `==`.
- Amounts sent to and verified against Xoala are strings formatted `number_format($v, 2, '.', '')`. Never compare or re-sign a reparsed float.
- `PaymentResult::$amount` is `int` (whole major units) — `(int) round((float) $amount)`, matching `PayportAdapter::wholeUnits()`. `TransactionWebhookUpdate::$amount` is `?float` and keeps its decimals.
- HTTP goes through `Asciisd\CashierCore\Support\PspHttp`: `PspHttp::client()` for writes, `PspHttp::idempotent()` for safely repeatable reads.
- Run tests with `vendor/bin/pest`. Filter with `--filter`.
- Commit messages: `feat:` / `test:` / `docs:` prefix, and end with:
  `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`

## File Structure

| File | Responsibility |
|---|---|
| `src/Drivers/Xoala/XoalaSignatureService.php` | Every MD5 rule: checkout request, callback/redirect-back verification, inquiry. Owns the amount string format. |
| `src/Drivers/Xoala/XoalaAdapter.php` | `PaymentAdapterInterface`: status tables, charge context → `PaymentResult`, inquiry → `PaymentResult`, callback → `TransactionWebhookUpdate`. |
| `src/Drivers/Xoala/XoalaClient.php` | HTTP: `authToken()` (cached per connection), `inquiry()`. |
| `src/Drivers/Xoala/XoalaProvider.php` | `PaymentProcessorInterface` + `PreparesChargeData` + `ProvidesWebhookTransactionId`, and `checkoutForm()` for the bridge. |
| `src/Http/Controllers/XoalaCheckoutController.php` | The bridge: signed GET → auto-submitting form. |
| `src/Http/Controllers/Webhooks/XoalaWebhookController.php` | Callback: connection matching, replay guard, job dispatch. |
| `resources/views/xoala/checkout.blade.php` | The interstitial. Publishable. |
| `routes/checkout.php` | The bridge route, bare — the group supplies prefix/middleware/name. |

**Task order matters:** Task 4 consumes Tasks 1–3; Tasks 5 and 6 consume Task 4.

---

### Task 1: XoalaSignatureService

**Files:**
- Create: `src/Drivers/Xoala/XoalaSignatureService.php`
- Test: `tests/Unit/Drivers/XoalaSignatureServiceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `new XoalaSignatureService(string $memberId, string $secureKey)`
  - `forCheckout(string $totype, string $amount, string $merchantTransactionId, string $merchantRedirectUrl): string`
  - `forCallback(string $paymentId, string $merchantTransactionId, string $amount, string $status): string`
  - `verifyCallback(array $payload): bool`
  - `forInquiry(string $id): string`
  - `static amount(float|int|string $value): string`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/XoalaSignatureServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;

/*
 * The composition rules come from checkout-docs.xoala.com:
 *   checkout : memberId|totype|amount|merchantTransactionId|merchantRedirectUrl|secureKey
 *   callback : paymentId|merchantTransactionId|amount|status|secureKey
 *   inquiry  : memberId|secureKey|<id>
 * Each is hashed here against a literal md5() of the documented string, so the
 * service is checked against the vendor's rule rather than against itself.
 */

it('signs a checkout request the way the docs compose it', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    expect($service->forCheckout('PartnerName', '50.00', 'DEP-1', 'https://app.test/return'))
        ->toBe(md5('11344|PartnerName|50.00|DEP-1|https://app.test/return|secure-key'));
});

it('reproduces the callback example printed in the documentation', function () {
    // The docs' worked example: 77251|011E1D8A5C034|156.00|N|<merchant secret key>
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    expect($service->forCallback('77251', '011E1D8A5C034', '156.00', 'N'))
        ->toBe(md5('77251|011E1D8A5C034|156.00|N|merchant-secret'));
});

it('verifies a callback signed with the short transactionStatus', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');

    expect($service->verifyCallback($payload))->toBeTrue();
});

it('verifies a redirect-back POST, where the short status arrives as `status`', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'status' => 'N',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'N');

    expect($service->verifyCallback($payload))->toBeTrue();
});

it('rejects a callback whose amount was tampered with', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');
    $payload['amount'] = '1560.00';

    expect($service->verifyCallback($payload))->toBeFalse();
});

it('rejects a callback carrying no checksum at all', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    expect($service->verifyCallback(['paymentId' => '77251', 'amount' => '1.00']))->toBeFalse();
});

it('does not treat a differently formatted amount as equivalent', function () {
    // The digest is over the STRING. 156.0 and 156.00 are different inputs, and
    // quietly reformatting one to match the other would be forging agreement.
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.0',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');

    expect($service->verifyCallback($payload))->toBeFalse();
});

it('signs an inquiry with the id actually being sent', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    expect($service->forInquiry('DEP-1'))->toBe(md5('11344|secure-key|DEP-1'));
});

it('formats amounts to the two-decimal shape the checksum is computed over', function () {
    expect(XoalaSignatureService::amount(50))->toBe('50.00')
        ->and(XoalaSignatureService::amount(156.5))->toBe('156.50')
        ->and(XoalaSignatureService::amount('1000'))->toBe('1000.00')
        ->and(XoalaSignatureService::amount(0.1 + 0.2))->toBe('0.30');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaSignatureService`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Xoala/XoalaSignatureService.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

/**
 * Every MD5 checksum Xoala asks for, in one place.
 *
 * Xoala (a white-label of the Paymentz platform) authenticates each request and
 * each callback with an MD5 over pipe-joined values ending in the merchant's
 * secure key. The compositions differ per operation and are NOT interchangeable:
 *
 *   checkout : memberId|totype|amount|merchantTransactionId|merchantRedirectUrl|secureKey
 *   callback : paymentId|merchantTransactionId|amount|status|secureKey
 *   inquiry  : memberId|secureKey|<id>
 *
 * The secure key is the last field of the first two and the SECOND field of the
 * third — a detail worth re-reading before "tidying" the argument order.
 */
final class XoalaSignatureService
{
    public function __construct(
        private readonly string $memberId,
        private readonly string $secureKey,
    ) {}

    /**
     * The checksum accompanying a Standard Checkout form POST.
     *
     * `$amount` is a pre-formatted string, never a float: see amount().
     */
    public function forCheckout(
        string $totype,
        string $amount,
        string $merchantTransactionId,
        string $merchantRedirectUrl,
    ): string {
        return $this->hash([
            $this->memberId,
            $totype,
            $amount,
            $merchantTransactionId,
            $merchantRedirectUrl,
            $this->secureKey,
        ]);
    }

    /**
     * The checksum on a notification callback or a redirect-back POST.
     *
     * `$status` is the SHORT status — Y, N, P, 3D or C. The documentation's
     * worked example is `77251|011E1D8A5C034|156.00|N|<secret>`, and a callback
     * carries the short value in `transactionStatus` alongside a long `status`
     * such as `capturesuccess`. Signing the long one silently fails every
     * callback.
     */
    public function forCallback(
        string $paymentId,
        string $merchantTransactionId,
        string $amount,
        string $status,
    ): string {
        return $this->hash([
            $paymentId,
            $merchantTransactionId,
            $amount,
            $status,
            $this->secureKey,
        ]);
    }

    /**
     * Whether this payload's `checksum` was produced by our secure key.
     *
     * The amount is taken as the RAW STRING Xoala sent. Re-formatting it to
     * make a digest match would be forging agreement with ourselves — the whole
     * point of the check is that their bytes and ours agree.
     *
     * `transactionStatus` is preferred over `status` because a notification
     * carries both and only the former is short; a redirect-back POST carries
     * the short value under `status` and no `transactionStatus` at all.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $received = strtolower(trim((string) ($payload['checksum'] ?? '')));

        if ($received === '') {
            return false;
        }

        $status = (string) ($payload['transactionStatus'] ?? $payload['status'] ?? '');

        $expected = $this->forCallback(
            (string) ($payload['paymentId'] ?? ''),
            (string) ($payload['merchantTransactionId'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            $status,
        );

        return hash_equals($expected, $received);
    }

    /**
     * The checksum for a backoffice inquiry.
     *
     * Documented as `memberId|secureKey|paymentId`, but an `idType=MID` lookup
     * sends no paymentId — so we sign the id actually being sent, which is our
     * own merchantTransactionId. See the spec's "Assumptions to confirm".
     */
    public function forInquiry(string $id): string
    {
        return $this->hash([$this->memberId, $this->secureKey, $id]);
    }

    /**
     * The amount format every Xoala checksum is computed over: `N11
     * [0-9]{1,8}\.[0-9]{2}`.
     *
     * Lives here rather than on the provider because the FORMAT is a property
     * of the signature, not of the request — `50` and `50.00` hash differently,
     * so one helper has to serve the field set, the digest and the bridge alike.
     */
    public static function amount(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<string>  $parts
     */
    private function hash(array $parts): string
    {
        return md5(implode('|', $parts));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=XoalaSignatureService`
Expected: PASS — 9 tests

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Xoala/XoalaSignatureService.php tests/Unit/Drivers/XoalaSignatureServiceTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala checksum service

Three MD5 compositions, and the amount string format all of them are
computed over. The secure key is the last field of the checkout and
callback rules but the second field of the inquiry rule.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: XoalaAdapter

**Files:**
- Create: `src/Drivers/Xoala/XoalaAdapter.php`
- Test: `tests/Unit/Drivers/XoalaAdapterTest.php`

**Interfaces:**
- Consumes: `XoalaSignatureService::amount()` from Task 1.
- Produces:
  - `fromProviderResponse(mixed $response): PaymentResult` — takes the charge context array assembled by the provider: `['merchant_transaction_id' => string, 'amount' => string, 'currency' => string, 'redirect_url' => string, 'fields' => array]`
  - `fromProviderPayload(string $transactionId, array $payload): PaymentResult` — an inquiry response
  - `fromWebhook(array $payload): TransactionWebhookUpdate`
  - `mapStatus(mixed $providerStatus): PaymentStatus` — the long-status table
  - `mapShortStatus(mixed $providerStatus): PaymentStatus` — Y/N/P/3D/C
  - `getProviderName(): string`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/XoalaAdapterTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

function xoalaCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'paymentId' => '18608029',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'paymentBrand' => 'VISA',
        'paymentMode' => 'CC',
        'amount' => '100.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'remark' => 'Approved',
        'checksum' => 'irrelevant-here',
        'result' => ['code' => '00001', 'description' => 'Transaction succeeded'],
        'card' => ['bin' => '444433', 'last4Digits' => '1111'],
        'timestamp' => '2023-02-09 19:42:23',
    ], $overrides);
}

it('maps every documented long status', function (string $xoala, PaymentStatus $expected) {
    expect((new XoalaAdapter)->mapStatus($xoala))->toBe($expected);
})->with([
    ['capturesuccess', PaymentStatus::Succeeded],
    ['settled', PaymentStatus::Succeeded],
    ['authsuccessful', PaymentStatus::Succeeded],
    ['begun', PaymentStatus::Pending],
    ['authstarted', PaymentStatus::Pending],
    ['capturestarted', PaymentStatus::Pending],
    ['cancelstarted', PaymentStatus::Pending],
    ['markedforreversal', PaymentStatus::Pending],
    ['authfailed', PaymentStatus::Failed],
    ['capturefailed', PaymentStatus::Failed],
    ['failed', PaymentStatus::Failed],
    ['cancelled', PaymentStatus::Canceled],
    ['authcancelled', PaymentStatus::Canceled],
    ['reversed', PaymentStatus::Canceled],
    ['chargeback', PaymentStatus::Canceled],
]);

it('maps the short statuses', function (string $xoala, PaymentStatus $expected) {
    expect((new XoalaAdapter)->mapShortStatus($xoala))->toBe($expected);
})->with([
    ['Y', PaymentStatus::Succeeded],
    ['N', PaymentStatus::Failed],
    ['P', PaymentStatus::Pending],
    ['3D', PaymentStatus::Pending],
    ['C', PaymentStatus::Canceled],
]);

it('is case-insensitive about the long status', function () {
    expect((new XoalaAdapter)->mapStatus('CaptureSuccess'))->toBe(PaymentStatus::Succeeded);
});

it('falls back to the short status when the long one is absent', function () {
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['status' => '', 'transactionStatus' => 'N'])
    );

    expect($update->status)->toBe(PaymentStatus::Failed);
});

it('falls back to the short status when the long one is unrecognised', function () {
    // A status Xoala adds later must not silently read as Pending when the
    // payload already states the outcome plainly.
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['status' => 'somethingnew', 'transactionStatus' => 'Y'])
    );

    expect($update->status)->toBe(PaymentStatus::Succeeded);
});

it('builds a webhook update from a successful callback', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload());

    expect($update->status)->toBe(PaymentStatus::Succeeded)
        ->and($update->amount)->toBe(100.0)
        ->and($update->currency)->toBe('USD')
        ->and($update->metadata['xoala_payment_id'])->toBe('18608029')
        ->and($update->paymentMethodSnapshot?->lastFour)->toBe('1111')
        ->and($update->paymentMethodSnapshot?->type)->toBe(PaymentMethodType::CreditCard);
});

it('keeps the decimals of a callback amount', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload(['amount' => '100000.65']));

    expect($update->amount)->toBe(100000.65);
});

it('carries the result code and description onto a failed update', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload([
        'status' => 'authfailed',
        'transactionStatus' => 'N',
        'result' => ['code' => '10001', 'description' => 'Transaction failed'],
    ]));

    expect($update->status)->toBe(PaymentStatus::Failed)
        ->and($update->errorCode)->toBe('10001')
        ->and($update->errorMessage)->toBe('Transaction failed');
});

it('leaves error fields unset on a success', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload());

    expect($update->errorCode)->toBeNull()
        ->and($update->errorMessage)->toBeNull();
});

it('reports no payment method when the callback describes none', function () {
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['paymentMode' => '', 'paymentBrand' => '', 'card' => []])
    );

    expect($update->paymentMethodSnapshot)->toBeNull();
});

it('turns a charge context into a pending result carrying the bridge url', function () {
    $result = (new XoalaAdapter)->fromProviderResponse([
        'merchant_transaction_id' => 'DEP-1',
        'amount' => '50.00',
        'currency' => 'USD',
        'redirect_url' => 'https://app.test/cashier/xoala/checkout/DEP-1?signature=abc',
        'fields' => ['email' => 'john@example.com'],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(50)
        ->and($result->currency)->toBe('USD')
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toBe('https://app.test/cashier/xoala/checkout/DEP-1?signature=abc')
        ->and($result->metadata['xoala_fields'])->toBe(['email' => 'john@example.com']);
});

it('builds a result from an inquiry response', function () {
    $result = (new XoalaAdapter)->fromProviderPayload('DEP-1', [
        'paymentId' => '54289',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'amount' => '1.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'result' => ['code' => '00026', 'description' => 'Your record found successfully'],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(1)
        ->and($result->metadata['xoala_payment_id'])->toBe('54289');
});

it('reports an unpaid inquiry as pending and unsuccessful', function () {
    $result = (new XoalaAdapter)->fromProviderPayload('DEP-1', [
        'paymentId' => '54289',
        'status' => 'begun',
        'amount' => '1.00',
        'currency' => 'USD',
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe(PaymentStatus::Pending);
});

it('names the provider', function () {
    expect((new XoalaAdapter)->getProviderName())->toBe('xoala');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaAdapter`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Xoala\XoalaAdapter" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Xoala/XoalaAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class XoalaAdapter implements PaymentAdapterInterface
{
    /**
     * Xoala's long transaction statuses, from the Status Description page.
     *
     * `reversed` and `chargeback` map to Canceled on this package's own
     * authority: WebhookProcessor documents that "a settled deposit may only
     * move to Canceled (refund/chargeback)" and drops anything else arriving
     * after Succeeded as out-of-order. Any other mapping makes a chargeback a
     * silent no-op.
     *
     * The payout statuses (payoutsuccessful/payoutstarted/payoutfailed) are
     * deliberately absent — they cannot reach a deposit, and listing them would
     * imply a payout scope this driver does not have.
     *
     * @var array<string, PaymentStatus>
     */
    private const LONG_STATUSES = [
        'capturesuccess' => PaymentStatus::Succeeded,
        'settled' => PaymentStatus::Succeeded,
        'authsuccessful' => PaymentStatus::Succeeded,

        'begun' => PaymentStatus::Pending,
        'authstarted' => PaymentStatus::Pending,
        'capturestarted' => PaymentStatus::Pending,
        'cancelstarted' => PaymentStatus::Pending,
        'markedforreversal' => PaymentStatus::Pending,

        'authfailed' => PaymentStatus::Failed,
        'capturefailed' => PaymentStatus::Failed,
        'failed' => PaymentStatus::Failed,

        'cancelled' => PaymentStatus::Canceled,
        'authcancelled' => PaymentStatus::Canceled,
        'reversed' => PaymentStatus::Canceled,
        'chargeback' => PaymentStatus::Canceled,
    ];

    /**
     * The short statuses, as sent in `transactionStatus` (and in `status` on a
     * redirect-back POST). `3D` means the customer is still at the ACS page.
     *
     * @var array<string, PaymentStatus>
     */
    private const SHORT_STATUSES = [
        'y' => PaymentStatus::Succeeded,
        'n' => PaymentStatus::Failed,
        'p' => PaymentStatus::Pending,
        '3d' => PaymentStatus::Pending,
        'c' => PaymentStatus::Canceled,
    ];

    /**
     * Turn the charge context the provider assembled into a Pending result.
     *
     * There is no provider response to transform: Standard Checkout has no
     * server-to-server leg, so `charge()` never calls Xoala. What arrives here
     * is what we decided to send, plus the bridge URL that will send it.
     *
     * `success: true` is not a claim that the money arrived — it means "nothing
     * failed". PaymentService::createTransactionRecord() stamps `failed_at`
     * from `isFailed()`, i.e. from `! success` alone, so a Pending hosted-page
     * charge reporting false would be written to the database as failed the
     * moment it is created.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['merchant_transaction_id'],
            status: PaymentStatus::Pending,
            amount: $this->wholeUnits($response['amount'] ?? null) ?? 0,
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['redirect_url'] ?? null,
                // The optional extras the bridge re-renders. Everything the
                // checksum covers is deliberately NOT here: it lives on the
                // transaction row, so each signed value has one source.
                'xoala_fields' => $response['fields'] ?? null,
            ], fn ($value) => $value !== null && $value !== []),
            processorResponse: $response,
        );
    }

    /**
     * Transform an inquiry (`paymentType=IN`) response.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->statusFor($payload);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: $this->wholeUnits($payload['amount'] ?? null) ?? 0,
            currency: (string) ($payload['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $this->resultDescription($payload),
            metadata: $this->metadataFrom($payload),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($payload),
        );
    }

    /**
     * Transform a notification callback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->statusFor($payload);
        $failed = $status === PaymentStatus::Failed;

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($payload),
            metadata: $this->metadataFrom($payload),
            errorCode: $failed ? $this->resultCode($payload) : null,
            errorMessage: $failed ? $this->resultDescription($payload) : null,
            // float, not int: TransactionWebhookUpdate keeps the decimals, and
            // the reconciliation guard in WebhookProcessor compares against the
            // invoice with them.
            amount: isset($payload['amount']) && $payload['amount'] !== ''
                ? (float) $payload['amount']
                : null,
            currency: ($payload['currency'] ?? null) ?: null,
        );
    }

    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return self::LONG_STATUSES[strtolower(trim((string) $providerStatus))] ?? PaymentStatus::Pending;
    }

    public function mapShortStatus(mixed $providerStatus): PaymentStatus
    {
        return self::SHORT_STATUSES[strtolower(trim((string) $providerStatus))] ?? PaymentStatus::Pending;
    }

    public function getProviderName(): string
    {
        return 'xoala';
    }

    /**
     * The status of a payload carrying both forms.
     *
     * The long status wins where we recognise it — it distinguishes
     * `authsuccessful` from `capturesuccess`, which the short form flattens to
     * `Y`. Where it is absent OR unknown, the short form decides: a status
     * Xoala adds later must not read as Pending while the payload states the
     * outcome plainly two fields away.
     *
     * @param  array<string, mixed>  $payload
     */
    private function statusFor(array $payload): PaymentStatus
    {
        $long = strtolower(trim((string) ($payload['status'] ?? '')));

        if (isset(self::LONG_STATUSES[$long])) {
            return self::LONG_STATUSES[$long];
        }

        return $this->mapShortStatus($payload['transactionStatus'] ?? null);
    }

    /**
     * Xoala amounts are major units; PaymentResult::$amount is a whole-unit int.
     */
    private function wholeUnits(mixed $amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round((float) $amount);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFrom(array $payload): array
    {
        return array_filter([
            // Kept because a refund or reversal keys on it and nothing else we
            // hold carries it — provider_transaction_id is our own id.
            'xoala_payment_id' => $payload['paymentId'] ?? null,
            'xoala_result_code' => $this->resultCode($payload),
            'xoala_bank_reference_id' => $payload['bankReferenceId'] ?? null,
            'xoala_terminal_id' => $payload['terminalId'] ?? null,
            'xoala_remark' => $payload['remark'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultCode(array $payload): ?string
    {
        $code = data_get($payload, 'result.code') ?? $payload['resultCode'] ?? null;

        return $code === null || $code === '' ? null : (string) $code;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultDescription(array $payload): ?string
    {
        $description = data_get($payload, 'result.description')
            ?? $payload['resultDescription']
            ?? null;

        return $description === null || $description === '' ? null : (string) $description;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function snapshot(array $payload): ?PaymentMethodSnapshot
    {
        $mode = strtoupper(trim((string) ($payload['paymentMode'] ?? '')));
        $brand = strtolower(trim((string) ($payload['paymentBrand'] ?? '')));
        $lastFour = (string) (data_get($payload, 'card.last4Digits')
            ?? data_get($payload, 'card.lastFourDigits')
            ?? $payload['cardLast4Digits']
            ?? '');

        if ($mode === '' && $brand === '' && $lastFour === '') {
            return null;
        }

        return new PaymentMethodSnapshot(
            type: $this->methodType($mode),
            brand: $this->brand($brand),
            lastFour: $lastFour !== '' ? $lastFour : null,
            displayName: $this->displayName($payload['paymentBrand'] ?? null, $lastFour),
        );
    }

    /**
     * Xoala's `paymentMode` codes: CC credit card, NB net banking / bank
     * transfer, EW e-wallet, SEPA direct debit, PV prepaid voucher, MMA mobile
     * money.
     */
    private function methodType(string $mode): PaymentMethodType
    {
        return match ($mode) {
            'CC' => PaymentMethodType::CreditCard,
            'DC' => PaymentMethodType::DebitCard,
            'NB', 'BT', 'SEPA' => PaymentMethodType::BankTransfer,
            'EW', 'MMA' => PaymentMethodType::DigitalWallet,
            'PV' => PaymentMethodType::Cash,
            default => PaymentMethodType::Other,
        };
    }

    private function brand(string $brand): PaymentMethodBrand
    {
        return match ($brand) {
            'visa' => PaymentMethodBrand::Visa,
            'mc', 'mastercard' => PaymentMethodBrand::Mastercard,
            'amex' => PaymentMethodBrand::AmericanExpress,
            'jcb' => PaymentMethodBrand::JCB,
            'cup', 'unionpay' => PaymentMethodBrand::UnionPay,
            'diners' => PaymentMethodBrand::DinersClub,
            'discover' => PaymentMethodBrand::Discover,
            'sepaexpress', 'sepa', 'directdebit' => PaymentMethodBrand::SEPA,
            'applepay' => PaymentMethodBrand::ApplePay,
            'googlepay' => PaymentMethodBrand::GooglePay,
            default => PaymentMethodBrand::Other,
        };
    }

    private function displayName(mixed $brand, string $lastFour): string
    {
        $label = trim((string) $brand) !== '' ? trim((string) $brand) : 'Xoala';

        return $lastFour === '' ? $label : "{$label} •••• {$lastFour}";
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=XoalaAdapter`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Xoala/XoalaAdapter.php tests/Unit/Drivers/XoalaAdapterTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala adapter

Long statuses decide where we recognise them; the short Y/N/P/3D/C form
decides otherwise, so a status Xoala adds later cannot read as Pending
while the payload states the outcome two fields away.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: XoalaClient

**Files:**
- Create: `src/Drivers/Xoala/XoalaClient.php`
- Test: `tests/Unit/Drivers/XoalaClientTest.php`

**Interfaces:**
- Consumes: `PspHttp` (existing).
- Produces:
  - `new XoalaClient(string $baseUrl, string $memberId, string $secureKey, string $cacheKey, ?string $username = null)`
  - `inquiry(string $merchantTransactionId, string $checksum): ?array` — the `parameters` node, or `null`
  - `forgetToken(): void`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/XoalaClientTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function xoalaClient(): XoalaClient
{
    return new XoalaClient('https://xoala.test', '11344', 'secure-key', 'xoala');
}

beforeEach(function () {
    Cache::flush();
});

it('fetches a token, then inquires with it in the authtoken header', function () {
    Http::fake([
        'https://xoala.test/transactionServices/REST/v1/authToken' => Http::response([
            'result' => ['code' => '200', 'description' => 'Token generated successfully'],
            'AuthToken' => 'jwt-token',
        ]),
        'https://xoala.test/transactionServices/REST/v1/inquiry' => Http::response([
            'paymentId' => '54289',
            'status' => 'capturesuccess',
            'amount' => '1.00',
            'result' => ['code' => '00026', 'description' => 'Your record found successfully'],
        ]),
    ]);

    $data = xoalaClient()->inquiry('DEP-1', 'the-checksum');

    expect($data)->not->toBeNull()
        ->and($data['paymentId'])->toBe('54289');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/inquiry')
            && $request->header('authtoken') === ['jwt-token']
            && $request['paymentType'] === 'IN'
            && $request['idType'] === 'MID'
            && $request['merchantTransactionId'] === 'DEP-1'
            && $request['authentication.memberId'] === '11344'
            && $request['authentication.checksum'] === 'the-checksum';
    });
});

it('sends the secure key as authentication.sKey and never as a bare field', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    xoalaClient()->inquiry('DEP-1', 'the-checksum');

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/authToken')) {
            return true;
        }

        return $request['authentication.sKey'] === 'secure-key'
            && $request['authentication.memberId'] === '11344';
    });
});

it('caches the token across calls', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c1');
    $client->inquiry('DEP-2', 'c2');

    // One token fetch, two inquiries.
    Http::assertSentCount(3);
});

it('caches the token per connection, not globally', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    (new XoalaClient('https://xoala.test', '1', 'k1', 'xoala'))->inquiry('DEP-1', 'c');
    (new XoalaClient('https://xoala.test', '2', 'k2', 'xoala_second'))->inquiry('DEP-2', 'c');

    // Two token fetches, two inquiries — a shared cache key would send three.
    Http::assertSentCount(4);
});

it('returns null when the token cannot be obtained', function () {
    Http::fake([
        '*/authToken' => Http::response(['result' => ['code' => '401']], 401),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/inquiry'));
});

it('returns null when the inquiry itself fails', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response('gateway down', 502),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('returns null when the inquiry answers with HTML rather than JSON', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response('<html>blocked</html>', 200),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('returns null when the record is not found, rather than inventing a status', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response([
            'result' => ['code' => '10009', 'description' => 'Record not found'],
        ]),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('drops the cached token when Xoala rejects it, so the next call re-authenticates', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['result' => ['code' => '401']], 401),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c');
    $client->inquiry('DEP-2', 'c');

    // Two token fetches, not one: a token cached for 55 minutes after Xoala
    // stopped honouring it would fail every sync in that window.
    Http::assertSentCount(4);
});

it('drops a cached token on request so the next call re-authenticates', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c');
    $client->forgetToken();
    $client->inquiry('DEP-2', 'c');

    // Two token fetches because the cache was cleared between inquiries.
    Http::assertSentCount(4);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaClient`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Xoala\XoalaClient" not found`

- [ ] **Step 3: Write the implementation**

Create `src/Drivers/Xoala/XoalaClient.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Xoala's REST surface — only as much of it as sync needs.
 *
 * Every REST call carries a bearer-style `authtoken` header obtained from
 * /transactionServices/REST/v1/authToken, which Xoala documents as valid for
 * one hour. Standard Checkout itself needs none of this: the hosted page is a
 * browser form POST authenticated by the checksum alone, so this client exists
 * purely to answer "did that deposit actually settle?".
 */
final class XoalaClient
{
    /**
     * Short of the documented hour, so a token cannot expire in flight between
     * our cache read and Xoala's clock.
     */
    private const TOKEN_TTL_SECONDS = 3300;

    /**
     * @param  string  $cacheKey  the connection name — tokens are per merchant
     *                            account, and a shared key would hand one
     *                            account's token to another's inquiry
     * @param  string|null  $username  sent as `merchant.username` when the
     *                                 account requires it; see the spec's
     *                                 "Assumptions to confirm"
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $memberId,
        private readonly string $secureKey,
        private readonly string $cacheKey,
        private readonly ?string $username = null,
    ) {}

    /**
     * Look a transaction up by the merchant id we assigned it.
     *
     * Returns null for every failure — no token, transport error, non-JSON
     * body, or a record Xoala does not have. `syncTransaction()` logs that as
     * `transactionNotFoundAtProvider` and moves on; inventing a status here
     * would let a lookup failure mark a live deposit failed.
     *
     * @return array<string, mixed>|null
     */
    public function inquiry(string $merchantTransactionId, string $checksum): ?array
    {
        $token = $this->authToken();

        if ($token === null) {
            return null;
        }

        try {
            $response = PspHttp::idempotent()
                ->withHeaders(['authtoken' => $token])
                ->acceptJson()
                ->asForm()
                ->post($this->baseUrl.'/transactionServices/REST/v1/inquiry', [
                    'authentication.memberId' => $this->memberId,
                    'authentication.checksum' => $checksum,
                    'paymentType' => 'IN',
                    // Look up by OUR id. provider_transaction_id holds the
                    // merchantTransactionId, because Standard Checkout issues
                    // no paymentId until the customer has actually paid.
                    'idType' => 'MID',
                    'merchantTransactionId' => $merchantTransactionId,
                ]);
        } catch (HttpClientException $e) {
            PaymentLogger::providerTransactionLookupFailed(
                'xoala',
                $merchantTransactionId,
                // 0, not null: the logger types this `int`, and a connection
                // failure produced no response to take a status from.
                0,
                $e->getMessage(),
            );

            return null;
        }

        // A token Xoala has stopped honouring is cached for up to 55 minutes,
        // which would otherwise fail every sync in that window. Dropping it
        // here lets the next attempt re-authenticate on its own.
        if ($response->status() === 401) {
            $this->forgetToken();
        }

        $body = $this->json($response);

        if ($body === null) {
            PaymentLogger::providerTransactionLookupFailed(
                'xoala',
                $merchantTransactionId,
                $response->status(),
                $this->resultDescription($response) ?? 'non-JSON or failed response',
            );

            return null;
        }

        // A found record always names its status. A "record not found" answer
        // arrives as HTTP 200 with only a `result` node, so the status code
        // cannot be what decides this.
        if (($body['status'] ?? '') === '' && ($body['transactionStatus'] ?? '') === '') {
            PaymentLogger::providerTransactionLookupFailed(
                'xoala',
                $merchantTransactionId,
                $response->status(),
                (string) (data_get($body, 'result.description') ?? 'no status in response'),
            );

            return null;
        }

        return $body;
    }

    /**
     * Drop the cached token. Call after a 401 so the next attempt re-fetches
     * rather than replaying a token Xoala has stopped honouring.
     */
    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    private function authToken(): ?string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = PspHttp::idempotent()
                ->acceptJson()
                ->asForm()
                ->post($this->baseUrl.'/transactionServices/REST/v1/authToken', array_filter([
                    'authentication.memberId' => $this->memberId,
                    'authentication.sKey' => $this->secureKey,
                    'merchant.username' => $this->username,
                ], fn ($value) => $value !== null && $value !== ''));
        } catch (HttpClientException $e) {
            PaymentLogger::providerChargeRequestFailed('xoala', null, $e->getMessage());

            return null;
        }

        $body = $this->json($response);
        $token = is_array($body) ? (string) ($body['AuthToken'] ?? '') : '';

        if ($token === '') {
            PaymentLogger::providerChargeRequestFailed(
                'xoala',
                $response->status(),
                'Xoala issued no auth token: '.($this->resultDescription($response) ?? 'no description'),
            );

            return null;
        }

        Cache::put($this->tokenCacheKey(), $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return "cashier-core:xoala:auth-token:{$this->cacheKey}";
    }

    /**
     * The decoded body of a successful response, or null.
     *
     * Xoala answers a rejected key with an HTML error page rather than JSON, so
     * a 200 proves nothing on its own.
     *
     * @return array<string, mixed>|null
     */
    private function json(Response $response): ?array
    {
        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    private function resultDescription(Response $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $description = data_get($body, 'result.description');

        return $description === null ? null : (string) $description;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=XoalaClient`
Expected: PASS

The two logger signatures this task calls, confirmed against
`src/Logging/PaymentLogger.php`:

```php
providerChargeRequestFailed(string $provider, ?int $httpStatus, string $error): void
providerTransactionLookupFailed(string $provider, string $providerTransactionId, int $httpStatus, string $body): void
```

Note the asymmetry: the charge one takes a **nullable** status, the lookup one
does **not**. Under `strict_types` passing `null` to the lookup logger is a
TypeError, which is why the transport-failure branch passes `0`. Do not invent
new logger methods, and do not widen the existing signatures.

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Xoala/XoalaClient.php tests/Unit/Drivers/XoalaClientTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala REST client

Auth tokens are cached per connection for 55 minutes against a documented
one-hour life. Every failure path returns null: a lookup that could not be
completed must not be able to mark a live deposit failed.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: XoalaProvider and driver registration

**Files:**
- Create: `src/Drivers/Xoala/XoalaProvider.php`
- Modify: `src/CashierCoreServiceProvider.php` — add to `BUNDLED_DRIVERS`
- Modify: `src/Cashier.php` — add `xoala` defaults to `fakeConnection()`
- Test: `tests/Unit/Drivers/XoalaProviderTest.php`

**Interfaces:**
- Consumes: `XoalaSignatureService`, `XoalaAdapter`, `XoalaClient` (Tasks 1–3).
- Produces:
  - `new XoalaProvider(array $config)`
  - `charge(array $data): PaymentResult`
  - `prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array`
  - `retrieve(string $transactionId): ?PaymentResult`
  - `checkoutForm(Transaction $transaction): array` → `['action' => string, 'fields' => array<string, string>]`
  - `verifyWebhookSignature(array $payload, string $signature): bool`
  - `extractWebhookTransactionId(array $payload): ?string`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/XoalaProviderTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Enums\PaymentStatus;
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaProvider`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Xoala\XoalaProvider" not found`

- [ ] **Step 3: Write the provider**

Create `src/Drivers/Xoala/XoalaProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Xoala (checkout-docs.xoala.com) hosted Standard Checkout deposits.
 *
 * Xoala is a white-label of the Paymentz platform. Standard Checkout has NO
 * server-to-server leg: the customer's browser POSTs a signed field set to
 * {host}/transaction/Checkout and Xoala hosts the payment page from there.
 * `charge()` therefore performs no HTTP at all — it mints an id and hands the
 * engine a signed package route, and {@see XoalaCheckoutController} turns that
 * route into the form POST Xoala expects.
 *
 * The alternatives were weighed and rejected in the design: the REST
 * asynchronous flow returns a redirect URL directly but only avoids PCI scope
 * for non-card methods, and the Invoice API is built around emailing the
 * customer a link that expires.
 */
class XoalaProvider implements PaymentProcessorInterface, PreparesChargeData, ProvidesWebhookTransactionId
{
    /**
     * The route the bridge is registered under, assembled from the checkout
     * group's configured name prefix.
     */
    private const ROUTE = 'xoala';

    /**
     * How long a customer has to arrive at the hosted page. Long enough for a
     * slow redirect chain, short enough that a leaked URL is not a standing
     * invitation to re-open somebody's payment form.
     */
    private const LINK_TTL_MINUTES = 30;

    private XoalaSignatureService $signatureService;

    private XoalaClient $client;

    private XoalaAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var list<string> */
    private array $supportedFeatures = ['charge', 'webhook'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['base_url']) || empty($config['member_id']) || empty($config['secure_key'])) {
            throw new PaymentProcessingException('Xoala provider is not configured.');
        }

        // `totype` is inside the request checksum, so a missing one does not
        // fail loudly at Xoala — it fails as a generic rejection on the hosted
        // page, which is an expensive thing to diagnose.
        if (empty($config['totype'])) {
            throw new PaymentProcessingException('Xoala provider requires a `totype`.');
        }

        $this->signatureService = new XoalaSignatureService(
            memberId: (string) $config['member_id'],
            secureKey: (string) $config['secure_key'],
        );

        $this->client = new XoalaClient(
            baseUrl: $this->baseUrl(),
            memberId: (string) $config['member_id'],
            secureKey: (string) $config['secure_key'],
            // Keyed on the ACCOUNT, not the connection name. ConnectionRegistry
            // hands a provider its raw connection config, which carries no
            // connection name — so a name-based key would be the same constant
            // for every account and hand one merchant's token to another's
            // inquiry. Two connections onto one Xoala account (a per-currency
            // split, say) may legitimately share a token, and this gets that
            // right for free.
            cacheKey: md5($this->baseUrl().'|'.$config['member_id']),
            username: ($config['username'] ?? null) ?: null,
        );

        $this->adapter = new XoalaAdapter;
    }

    /**
     * Declare the connection's currency BEFORE the charge so the engine can
     * price the leg.
     *
     * Resolving it here rather than inside `charge()` is what makes it visible
     * to PaymentService, which converts the amount when the declared currency
     * differs from the account's. Doing it privately in `charge()` leaves the
     * engine sending one currency's figure under another's label.
     *
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        $paymentData['currency'] = $this->currency($paymentData);

        return $paymentData;
    }

    /**
     * Open a deposit. No HTTP: see the class docblock.
     *
     * @param  array<string, mixed>  $data
     */
    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $merchantTransactionId = (string) ($data['order_id'] ?? $data['order_number'] ?? 'DEP-'.Str::ulid());

        return $this->adapter->fromProviderResponse([
            'merchant_transaction_id' => $merchantTransactionId,
            'amount' => XoalaSignatureService::amount($validated['amount']),
            'currency' => $this->currency($data),
            'redirect_url' => $this->bridgeUrl($merchantTransactionId),
            'fields' => $this->optionalFields($data),
        ]);
    }

    /**
     * The action and field set for the hosted checkout form.
     *
     * Called by the bridge at render time, not at charge time, and every signed
     * value is read from the transaction row — so the checksum is never
     * persisted and there is no second copy of it to drift.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function checkoutForm(Transaction $transaction): array
    {
        $merchantTransactionId = (string) $transaction->provider_transaction_id;

        // The charge leg, not the account amount: on a converted deposit the
        // engine priced a foreign leg and that is the figure Xoala invoices.
        $amount = XoalaSignatureService::amount(
            $transaction->charge_amount ?? $transaction->amount
        );
        $currency = strtoupper((string) ($transaction->charge_currency ?? $transaction->currency));

        $redirectUrl = $this->url('redirect_url', 'payment.success');

        $fields = array_filter([
            'memberId' => (string) $this->config['member_id'],
            'totype' => (string) $this->config['totype'],
            'amount' => $amount,
            'currency' => $currency,
            'merchantTransactionId' => $merchantTransactionId,
            'merchantRedirectUrl' => $redirectUrl,
            'notificationUrl' => $this->url('webhook_url', 'cashier.webhooks.xoala'),
            // DB authorizes and captures in one step, which is what a deposit
            // wants. PA would leave the funds held and uncaptured.
            'transactionType' => strtoupper((string) ($this->config['transaction_type'] ?? 'DB')),
            'terminalid' => $this->configString('terminal_id'),
            'paymentMode' => $this->configString('payment_mode'),
            'paymentBrand' => $this->configString('payment_brand'),
            'lang' => $this->language(),
        ], fn ($value) => $value !== null && $value !== '');

        $extras = (array) (($transaction->metadata['xoala_fields'] ?? []) ?: []);

        return [
            'action' => $this->baseUrl().'/transaction/Checkout',
            'fields' => array_merge($extras, $fields, [
                'checksum' => $this->signatureService->forCheckout(
                    (string) $this->config['totype'],
                    $amount,
                    $merchantTransactionId,
                    (string) $redirectUrl,
                ),
            ]),
        ];
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $data = $this->client->inquiry(
            $transactionId,
            $this->signatureService->forInquiry($transactionId),
        );

        return $data === null ? null : $this->adapter->fromProviderPayload($transactionId, $data);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        return $this->retrieve($transactionId)?->status->value ?? 'unknown';
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('Xoala refunds are not implemented: a refund keys on Xoala’s paymentId, which this driver only learns from a callback.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Xoala capture is not supported in the hosted checkout flow.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Xoala authorize is not supported in the hosted checkout flow.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Xoala reversal is not implemented: it keys on Xoala’s paymentId, which this driver only learns from a callback.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validatePaymentData(array $data): array
    {
        return validator($data, [
            'amount' => 'required|numeric|min:0.01',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return $this->signatureService->verifyCallback($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookTransactionId(array $payload): ?string
    {
        $id = (string) ($payload['merchantTransactionId'] ?? '');

        return $id !== '' ? $id : null;
    }

    public function getName(): string
    {
        return 'xoala';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }

    /**
     * The signed, expiring URL the engine redirects the customer to.
     */
    private function bridgeUrl(string $merchantTransactionId): string
    {
        $name = config('cashier-core.routes.checkout.name_prefix', 'cashier.checkout.').self::ROUTE;

        if (! Route::has($name)) {
            throw new PaymentProcessingException(
                "Xoala needs the `{$name}` route. It is registered by the package unless routes are disabled; register your own bridge if you disabled them."
            );
        }

        return URL::temporarySignedRoute(
            $name,
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['transaction' => $merchantTransactionId],
        );
    }

    /**
     * The customer details Xoala accepts but does not sign.
     *
     * Only these are persisted to metadata. Everything the checksum covers is
     * recomputed from the transaction row at render time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function optionalFields(array $data): array
    {
        $metadata = (array) ($data['metadata'] ?? []);
        $name = trim((string) ($metadata['user_name'] ?? ''));

        return array_filter([
            'email' => (string) ($metadata['user_email'] ?? ''),
            'firstName' => $name !== '' ? Str::before($name, ' ') : '',
            'lastName' => $name !== '' ? trim(Str::after($name, ' ')) : '',
            'ip' => (string) ($metadata['user_ip'] ?? ''),
            'country' => strtoupper((string) ($metadata['user_country'] ?? '')),
            'orderDescription' => (string) ($data['description'] ?? ''),
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * The currency this connection invoices in.
     *
     * Upper-cased so the value the engine compares against the account currency
     * is the value Xoala is sent — a lower-case env entry would otherwise read
     * as "foreign" to the engine and as an unknown currency to Xoala.
     *
     * @param  array<string, mixed>  $data
     */
    private function currency(array $data): string
    {
        return strtoupper((string) (($this->config['currency'] ?? null)
            ?: ($data['currency'] ?? config('cashier-core.currency.default', 'USD'))));
    }

    /**
     * A configured callback/redirect URL, falling back to the named route.
     */
    private function url(string $key, string $fallbackRoute): ?string
    {
        $url = trim((string) ($this->config[$key] ?? ''));

        if ($url !== '') {
            return $url;
        }

        return Route::has($fallbackRoute) ? route($fallbackRoute) : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/');
    }

    private function configString(string $key): ?string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * The hosted page's language: two or three letters, defaulting to English.
     */
    private function language(): string
    {
        $configured = $this->configString('language');

        return strtolower($configured ?? substr(app()->getLocale(), 0, 2)) ?: 'en';
    }
}
```

- [ ] **Step 4: Register the driver**

In `src/CashierCoreServiceProvider.php`, add to `BUNDLED_DRIVERS` after the `myfatoorah` line:

```php
        'xoala' => Drivers\Xoala\XoalaProvider::class,
```

In `src/Cashier.php`, add to the `fakeConnection()` match before `default => []`:

```php
            'xoala' => [
                'base_url' => 'https://secure-checkout-sandbox.xoala.com',
                'member_id' => '11344',
                'secure_key' => "{$name}-secure-key",
                'totype' => 'TestPartner',
                'redirect_url' => 'https://members.example.com/payment/success',
                'webhook_url' => 'https://members.example.com/api/webhooks/xoala',
            ],
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/pest --filter=XoalaProvider`
Expected: PASS — every test, with no skips or `->todo()` markers. The
`beforeEach` stub route makes the signed-URL assertions real now, and Task 5
replaces the stub with the registered route without touching this test.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/Xoala/XoalaProvider.php src/CashierCoreServiceProvider.php src/Cashier.php tests/Unit/Drivers/XoalaProviderTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala provider

charge() performs no HTTP: Standard Checkout has no server-to-server leg,
so it mints an id and returns a signed bridge route. checkoutForm()
recomputes the checksum from the transaction row at render time, so no
request authenticator is ever persisted.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: The checkout bridge

**Files:**
- Create: `routes/checkout.php`
- Create: `src/Http/Controllers/XoalaCheckoutController.php`
- Create: `resources/views/xoala/checkout.blade.php`
- Modify: `src/CashierCoreServiceProvider.php` — checkout route group, view loading, publish tag, rate limiter
- Modify: `config/cashier-core.php` — `routes.checkout` block
- Test: `tests/Feature/XoalaCheckoutControllerTest.php`

**Interfaces:**
- Consumes: `XoalaProvider::checkoutForm()` from Task 4.
- Produces: route `cashier.checkout.xoala` at `GET {prefix}/xoala/checkout/{transaction}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/XoalaCheckoutControllerTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Cashier::fakeConnection('xoala');
});

function xoalaTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'xoala',
        'connection' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'type' => TransactionType::Deposit,
        'amount' => 50.0,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ], $overrides));
}

function xoalaBridgeUrl(string $id = 'DEP-1'): string
{
    return URL::temporarySignedRoute('cashier.checkout.xoala', now()->addMinutes(30), ['transaction' => $id]);
}

it('renders an auto-submitting form for a pending deposit', function () {
    xoalaTransaction();

    $response = $this->get(xoalaBridgeUrl());

    $response->assertOk()
        ->assertSee('https://secure-checkout-sandbox.xoala.com/transaction/Checkout', false)
        ->assertSee('name="merchantTransactionId" value="DEP-1"', false)
        ->assertSee('name="amount" value="50.00"', false);
});

it('renders a checksum the signature service agrees with', function () {
    xoalaTransaction();

    // Not merely "a checksum is present": the digest itself is the thing that
    // has to be right, and a form rendering the wrong one fails at the hosted
    // page with no useful message.
    $expected = (new XoalaSignatureService('11344', 'xoala-secure-key'))->forCheckout(
        'TestPartner',
        '50.00',
        'DEP-1',
        'https://members.example.com/payment/success',
    );

    $this->get(xoalaBridgeUrl())
        ->assertOk()
        ->assertSee('name="checksum" value="'.$expected.'"', false);
});

it('renders the charge leg on a converted deposit', function () {
    xoalaTransaction(['charge_amount' => 375.0, 'charge_currency' => 'SAR']);

    $this->get(xoalaBridgeUrl())
        ->assertOk()
        ->assertSee('name="amount" value="375.00"', false)
        ->assertSee('name="currency" value="SAR"', false);
});

it('refuses an unsigned url', function () {
    xoalaTransaction();

    $this->get('/cashier/xoala/checkout/DEP-1')->assertForbidden();
});

it('refuses an expired url', function () {
    xoalaTransaction();

    $url = URL::temporarySignedRoute('cashier.checkout.xoala', now()->subMinute(), ['transaction' => 'DEP-1']);

    $this->get($url)->assertForbidden();
});

it('refuses a url signed for a different transaction', function () {
    xoalaTransaction();

    // Tamper with the path while keeping a valid-looking signature.
    $url = str_replace('DEP-1', 'DEP-2', xoalaBridgeUrl());

    $this->get($url)->assertForbidden();
});

it('404s an unknown transaction', function () {
    $this->get(xoalaBridgeUrl('DEP-missing'))->assertNotFound();
});

it('404s a transaction belonging to another driver', function () {
    xoalaTransaction(['provider' => 'payport']);

    $this->get(xoalaBridgeUrl())->assertNotFound();
});

it('refuses to re-present a settled deposit as payable', function () {
    xoalaTransaction(['status' => PaymentStatus::Succeeded]);

    $this->get(xoalaBridgeUrl())->assertStatus(409);
});

it('refuses to re-present a failed deposit', function () {
    xoalaTransaction(['status' => PaymentStatus::Failed]);

    $this->get(xoalaBridgeUrl())->assertStatus(409);
});

it('offers a manual submit button when javascript is unavailable', function () {
    xoalaTransaction();

    $this->get(xoalaBridgeUrl())->assertSee('<noscript>', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaCheckout`
Expected: FAIL — `Route [cashier.checkout.xoala] not defined.`

- [ ] **Step 3: Add the config block and route file**

In `config/cashier-core.php`, inside the existing `'routes' => [...]` array, after `'name_prefix' => 'cashier.webhooks.',` add:

```php
        /*
         * The hosted-checkout bridge. Xoala's Standard Checkout is entered by
         * a browser form POST rather than a URL, so the package serves a signed
         * GET page that submits that form. No `web` middleware: the page holds
         * no session and no CSRF token, and requiring the host's `web` group
         * would be a surprising coupling for a page whose only job is to
         * submit to an external host.
         */
        'checkout' => [
            'prefix' => env('CASHIER_CHECKOUT_PREFIX', 'cashier'),
            'middleware' => ['signed', 'throttle:cashier-checkout'],
            'name_prefix' => 'cashier.checkout.',
        ],
```

Create `routes/checkout.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Http\Controllers\XoalaCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Hosted-Checkout Bridges
|--------------------------------------------------------------------------
|
| Signed GET pages that hand a customer off to a PSP whose hosted checkout is
| entered by a form POST rather than a URL. Prefix, middleware and name prefix
| come from `cashier-core.routes.checkout.*` via the service provider's group.
|
*/

Route::get('/xoala/checkout/{transaction}', XoalaCheckoutController::class)->name('xoala');
```

- [ ] **Step 4: Wire the provider**

In `src/CashierCoreServiceProvider.php`:

Add view loading to `boot()`, before `registerRoutes()`:

```php
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier-core');
```

Add to `publishConfiguration()`, inside the `runningInConsole()` block:

```php
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/cashier-core'),
            ], 'cashier-core-views');
```

Add the second group at the end of `registerRoutes()`:

```php
        Route::group([
            'prefix' => config('cashier-core.routes.checkout.prefix', 'cashier'),
            'as' => config('cashier-core.routes.checkout.name_prefix', 'cashier.checkout.'),
            'middleware' => config('cashier-core.routes.checkout.middleware', ['signed', 'throttle:cashier-checkout']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/checkout.php');
        });
```

Add a limiter to `registerRateLimiter()`:

```php
        if (RateLimiter::limiter('cashier-checkout') === null) {
            RateLimiter::for(
                'cashier-checkout',
                fn (Request $request) => Limit::perMinute(30)->by($request->ip())
            );
        }
```

- [ ] **Step 5: Write the controller and view**

Create `src/Http/Controllers/XoalaCheckoutController.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands a customer off to Xoala's hosted Standard Checkout.
 *
 * Xoala's checkout is entered by a browser form POST, but the engine's charge
 * contract carries a `redirect_url`. This page is the join: `charge()` returns
 * a signed link here, and this renders the signed field set as a form that
 * submits itself.
 *
 * The URL is signed and expiring (the `signed` middleware enforces both), so
 * the page cannot be reached by guessing a transaction id — and even a valid
 * link only renders while the deposit is still payable.
 */
class XoalaCheckoutController extends Controller
{
    private const DRIVER = 'xoala';

    public function __invoke(string $transaction, ConnectionRegistry $registry): View
    {
        $model = Cashier::transactionModel();

        $record = $model::query()
            ->where('provider', self::DRIVER)
            ->where('provider_transaction_id', $transaction)
            ->first();

        abort_if($record === null, Response::HTTP_NOT_FOUND);

        // A settled, failed or cancelled deposit must not be re-presentable as
        // a payable form: paying it again would open a second charge against a
        // transaction row that can only record one.
        abort_if(
            $record->status !== PaymentStatus::Pending,
            Response::HTTP_CONFLICT,
            'This payment is no longer awaiting payment.',
        );

        try {
            // The connection the charge was taken through, so a second Xoala
            // account signs with its own secure key.
            $provider = $registry->get($record->connection ?: self::DRIVER);
        } catch (PaymentProcessingException|ProcessorNotFoundException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_unless($provider instanceof XoalaProvider, Response::HTTP_NOT_FOUND);

        $form = $provider->checkoutForm($record);

        return view('cashier-core::xoala.checkout', [
            'action' => $form['action'],
            'fields' => $form['fields'],
        ]);
    }
}
```

Create `resources/views/xoala/checkout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="origin">
    <title>{{ __('Redirecting to payment…') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; display: flex;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0;
               color: #1f2933; background: #f5f7fa; }
        .panel { text-align: center; padding: 2rem; }
        button { font: inherit; padding: 0.6rem 1.2rem; cursor: pointer; }
    </style>
</head>
<body>
<div class="panel">
    <p>{{ __('Redirecting you to the secure payment page…') }}</p>

    <form id="xoala-checkout" method="POST" action="{{ $action }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        {{-- The flow must still complete where scripts are blocked. --}}
        <noscript>
            <button type="submit">{{ __('Continue to payment') }}</button>
        </noscript>
    </form>
</div>

<script>document.getElementById('xoala-checkout').submit();</script>
</body>
</html>
```

- [ ] **Step 6: Run tests**

Run: `vendor/bin/pest --filter="XoalaCheckout|XoalaProvider"`
Expected: PASS

The stub route in `XoalaProviderTest`'s `beforeEach` now no-ops, because the service provider registers the real one. Both files must pass together.

- [ ] **Step 7: Commit**

```bash
git add routes/checkout.php src/Http/Controllers/XoalaCheckoutController.php resources/views/xoala/checkout.blade.php src/CashierCoreServiceProvider.php config/cashier-core.php tests/Feature/XoalaCheckoutControllerTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala hosted-checkout bridge

A signed, expiring GET page that renders the checkout form Xoala expects,
recomputing the checksum from the transaction row. Only a Pending deposit
renders: a settled one must not be re-presentable as payable.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: The callback endpoint

**Files:**
- Create: `src/Http/Controllers/Webhooks/XoalaWebhookController.php`
- Modify: `routes/webhooks.php`
- Modify: `src/Testing/WebhookSimulator.php`
- Test: `tests/Feature/Webhooks/XoalaWebhookTest.php`

**Interfaces:**
- Consumes: `XoalaProvider::verifyWebhookSignature()`, `XoalaSignatureService::forCallback()`.
- Produces: route `cashier.webhooks.xoala`; `WebhookSimulator::make('xoala', $payload, $connection)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Webhooks/XoalaWebhookTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cashier::fakeConnection('xoala', 'xoala', ['secure_key' => 'base-key']);
    Cashier::fakeConnection('xoala_second', 'xoala', ['secure_key' => 'second-key']);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function xoalaCallback(array $overrides = [], string $secureKey = 'base-key'): array
{
    $payload = array_merge([
        'paymentId' => '18608029',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'paymentBrand' => 'VISA',
        'paymentMode' => 'CC',
        'amount' => '50.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'timestamp' => '2026-09-09 12:00:00',
    ], $overrides);

    $payload['checksum'] = (new XoalaSignatureService('11344', $secureKey))->forCallback(
        (string) $payload['paymentId'],
        (string) $payload['merchantTransactionId'],
        (string) $payload['amount'],
        (string) $payload['transactionStatus'],
    );

    return $payload;
}

it('accepts a correctly signed JSON callback', function () {
    $this->postJson('/api/webhooks/xoala', xoalaCallback())
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('accepts a form-encoded callback', function () {
    $this->post('/api/webhooks/xoala', xoalaCallback())
        ->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('rejects a callback signed with an unknown key', function () {
    Event::fake([WebhookRejected::class]);

    $this->postJson('/api/webhooks/xoala', xoalaCallback(secureKey: 'not-ours'))
        ->assertForbidden();

    Queue::assertNothingPushed();
    Event::assertDispatched(WebhookRejected::class);
});

it('rejects a callback carrying no checksum', function () {
    $payload = xoalaCallback();
    unset($payload['checksum']);

    $this->postJson('/api/webhooks/xoala', $payload)->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a callback whose amount was altered after signing', function () {
    $payload = xoalaCallback();
    $payload['amount'] = '5000.00';

    $this->postJson('/api/webhooks/xoala', $payload)->assertForbidden();

    Queue::assertNothingPushed();
});

it('matches the second account by its own key', function () {
    $this->postJson('/api/webhooks/xoala', xoalaCallback(secureKey: 'second-key'))
        ->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn ($job) => $job->connection === 'xoala_second',
    );
});

it('acks a replayed delivery without dispatching it twice', function () {
    $payload = xoalaCallback();

    $this->postJson('/api/webhooks/xoala', $payload)->assertOk();
    $this->postJson('/api/webhooks/xoala', $payload)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);
});

it('builds a verifiable delivery through the simulator', function () {
    $delivery = Asciisd\CashierCore\Testing\WebhookSimulator::make('xoala', [
        'paymentId' => '99',
        'merchantTransactionId' => 'DEP-9',
        'amount' => '10.00',
        'transactionStatus' => 'Y',
        'status' => 'capturesuccess',
    ]);

    $this->post($delivery->uri, $delivery->payload)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});
```

Check `ProcessPaymentProviderWebhook`'s constructor property name before running — if the connection is not a public `$connection` property, adjust the closure in the "second account" test to match.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=XoalaWebhook`
Expected: FAIL — 404, the route does not exist.

- [ ] **Step 3: Write the controller and route**

Create `src/Http/Controllers/Webhooks/XoalaWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Services\Webhooks\WebhookRelay;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class XoalaWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'xoala';

    public function __invoke(
        Request $request,
        ConnectionRegistry $registry,
        ReplayGuard $replayGuard,
        WebhookRelay $relay,
    ): JsonResponse {
        /*
         * The platform's request-format section specifies
         * application/x-www-form-urlencoded, while the notification samples are
         * JSON with nested `result`, `card` and `customer` objects. The docs
         * commit to neither, so both are read.
         */
        $payload = $request->isJson() ? $request->json()->all() : $request->post();

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) ($payload['checksum'] ?? '');

            $matchedConnection = $this->connectionThatSigned($payload, $registry);

            if ($matchedConnection === null) {
                /*
                 * Logged with a redacted payload: a wrong secure key, a
                 * signature over the long status instead of the short one, and
                 * a rotated key all look identical without it, and the payer's
                 * details are not needed to tell them apart.
                 */
                PaymentLogger::providerWebhookSignatureInvalid(
                    self::DRIVER,
                    $signature,
                    (new PayloadRedactor)->redact($payload),
                );

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, $matchedConnection)) {
                // Duplicate delivery — ACK so Xoala stops retrying, process nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        $relay->maybeRelay(self::DRIVER, $matchedConnection, $payload, $request);

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose Xoala account signed this callback, or null.
     *
     * Every account posts to this one URL and the payload names no merchant, so
     * each account's secure key is tried in turn — a match is itself the proof
     * of which account sent it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function connectionThatSigned(array $payload, ConnectionRegistry $registry): ?string
    {
        foreach (Connections::forDriver(self::DRIVER) as $connection) {
            try {
                $provider = $registry->get($connection);
            } catch (PaymentProcessingException|ProcessorNotFoundException) {
                // An account whose credentials are not filled in yet. Skipping
                // keeps the accounts that are configured verifiable.
                continue;
            }

            if ($provider->verifyWebhookSignature($payload, (string) ($payload['checksum'] ?? ''))) {
                return $connection;
            }
        }

        return null;
    }
}
```

In `routes/webhooks.php`, add the import and the route:

```php
use Asciisd\CashierCore\Http\Controllers\Webhooks\XoalaWebhookController;
```

```php
Route::post('/xoala', XoalaWebhookController::class)->name('xoala');
```

- [ ] **Step 4: Add the simulator recipe**

In `src/Testing/WebhookSimulator.php`, add the import:

```php
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
```

Add to the `match ($driver)` block, next to `'payport'`:

```php
            'xoala' => self::xoala($uri, $payload, $config),
```

And the method:

```php
    /**
     * Xoala signs the SHORT status — `transactionStatus`, not the long `status`
     * a callback also carries.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function xoala(string $uri, array $payload, array $config): SignedWebhook
    {
        $service = new XoalaSignatureService(
            (string) ($config['member_id'] ?? ''),
            (string) ($config['secure_key'] ?? ''),
        );

        $payload['checksum'] = $service->forCallback(
            (string) ($payload['paymentId'] ?? ''),
            (string) ($payload['merchantTransactionId'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            (string) ($payload['transactionStatus'] ?? $payload['status'] ?? ''),
        );

        return new SignedWebhook($uri, $payload, [], 'form');
    }
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/pest --filter=XoalaWebhook`
Expected: PASS

- [ ] **Step 6: Run the whole suite**

Run: `vendor/bin/pest`
Expected: PASS — including `tests/Feature/Webhooks/RouteRegistrationTest.php`, which may assert on the registered route list and need `xoala` added.

- [ ] **Step 7: Commit**

```bash
git add src/Http/Controllers/Webhooks/XoalaWebhookController.php routes/webhooks.php src/Testing/WebhookSimulator.php tests/Feature/Webhooks/XoalaWebhookTest.php
git commit -m "$(cat <<'EOF'
feat: add the Xoala callback endpoint

Each account's secure key is tried in turn, so a match identifies the
sender. Both JSON and form-encoded bodies are read: the platform's request
format section and its notification samples disagree.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Documentation and the vendored gateway contract

**Files:**
- Modify: `config/cashier-core.php` — commented connection example
- Modify: `README.md` — bundled-driver list
- Modify: `resources/boost/skills/cashier-core-development/SKILL.md` — driver list
- Create: `.claude/skills/xoala/SKILL.md`
- Create: `.claude/skills/xoala/references/*.md`

**Interfaces:**
- Consumes: everything above. Produces no code.

- [ ] **Step 1: Add the connection example to config**

In `config/cashier-core.php`, inside the `connections` docblock after the MyFatoorah examples, add:

```
    | Xoala is a white-label of the Paymentz platform. Deposits go through
    | Standard Checkout, whose entry point is a browser form POST rather than a
    | URL — the package serves a signed bridge page that submits it, so nothing
    | in the host application has to render the form.
    |
    | 'xoala' => [
    |     'driver' => 'xoala',
    |     // Sandbox: https://secure-checkout-sandbox.xoala.com
    |     // Live:    https://secure-checkout.xoala.com
    |     'base_url' => env('XOALA_BASE_URL'),
    |     // Merchant id, assigned by Xoala. Authenticates every request.
    |     'member_id' => env('XOALA_MEMBER_ID'),
    |     // Generated in the Xoala dashboard. Signs every checksum, and is
    |     // never itself transmitted.
    |     'secure_key' => env('XOALA_SECURE_KEY'),
    |     // Required, and account-specific — the spec calls it "Merchant's
    |     // Partner name". It is the second field of the request checksum, so
    |     // a wrong value fails the payment at the hosted page with no useful
    |     // message. The provider refuses to resolve without it.
    |     'totype' => env('XOALA_TOTYPE'),
    |     // Optional. Required on some account shapes ("Conditional" in the
    |     // spec); sent only when set.
    |     'terminal_id' => env('XOALA_TERMINAL_ID'),
    |     // Optional. Restricts the hosted page to one method — e.g. CC.
    |     // Unset shows every method the account has enabled.
    |     'payment_mode' => env('XOALA_PAYMENT_MODE'),
    |     'payment_brand' => env('XOALA_PAYMENT_BRAND'),
    |     // Optional. DB authorizes and captures in one step, which is what a
    |     // deposit wants; PA leaves the funds held and uncaptured.
    |     'transaction_type' => env('XOALA_TRANSACTION_TYPE', 'DB'),
    |     // Optional. The currency this connection invoices in, declared to
    |     // the engine before the charge so it can price a converted leg.
    |     'currency' => env('XOALA_CURRENCY'),
    |     // Optional — these fall back to the `payment.success` and
    |     // `cashier.webhooks.xoala` routes where the host defines them.
    |     'redirect_url' => env('XOALA_REDIRECT_URL'),
    |     'webhook_url' => env('XOALA_WEBHOOK_URL'),
    |     // Optional. Hosted page language; defaults to the app locale.
    |     'language' => env('XOALA_LANGUAGE'),
    | ],
    |
    | A second Xoala merchant account is another connection on the same driver.
    | Both post to the one `cashier.webhooks.xoala` URL and the payload names
    | no merchant, so the sender is identified by whose secure key verifies the
    | checksum.
    |
```

- [ ] **Step 2: Update the driver lists**

In `README.md` line ~14, add Xoala to the bundled-driver sentence, and add a short paragraph near the MyFatoorah one:

```markdown
Xoala (a white-label of the Paymentz platform) covers card and alternative
rails through its hosted Standard Checkout. Its checkout is entered by a form
POST rather than a URL, so the package serves a signed bridge page at
`{prefix}/xoala/checkout/{id}` that submits the signed field set for you —
nothing in the host application has to render it.
```

In `resources/boost/skills/cashier-core-development/SKILL.md`, add an `xoala`
connection example matching the config block above.

- [ ] **Step 3: Vendor the gateway contract as a skill**

Create `.claude/skills/xoala/SKILL.md` following `.claude/skills/myfatoorah/SKILL.md`'s structure, with frontmatter:

```markdown
---
name: xoala
description: Use when working with the Xoala payment gateway (checkout-docs.xoala.com, secure-checkout.xoala.com) — the Xoala driver, XoalaClient/XoalaProvider/XoalaAdapter/XoalaSignatureService, Xoala callbacks and checksums, or Xoala hosted Standard Checkout deposits, inquiry and status sync. Covers the Standard Checkout, REST and Invoice API flows of the underlying Paymentz platform.
---
```

**Where the material comes from.** The doc site loads every sample payload by
JavaScript, so `curl` on a page returns markup with empty sample blocks. Fetch
the prose pages and the samples separately:

```bash
# Prose pages — strip tags, keep the parameter tables.
for p in overview standard-checkout standard-checkout-specifications \
         rest-api-specifications transaction-AuthToken backoffice \
         asynchronous-workflow synchronous-workflow status \
         response-codes standard-checkout-response-codes payout; do
  curl -sL "https://checkout-docs.xoala.com/integration/$p.php" -o "$p.html"
done

# Every request and response sample, as JSON, keyed by flow
# (VISA_CC, IN, RF, RV, CP, PO, AUTH_TOKEN, GENERATE, StandardCheckout,
#  NOTI_CARD, FAIL_NOTI_CARD, NOTI_BANKTRANS, …).
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/sampleRequest
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/sampleResponse

# The brand's own host record — where the sandbox and live URLs come from.
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/getProperty/checkout-docs.xoala.com
```

Create `references/` holding, at minimum:

| File | Contents |
|---|---|
| `standard-checkout.md` | The hosted flow, the four checksum rules with the docs' worked example, request and response parameter tables. |
| `api-rest.md` | authToken, synchronous/asynchronous payments, backoffice (IN/CP/RF/RV), payout. Endpoints and parameter names. |
| `statuses.md` | The long status table (successful/pending/failed/cancelled/reversed/chargeback) and the short Y/N/P/3D/C form. |
| `callbacks.md` | Notification payload shapes, success and failure, card and bank-transfer variants. |
| `pitfalls.md` | The traps: form POST not a URL; short vs long status in the checksum; amount string formatting; `totype` inside the checksum; sample-response inconsistencies; JS-rendered docs. |
| `SOURCES.md` | Where each page came from, and the note that samples are served from `sandbox.paymentplug.com/transactionServices/REST/v2/sampleRequest` and `…/sampleResponse` rather than being in the page source. |

- [ ] **Step 4: Verify the suite still passes**

Run: `vendor/bin/pest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/cashier-core.php README.md resources/boost/skills/cashier-core-development/SKILL.md .claude/skills/xoala
git commit -m "$(cat <<'EOF'
docs: document the Xoala connection and vendor its gateway contract

The doc site renders every sample payload from JavaScript and blanks the
brand name throughout, so the contract is not readable from the URL alone.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Deviations from the spec

Recorded here so a reviewer can see them rather than discover them:

1. **`XoalaSignatureService::amount()`** — the spec describes the amount format as a rule but assigns it no home. It lives on the signature service as a static, because the format is a property of the digest (`50` and `50.00` hash differently), and the provider, the bridge and the tests all need the same one.
2. **A `username` config key** — the merchant authToken sample shows `merchant.username` alongside `authentication.sKey`, but the merchant-token page documents only the sKey. The client sends it when configured and omits it otherwise. This joins the spec's "Assumptions to confirm" list; confirm it in the sandbox before relying on `retrieve()`.
3. **The auth-token cache key.** Resolved before execution: `ConnectionRegistry::get()` hands a provider the raw connection config, which carries no connection name — so the originally planned `$config['connection'] ?? 'xoala'` would have been the same constant for every account, handing one merchant's token to another's inquiry. The key is `md5(base_url|member_id)` instead, which is per-account by construction and lets two connections onto one Xoala account share a token correctly.
