# Digiblox Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `digiblox` driver to `cashier-core` covering crypto deposits (hosted payment link + widget handoff + webhook + reconciliation) and crypto withdrawals (Centralized Transfer + status polling).

**Architecture:** Four classes under `src/Drivers/Digiblox/`, mirroring the existing `Heropayment` and `Xoala` drivers. `DigibloxClient` owns the HTTP surface and the cached JWT; `DigibloxAdapter` is pure logic — payload assembly, the three status vocabularies, and the reconciliation rule; `DigibloxProvider` implements `PaymentProcessorInterface`; `DigibloxTransferService` owns withdrawals and their safety rules. A webhook controller and route follow the bundled pattern. `charge()` performs one HTTP call and returns a redirect URL — the widget handles everything after that, so there is no server-to-server settlement leg.

**Tech Stack:** PHP 8.3, Laravel 11/12/13, Pest, `Illuminate\Support\Facades\Http` (via `Asciisd\CashierCore\Support\PspHttp`), `Illuminate\Support\Facades\Cache`.

**Spec:** `digiblox_docs/DIGIBLOX-INTEGRATION.md` — read §1–§6 and §10–§16 before starting. Every assertion below traces to a numbered section there.

## Global Constraints

Copied verbatim from the spec. Every task's requirements implicitly include this section.

- **Base URL** is `https://app.digiblox.io`; the deposit API base is `https://app.digiblox.io/gateway/api/v1/v3`. Use the `/gateway/api/v1/v3/…` path form, **never** `/gateway/api/v3/…` — the latter is browser-session auth and rejects a valid JWT with 401. (§1)
- **Payment link creation returns `201 Created`, not 200.** Transfer creation returns `202 Accepted`. A strict `=== 200` check treats success as failure. (§3.3, §6.1)
- **`fiat_amount` must be a string with exactly 2 decimals** — `"150.00"`. `"150"` and `"150.5"` are rejected. (§3.3)
- **The `notification` object is required even when empty** — send `"notification": {}`. (§3.3)
- **Reconcile on `total_amount`, never `amount`.** `amount` is net of the platform fee; reconciling against it makes correct payments look short by exactly the fee. (§4, §16)
- **Deduplicate on `tx_hash`, never `external_transaction_id`.** One payment link can legitimately produce several payments, each with its own hash. (§4)
- **`merchant_id`, `user_id` and every `*_id` are opaque encrypted strings, never integers.** Pass back verbatim. The one exception is `external_id` / `external_transaction_id`, which is our own plain-text reference. (§1)
- **Branch three ways on status, never string-match.** `CONFIRMED` → success, `REJECTED`/`REJECTED_BY_ADMIN` → failure, **everything else, including unknown values → pending**. Same rule for transfers with `FAILED`/`REJECTED`/`DROPPED`/`EXPIRED`. (§5, §6.2)
- **The webhook says `COMPLETED`; the deposits API says `CONFIRMED`.** Different axes — a reconciliation verdict vs a lifecycle state. Never compare the two strings. (§5)
- **Always iterate the `result` array**; never assume `result[0]`. (§5)
- **Return 2xx to every webhook**, including ones we cannot match to an order. A non-2xx burns one of only three retries and the delivery is abandoned. (§4)
- **The transfer API has no idempotency key.** A second POST after the first advances past `QUEUED` creates an independent second withdrawal. Poll status before any retry. (§6.1)
- **A newly minted JWT invalidates the previous one for that merchant.** Cache per connection and refresh under a lock, or concurrent workers will 401 each other. TTL is 1 hour. (§2)
- **The login endpoint returns a plain string, not JSON, on 401 and 500.** Never blindly decode a non-2xx login response. (§2)
- **No sandbox exists and we hold production credentials only.** Withdrawals move real money on the first run. `withdrawals_enabled` defaults to `false` and `withdrawal_max_amount` caps every transfer until the flow is proven.

---

## File Structure

| File | Responsibility |
|---|---|
| `src/Drivers/Digiblox/DigibloxAdapter.php` | Pure logic: the three status vocabularies, payment-link payload assembly, the `total_amount` reconciliation rule, `PaymentResult` construction. No HTTP, no framework facades. |
| `src/Drivers/Digiblox/DigibloxClient.php` | HTTP surface: cached JWT, guest check/create, payment-link create, deposits search. |
| `src/Drivers/Digiblox/DigibloxTransferService.php` | Withdrawals only: create, status poll, and the safety rules that exist because there is no idempotency key and no sandbox. |
| `src/Drivers/Digiblox/DigibloxProvider.php` | `PaymentProcessorInterface` + `ProvidesWebhookTransactionId`. Wires the three above together. |
| `src/Http/Controllers/Webhooks/DigibloxWebhookController.php` | Inbound deposit webhook: verify static header, ACK fast, queue the job. |
| `routes/webhooks.php` | One added `Route::post('/digiblox', …)`. |
| `src/CashierCoreServiceProvider.php` | One added entry in `BUNDLED_DRIVERS`. |
| `config/cashier-core.php` | Documented connection block in the `connections` docblock. |
| `tests/Unit/Drivers/DigibloxAdapterTest.php` | Status mapping, payload assembly, reconciliation. |
| `tests/Unit/Drivers/DigibloxClientTest.php` | JWT caching, link creation, deposits search — `Http::fake()`. |
| `tests/Unit/Drivers/DigibloxTransferServiceTest.php` | Transfer create/status and the safety guards. |
| `tests/Unit/Drivers/DigibloxProviderTest.php` | `charge()`, `retrieve()`, `supports()`, unsupported-method throws. |
| `tests/Feature/Webhooks/DigibloxWebhookTest.php` | End-to-end webhook: header auth, all three verdicts, unmatched order still 200. |

Tasks 1–2 build the adapter (no I/O, fastest feedback). Tasks 3–5 build the client. Task 6 wires the provider and registers the driver, at which point deposits work end to end. Task 7 adds the webhook. Tasks 8–9 add withdrawals. Task 10 adds Flow B.

---

### Task 1: Adapter — the three status vocabularies

The spec's single most dangerous trap: four disjoint status vocabularies describe the same money (§16). Three of them reach our code, and they mean different things. Each gets its own method so a caller cannot pass the wrong one.

**Files:**
- Create: `src/Drivers/Digiblox/DigibloxAdapter.php`
- Test: `tests/Unit/Drivers/DigibloxAdapterTest.php`

**Interfaces:**
- Consumes: `Asciisd\CashierCore\Enums\PaymentStatus`
- Produces: `DigibloxAdapter::mapDepositStatus(mixed $status): PaymentStatus`, `DigibloxAdapter::mapWebhookStatus(mixed $status): PaymentStatus`, `DigibloxAdapter::mapTransferStatus(mixed $status): PaymentStatus`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new DigibloxAdapter;
});

describe('mapDepositStatus', function () {
    it('maps CONFIRMED to Succeeded — the only status to fulfil on', function () {
        expect($this->adapter->mapDepositStatus('CONFIRMED'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps both rejection forms to Failed', function () {
        expect($this->adapter->mapDepositStatus('REJECTED'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapDepositStatus('REJECTED_BY_ADMIN'))->toBe(PaymentStatus::Failed);
    });

    it('maps every in-flight status to Pending', function () {
        foreach (['SENT_DEPOSIT_ADDRESS', 'SENT_TOKEN', 'PENDING', 'PUBLISHED'] as $status) {
            expect($this->adapter->mapDepositStatus($status))->toBe(PaymentStatus::Pending);
        }
    });

    it('maps an unknown status to Pending so a new status cannot break us', function () {
        expect($this->adapter->mapDepositStatus('SOMETHING_NEW'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapDepositStatus(null))->toBe(PaymentStatus::Pending);
    });
});

describe('mapWebhookStatus', function () {
    it('maps COMPLETED and OVERPAID to Succeeded — both are covered orders', function () {
        expect($this->adapter->mapWebhookStatus('COMPLETED'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapWebhookStatus('OVERPAID'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps PARTIALLY_PAID to OnHold — the funds are real, the order is not covered', function () {
        expect($this->adapter->mapWebhookStatus('PARTIALLY_PAID'))->toBe(PaymentStatus::OnHold);
    });

    it('maps an unknown verdict to OnHold, never to Succeeded', function () {
        expect($this->adapter->mapWebhookStatus('SOMETHING_NEW'))->toBe(PaymentStatus::OnHold);
    });

    it('does not treat the deposit vocabulary as a webhook verdict', function () {
        expect($this->adapter->mapWebhookStatus('CONFIRMED'))->toBe(PaymentStatus::OnHold);
    });
});

describe('mapTransferStatus', function () {
    it('maps CONFIRMED to Succeeded', function () {
        expect($this->adapter->mapTransferStatus('CONFIRMED'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps all four terminal failures to Failed', function () {
        foreach (['FAILED', 'REJECTED', 'DROPPED', 'EXPIRED'] as $status) {
            expect($this->adapter->mapTransferStatus($status))->toBe(PaymentStatus::Failed);
        }
    });

    it('maps FINALIZE to Pending — confirmed on-chain but not yet terminal', function () {
        expect($this->adapter->mapTransferStatus('FINALIZE'))->toBe(PaymentStatus::Pending);
    });

    it('maps every other in-progress status, and unknowns, to Pending', function () {
        foreach (['QUEUED', 'PENDING', 'ADMIN_APPROVED', 'SIGNATURE_PENDING', 'BLOCKCHAIN_PENDING', 'WHATEVER'] as $status) {
            expect($this->adapter->mapTransferStatus($status))->toBe(PaymentStatus::Pending);
        }
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxAdapterTest.php`
Expected: FAIL — `Class "Asciisd\CashierCore\Drivers\Digiblox\DigibloxAdapter" not found`

- [ ] **Step 3: Write the minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Enums\PaymentStatus;

/**
 * Payload assembly and status mapping for Digiblox.
 *
 * Digiblox exposes three disjoint status vocabularies for the same money, and
 * they are not interchangeable: the deposits API reports a *lifecycle state*,
 * the webhook reports a *reconciliation verdict about the amount*, and
 * transfers have a lifecycle of their own. A PARTIALLY_PAID webhook and a
 * CONFIRMED deposit describe the same payment. Never compare them; never share
 * a mapper between them.
 */
class DigibloxAdapter
{
    /**
     * Deposit lifecycle (GET /v3/deposits/merchant).
     *
     * Deliberately a three-way branch rather than an exhaustive match: any
     * status Digiblox adds later lands in Pending instead of breaking us.
     */
    public function mapDepositStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'CONFIRMED' => PaymentStatus::Succeeded,
            'REJECTED', 'REJECTED_BY_ADMIN' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Webhook reconciliation verdict.
     *
     * All three documented values mean money arrived. OVERPAID covers the order
     * and is fulfilled (the excess is settled separately); PARTIALLY_PAID does
     * not, and goes to review rather than to failure — the funds are real and
     * already credited. An unknown verdict must never reach Succeeded.
     */
    public function mapWebhookStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'COMPLETED', 'OVERPAID' => PaymentStatus::Succeeded,
            default => PaymentStatus::OnHold,
        };
    }

    /**
     * Centralized Transfer lifecycle. The spec warns this field is not a strict
     * enum, so anything unrecognised stays Pending and keeps being polled.
     */
    public function mapTransferStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtoupper((string) $providerStatus)) {
            'CONFIRMED' => PaymentStatus::Succeeded,
            'FAILED', 'REJECTED', 'DROPPED', 'EXPIRED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxAdapterTest.php`
Expected: PASS — 12 assertions across 3 describe blocks

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxAdapter.php tests/Unit/Drivers/DigibloxAdapterTest.php
git commit -m "feat(digiblox): map the three Digiblox status vocabularies"
```

---

### Task 2: Adapter — payment-link payload and the reconciliation rule

Four of the spec's validation traps are payload-shape traps (§3.3) and one is the fee trap (§4, demonstrated on live data in §16). All are pure functions, so all are cheap to test.

**Files:**
- Modify: `src/Drivers/Digiblox/DigibloxAdapter.php`
- Test: `tests/Unit/Drivers/DigibloxAdapterTest.php`

**Interfaces:**
- Consumes: `DigibloxAdapter` from Task 1
- Produces: `DigibloxAdapter::buildLinkPayload(array $data, array $config): array`, `DigibloxAdapter::formatFiatAmount(int|float|string $amount): string`, `DigibloxAdapter::isCovered(array $webhookPayload): bool`, `DigibloxAdapter::grossReceived(array $row): string`

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Drivers/DigibloxAdapterTest.php`:

```php
describe('formatFiatAmount', function () {
    it('always emits exactly two decimals', function () {
        expect($this->adapter->formatFiatAmount(150))->toBe('150.00');
        expect($this->adapter->formatFiatAmount(150.5))->toBe('150.50');
        expect($this->adapter->formatFiatAmount('7.5'))->toBe('7.50');
        expect($this->adapter->formatFiatAmount(0.1 + 0.2))->toBe('0.30');
    });
});

describe('buildLinkPayload', function () {
    $config = [
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'crypto_currency' => 'USDT',
        'network' => 'TRON',
        'success_url' => 'https://shop.test/ok',
        'fail_url' => 'https://shop.test/no',
    ];

    it('builds a Flow A payload with a two-decimal string amount', function () use ($config) {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 150, 'currency' => 'USD'],
            $config,
        );

        expect($payload['payload'])->toBe([
            'external_id' => 'DEP-1',
            'merchant_id' => 'bkE0RmNjbEhCUmc9',
            'payment_method' => 'CRYPTO_DEPOSIT',
            'fiat_currency' => 'USD',
            'fiat_amount' => '150.00',
            'crypto_currency' => 'USDT',
            'network' => 'TRON',
        ]);
    });

    it('always sends a notification object, empty when no urls are configured', function () {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            ['merchant_id' => 'M1'],
        );

        expect($payload)->toHaveKey('notification')
            ->and($payload['notification'])->toBe([]);
    });

    it('adds username only for Flow B', function () use ($config) {
        $flowA = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            $config,
        );
        $flowB = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-2', 'amount' => 10, 'currency' => 'USD', 'guest_email' => 'a@b.test'],
            $config,
        );

        expect($flowA['payload'])->not->toHaveKey('username')
            ->and($flowB['payload']['username'])->toBe('a@b.test');
    });

    it('omits network when no crypto_currency is configured, since network alone is rejected', function () {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            ['merchant_id' => 'M1', 'network' => 'TRON'],
        );

        expect($payload['payload'])->not->toHaveKey('network');
    });
});

describe('reconciliation', function () {
    it('reconciles on total_amount, not on the fee-netted amount', function () {
        // Figures from a real settled deposit on this account.
        // Reconciling on `amount` would make this look 75.12 USDT short.
        $payload = [
            'expected_amount' => '15023.830599907475',
            'total_amount' => '15023.830598999999',
            'amount' => '14948.711446005',
            'status' => 'COMPLETED',
        ];

        expect($this->adapter->isCovered($payload))->toBeTrue();
    });

    it('does not treat a genuine underpayment as covered', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => '150.000000',
            'total_amount' => '120.000000',
            'amount' => '119.760000',
            'status' => 'PARTIALLY_PAID',
        ]))->toBeFalse();
    });

    it('treats an overpayment as covered', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => '150.000000',
            'total_amount' => '175.500000',
            'status' => 'OVERPAID',
        ]))->toBeTrue();
    });

    it('is not covered when no expected amount was recorded', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => null,
            'total_amount' => '10.000000',
        ]))->toBeFalse();
    });

    it('computes gross received as amount plus system_fee', function () {
        expect($this->adapter->grossReceived([
            'amount' => '14948.711446005',
            'system_fee' => '75.119152995',
        ]))->toBe('15023.830599000');
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxAdapterTest.php`
Expected: FAIL — `Call to undefined method … ::formatFiatAmount()`

- [ ] **Step 3: Write the implementation**

Add to `DigibloxAdapter`:

```php
    /**
     * Digiblox rejects "150" and "150.5" with `fiat_amount must have 2
     * decimals`. bcadd normalises binary-float artefacts (0.1 + 0.2) that
     * number_format would carry through.
     */
    public function formatFiatAmount(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Assemble the POST /v3/payments/guests body.
     *
     * @param  array<string, mixed>  $data    charge data
     * @param  array<string, mixed>  $config  the connection config
     * @return array{payload: array<string, mixed>, notification: array<string, string>}
     */
    public function buildLinkPayload(array $data, array $config): array
    {
        $cryptoCurrency = $config['crypto_currency'] ?? null;

        $payload = array_filter([
            'external_id' => (string) $data['external_id'],
            'merchant_id' => (string) ($config['merchant_id'] ?? ''),
            'payment_method' => 'CRYPTO_DEPOSIT',
            'fiat_currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
            'fiat_amount' => $this->formatFiatAmount($data['amount'] ?? 0),
            'crypto_currency' => $cryptoCurrency,
            // `network` without `crypto_currency` is rejected outright, so it
            // rides along with the asset or not at all.
            'network' => $cryptoCurrency ? ($config['network'] ?? null) : null,
            // Flow B. Absent for an anonymous guest — the widget identifies
            // the customer itself.
            'username' => $data['guest_email'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'payload' => $payload,
            // Required even when empty: omitting it fails with
            // `Invalid body - notification property is missing or invalid`.
            'notification' => array_filter([
                'success_url' => $config['success_url'] ?? null,
                'fail_url' => $config['fail_url'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /**
     * Was the order covered? Compares gross received against what was asked.
     *
     * `amount` is net of the platform fee — reconciling on it makes every
     * correct payment look short by exactly the fee. A deposit with no
     * recorded expectation is never covered: Digiblox deliberately refuses to
     * claim COMPLETED when it cannot verify the amount, and so do we.
     *
     * @param  array<string, mixed>  $webhookPayload
     */
    public function isCovered(array $webhookPayload): bool
    {
        $expected = $webhookPayload['expected_amount'] ?? null;

        if ($expected === null || (float) $expected <= 0.0) {
            return false;
        }

        return (float) ($webhookPayload['total_amount'] ?? 0) >= (float) $expected;
    }

    /**
     * Gross received = credited amount + platform fee. Both arrive already
     * decimal-converted, so Currency.decimals is reference only.
     *
     * @param  array<string, mixed>  $row
     */
    public function grossReceived(array $row): string
    {
        return bcadd(
            (string) ($row['amount'] ?? '0'),
            (string) ($row['system_fee'] ?? '0'),
            9,
        );
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxAdapterTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxAdapter.php tests/Unit/Drivers/DigibloxAdapterTest.php
git commit -m "feat(digiblox): build link payloads and reconcile on total_amount"
```

---

### Task 3: Client — the cached JWT

A new token invalidates the previous one for the same merchant (§2), so minting per request makes concurrent workers 401 each other. Cache it, key it per account, and refresh under a lock.

**Files:**
- Create: `src/Drivers/Digiblox/DigibloxClient.php`
- Test: `tests/Unit/Drivers/DigibloxClientTest.php`

**Interfaces:**
- Consumes: `Asciisd\CashierCore\Support\PspHttp`
- Produces: `DigibloxClient::__construct(string $baseUrl, string $username, string $apiKey, string $apiSecret, string $merchantId)`, `DigibloxClient::fromConfig(array $config): self`, `DigibloxClient::forgetToken(): void`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function digibloxClient(): DigibloxClient
{
    return new DigibloxClient(
        baseUrl: 'https://digiblox.test',
        username: 'merchant_alpha',
        apiKey: 'key',
        apiSecret: 'secret',
        merchantId: 'bkE0RmNjbEhCUmc9',
    );
}

function fakeLogin(string $token = 'jwt-token'): array
{
    return [
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response([
            'iss' => 'Crymbo-API',
            'iat' => 1785926400,
            'exp' => 1785930000,
            'type' => 'Bearer',
            'token' => $token,
        ]),
    ];
}

beforeEach(function () {
    Cache::flush();
});

it('exchanges credentials for a token and sends it as a bearer header', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['paymentLink' => 'https://widget.digiblox.io/widget/auth?paymentId=X'], 201,
        ),
    ]));

    digibloxClient()->createPaymentLink(['payload' => ['external_id' => 'DEP-1'], 'notification' => []]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/auth/login/jwt')
        && $request['username'] === 'merchant_alpha'
        && $request['api_key'] === 'key'
        && $request['api_secret'] === 'secret');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/guests')
        && $request->header('Authorization') === ['Bearer jwt-token']);
});

it('caches the token across calls rather than minting one per request', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();
    $client->createPaymentLink(['payload' => ['external_id' => 'A'], 'notification' => []]);
    $client->createPaymentLink(['payload' => ['external_id' => 'B'], 'notification' => []]);

    // A new token would have invalidated the first — exactly one login.
    Http::assertSentCount(3);
});

it('keys the token per merchant account so two connections cannot share one', function () {
    Http::fake(fakeLogin());

    $a = new DigibloxClient('https://digiblox.test', 'a', 'k', 's', 'M-A');
    $b = new DigibloxClient('https://digiblox.test', 'b', 'k', 's', 'M-B');

    expect($a->tokenCacheKey())->not->toBe($b->tokenCacheKey());
});

it('does not decode the plain-string body a failed login returns', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response('Invalid Credentials', 401),
    ]);

    expect(fn () => digibloxClient()->createPaymentLink(['payload' => [], 'notification' => []]))
        ->toThrow(Asciisd\CashierCore\Exceptions\PaymentProcessingException::class, 'authenticate');
});

it('forgets a cached token on demand', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();
    $client->createPaymentLink(['payload' => ['external_id' => 'A'], 'notification' => []]);
    $client->forgetToken();
    $client->createPaymentLink(['payload' => ['external_id' => 'B'], 'notification' => []]);

    Http::assertSentCount(4); // two logins, two creates
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxClientTest.php`
Expected: FAIL — `Class "…\DigibloxClient" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Support\Facades\Cache;

class DigibloxClient
{
    /**
     * Short of the documented hour, so a token cannot expire in flight between
     * our cache read and Digiblox's clock.
     */
    private const TOKEN_TTL_SECONDS = 3300;

    /**
     * The JWT-authenticated path form. The sibling `/gateway/api/v3/…` is the
     * same route under browser session auth and rejects a valid JWT with 401.
     */
    private const API_PREFIX = '/gateway/api/v1/v3';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $merchantId,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://app.digiblox.io'), '/'),
            username: (string) ($config['username'] ?? ''),
            apiKey: (string) ($config['api_key'] ?? ''),
            apiSecret: (string) ($config['api_secret'] ?? ''),
            merchantId: (string) ($config['merchant_id'] ?? ''),
        );
    }

    /**
     * Tokens are per merchant account and ConnectionRegistry hands this client
     * raw config with no connection name to key on, so the key is derived from
     * the account's own identifying fields. A shared key would hand one
     * account's token to another — and because minting a token invalidates the
     * previous one, that would log the other account out.
     */
    public function tokenCacheKey(): string
    {
        return 'cashier:digiblox:jwt:'.md5($this->baseUrl.'|'.$this->username.'|'.$this->merchantId);
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createPaymentLink(array $body): array
    {
        $response = PspHttp::client()
            ->withToken($this->authToken())
            ->acceptJson()
            ->post($this->baseUrl.self::API_PREFIX.'/payments/guests', $body);

        // 201 Created, not 200. A strict 200 check fails a good link.
        if ($response->status() !== 201) {
            throw new PaymentProcessingException(
                'Digiblox rejected the payment link: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        return (array) $response->json();
    }

    /**
     * Public because DigibloxTransferService reuses it. It must never mint its
     * own token: a new token invalidates the previous one for this merchant,
     * so two token-minting call sites would log each other out.
     */
    public function authToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = PspHttp::idempotent()
            ->acceptJson()
            ->post($this->baseUrl.'/gateway/api/v1/auth/login/jwt', [
                'username' => $this->username,
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ]);

        // On 401 and 500 this endpoint answers with a plain string, not JSON.
        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Could not authenticate with Digiblox: '.trim($response->body()),
            );
        }

        $token = (string) ($response->json('token') ?? '');

        if ($token === '') {
            throw new PaymentProcessingException('Digiblox returned no token.');
        }

        Cache::put($this->tokenCacheKey(), $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * Digiblox returns `message` as a string for single-rule failures and as an
     * array of strings for field validation. Surface every entry — there is
     * usually more than one.
     */
    private function errorMessage(mixed $json, string $fallback): string
    {
        $message = is_array($json) ? ($json['message'] ?? null) : null;

        return match (true) {
            is_array($message) => implode('; ', array_map('strval', $message)),
            is_string($message) && $message !== '' => $message,
            default => trim($fallback),
        };
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxClientTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxClient.php tests/Unit/Drivers/DigibloxClientTest.php
git commit -m "feat(digiblox): cache the JWT per merchant account"
```

---

### Task 4: Client — deposits search for reconciliation

`GET /v3/deposits/merchant` is the reconciliation surface (§5). Eight query parameters, of which exactly one ever changes. The response may hold several rows for one `external_id`, and `totalItems: 0` is a normal "not yet", not an error.

**Files:**
- Modify: `src/Drivers/Digiblox/DigibloxClient.php`
- Test: `tests/Unit/Drivers/DigibloxClientTest.php`

**Interfaces:**
- Consumes: `DigibloxClient` from Task 3
- Produces: `DigibloxClient::searchDeposits(string $externalId): array` returning the raw `result` list

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Drivers/DigibloxClientTest.php`:

```php
describe('searchDeposits', function () {
    it('sends the fixed query string, varying only fV', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
                ['totalItems' => 0, 'result' => []],
            ),
        ]));

        digibloxClient()->searchDeposits('DEP-42');

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_contains($request->url(), '/v3/deposits/merchant')
                && $query['fV'] === 'DEP-42'
                && $query['fB'] === 'external_transaction_id'
                && $query['fO'] === 'EQ'
                && $query['fT'] === 'S'
                && $query['sB'] === 'created_at'
                && $query['sD'] === 'desc';
        });
    });

    it('returns an empty list for totalItems 0 without treating it as an error', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
                ['totalItems' => 0, 'result' => []],
            ),
        ]));

        expect(digibloxClient()->searchDeposits('DEP-unknown'))->toBe([]);
    });

    it('returns every row, not just the first — one link can take several payments', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response([
                'totalItems' => 2,
                'result' => [
                    ['external_transaction_id' => 'DEP-1', 'status' => 'CONFIRMED', 'tx_hash' => '0xaaa'],
                    ['external_transaction_id' => 'DEP-1', 'status' => 'CONFIRMED', 'tx_hash' => '0xbbb'],
                ],
            ]),
        ]));

        $rows = digibloxClient()->searchDeposits('DEP-1');

        expect($rows)->toHaveCount(2)
            ->and(array_column($rows, 'tx_hash'))->toBe(['0xaaa', '0xbbb']);
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxClientTest.php --filter=searchDeposits`
Expected: FAIL — `Call to undefined method … ::searchDeposits()`

- [ ] **Step 3: Write the implementation**

Add to `DigibloxClient`:

```php
    /**
     * Reconciliation: every deposit recorded against one of our payment links.
     *
     * Only `fV` ever varies — the rest of the query string is a constant. An
     * empty result is the correct "nothing yet" signal, not an error, so it
     * returns [] rather than throwing.
     *
     * @return list<array<string, mixed>>
     */
    public function searchDeposits(string $externalId, int $limit = 25, int $offset = 0): array
    {
        $response = PspHttp::idempotent()
            ->withToken($this->authToken())
            ->acceptJson()
            ->get($this->baseUrl.self::API_PREFIX.'/deposits/merchant', [
                'limit' => $limit,
                'offset' => $offset,
                'sB' => 'created_at',
                'sD' => 'desc',
                'fB' => 'external_transaction_id',
                'fV' => $externalId,
                'fO' => 'EQ',
                'fT' => 'S',
            ]);

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox deposit lookup failed: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        return array_values((array) ($response->json('result') ?? []));
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxClientTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxClient.php tests/Unit/Drivers/DigibloxClientTest.php
git commit -m "feat(digiblox): search deposits for reconciliation"
```

---

### Task 5: Provider — charge, retrieve, and the unsupported operations

`charge()` makes one call and hands back a redirect URL. Refund, capture, authorize and void have no Digiblox endpoint and must throw rather than silently no-op (§10).

**Files:**
- Create: `src/Drivers/Digiblox/DigibloxProvider.php`
- Test: `tests/Unit/Drivers/DigibloxProviderTest.php`

**Interfaces:**
- Consumes: `DigibloxClient`, `DigibloxAdapter`
- Produces: `DigibloxProvider` implementing `PaymentProcessorInterface` and `ProvidesWebhookTransactionId`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function digibloxConfig(array $overrides = []): array
{
    return array_merge([
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'crypto_currency' => 'USDT',
        'network' => 'TRON',
    ], $overrides);
}

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['paymentLink' => 'https://widget.digiblox.io/widget/auth?paymentId=R1N0'], 201,
        ),
    ]);
});

it('refuses to construct without credentials', function () {
    expect(fn () => new DigibloxProvider(['base_url' => 'https://digiblox.test']))
        ->toThrow(PaymentProcessingException::class, 'not configured');
});

it('charges by returning the payment link as a redirect', function () {
    $result = (new DigibloxProvider(digibloxConfig()))
        ->charge(['amount' => 150, 'currency' => 'USD', 'external_id' => 'DEP-1']);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toBe('https://widget.digiblox.io/widget/auth?paymentId=R1N0');
});

it('mints an external id when the caller does not supply one', function () {
    $result = (new DigibloxProvider(digibloxConfig()))->charge(['amount' => 10, 'currency' => 'USD']);

    expect($result->transactionId)->toStartWith('DEP-');
});

it('reports the status of the most recent deposit row', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response([
            'totalItems' => 1,
            'result' => [[
                'external_transaction_id' => 'DEP-1',
                'status' => 'CONFIRMED',
                'amount' => '149.700000',
                'system_fee' => '0.300000',
                'currency' => 'USDT',
                'tx_hash' => '0xabc',
            ]],
        ]),
    ]);

    $provider = new DigibloxProvider(digibloxConfig());

    expect($provider->getPaymentStatus('DEP-1'))->toBe(PaymentStatus::Succeeded->value)
        ->and($provider->retrieve('DEP-1')->status)->toBe(PaymentStatus::Succeeded);
});

it('returns null from retrieve when no deposit exists yet', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
            ['totalItems' => 0, 'result' => []],
        ),
    ]);

    expect((new DigibloxProvider(digibloxConfig()))->retrieve('DEP-nothing'))->toBeNull();
});

it('throws on every operation Digiblox has no endpoint for', function () {
    $provider = new DigibloxProvider(digibloxConfig());

    expect(fn () => $provider->refund('DEP-1'))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->capture('DEP-1'))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->authorize([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->void('DEP-1'))->toThrow(PaymentProcessingException::class);
});

it('advertises only what it supports', function () {
    $provider = new DigibloxProvider(digibloxConfig());

    expect($provider->getName())->toBe('digiblox')
        ->and($provider->supports('charge'))->toBeTrue()
        ->and($provider->supports('webhook'))->toBeTrue()
        ->and($provider->supports('refund'))->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxProviderTest.php`
Expected: FAIL — `Class "…\DigibloxProvider" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Str;

/**
 * Digiblox (Crymbo Gateway v3) — hosted crypto deposits.
 *
 * charge() creates a payment link and hands the customer to the Digiblox
 * widget, which owns currency selection, the deposit address, pricing, the QR
 * code and chain monitoring. There is no server-to-server settlement leg: the
 * webhook is the only authoritative notice that money arrived.
 */
class DigibloxProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    private DigibloxClient $client;

    private DigibloxAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['api_key']) || empty($config['api_secret']) || empty($config['merchant_id'])) {
            throw new PaymentProcessingException('Digiblox provider is not configured.');
        }

        $this->client = DigibloxClient::fromConfig($config);
        $this->adapter = new DigibloxAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        // Plain text and ours — the only unencrypted identifier in the API, and
        // the key that ties the webhook back to the order.
        $externalId = (string) ($data['external_id'] ?? 'DEP-'.Str::ulid());

        $body = $this->adapter->buildLinkPayload(
            array_merge($data, ['external_id' => $externalId, 'amount' => $validated['amount']]),
            $this->config,
        );

        $response = $this->client->createPaymentLink($body);

        return new PaymentResult(
            success: true,
            transactionId: $externalId,
            status: PaymentStatus::Pending,
            amount: (int) round((float) $validated['amount']),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['paymentLink'] ?? null,
                'digiblox_external_id' => $externalId,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
        );
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $rows = $this->client->searchDeposits($transactionId);

        if ($rows === []) {
            // Not an error: no deposit has been recorded for this link yet.
            return null;
        }

        // Rows are sorted newest first. A link may hold several payments; the
        // caller reconciles the set, this reports the latest state.
        $row = $rows[0];

        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: $this->adapter->mapDepositStatus($row['status'] ?? null),
            amount: (int) round((float) ($row['expected_amount'] ?? 0)),
            currency: strtoupper((string) ($row['expected_currency'] ?? 'USD')),
            metadata: array_filter([
                'tx_hash' => $row['tx_hash'] ?? null,
                'gross_received' => $this->adapter->grossReceived($row),
                'deposit_rows' => count($rows),
            ], fn ($value) => $value !== null),
            processorResponse: $row,
        );
    }

    public function getPaymentStatus(string $transactionId): string
    {
        return ($this->retrieve($transactionId)?->status ?? PaymentStatus::Pending)->value;
    }

    public function validatePaymentData(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new PaymentProcessingException('Digiblox requires a positive amount.');
        }

        return ['amount' => $amount];
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    /**
     * Digiblox signs nothing. Authenticity rests entirely on the static header
     * registered with them and replayed on every delivery, which the webhook
     * controller checks. See the spec's open questions.
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        return isset($payload['external_transaction_id'])
            ? (string) $payload['external_transaction_id']
            : null;
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new PaymentProcessingException('Digiblox has no refund endpoint; settle the excess manually.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support void.');
    }

    public function getName(): string
    {
        return 'digiblox';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }
}
```

- [ ] **Step 3b: Add `fromWebhook` to the adapter**

`parseWebhook` needs it. Add to `DigibloxAdapter`:

```php
    /**
     * Turn a deposit webhook into a transaction update.
     *
     * Reconciliation runs on total_amount. PARTIALLY_PAID becomes OnHold, not
     * Failed: the funds are real and already credited, the order simply is not
     * covered.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->mapWebhookStatus($payload['status'] ?? null);

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            metadata: array_filter([
                'tx_hash' => $payload['tx_hash'] ?? null,
                'network' => $payload['network'] ?? null,
                'from_address' => $payload['from_address'] ?? null,
                'to_address' => $payload['to_address'] ?? null,
                'expected_amount' => $payload['expected_amount'] ?? null,
                'total_amount' => $payload['total_amount'] ?? null,
                'covered' => $this->isCovered($payload),
            ], fn ($value) => $value !== null),
            errorMessage: $status === PaymentStatus::OnHold
                ? 'Digiblox reported '.((string) ($payload['status'] ?? 'an unknown status')).' — held for review.'
                : null,
            // The gross the payer sent, not the fee-netted credit.
            amount: isset($payload['total_amount']) ? (float) $payload['total_amount'] : null,
            currency: isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
        );
    }
```

Add the imports `use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;` to `DigibloxAdapter`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxProviderTest.php tests/Unit/Drivers/DigibloxAdapterTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/ tests/Unit/Drivers/DigibloxProviderTest.php
git commit -m "feat(digiblox): add the provider with charge and retrieve"
```

---

### Task 6: Register the driver and document the connection

Nothing resolves the driver until it is in `BUNDLED_DRIVERS`. The config docblock is the only place a host learns which keys exist.

**Files:**
- Modify: `src/CashierCoreServiceProvider.php:29-39`
- Modify: `config/cashier-core.php` (the `connections` docblock, near the APS example at lines 32-65)
- Test: `tests/Unit/Drivers/DigibloxProviderTest.php`

**Interfaces:**
- Consumes: `DigibloxProvider` from Task 5
- Produces: the `'digiblox'` driver key, resolvable through `ConnectionRegistry::get('digiblox')`

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Drivers/DigibloxProviderTest.php`:

```php
it('resolves through the connection registry', function () {
    config()->set('cashier-core.connections.digiblox', array_merge(
        digibloxConfig(),
        ['driver' => 'digiblox'],
    ));

    $provider = app(Asciisd\CashierCore\Connections\ConnectionRegistry::class)->get('digiblox');

    expect($provider)->toBeInstanceOf(DigibloxProvider::class)
        ->and($provider->getName())->toBe('digiblox');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxProviderTest.php --filter=registry`
Expected: FAIL — the driver key is not mapped

- [ ] **Step 3: Register the driver**

In `src/CashierCoreServiceProvider.php`, add to `BUNDLED_DRIVERS` keeping the existing order:

```php
        'digiblox' => Drivers\Digiblox\DigibloxProvider::class,
```

In `config/cashier-core.php`, add to the `connections` docblock after the APS examples:

```php
    | 'digiblox' => [
    |     'driver' => 'digiblox',
    |     'base_url' => env('DIGIBLOX_BASE_URL'),   // defaults to https://app.digiblox.io
    |     'username' => env('DIGIBLOX_USERNAME'),
    |     'api_key' => env('DIGIBLOX_API_KEY'),
    |     'api_secret' => env('DIGIBLOX_API_SECRET'),
    |     // The encrypted merchant token Digiblox issued. A raw integer fails
    |     // to decrypt and is rejected as `merchant_id must be a string`.
    |     'merchant_id' => env('DIGIBLOX_MERCHANT_ID'),
    |     // Pinning both lands the widget straight on the QR code. Omitting
    |     // crypto_currency also drops network — network alone is rejected.
    |     'crypto_currency' => env('DIGIBLOX_CRYPTO_CURRENCY', 'USDT'),
    |     'network' => env('DIGIBLOX_NETWORK', 'TRON'),
    |     'success_url' => env('DIGIBLOX_SUCCESS_URL'),
    |     'fail_url' => env('DIGIBLOX_FAIL_URL'),
    |     // Digiblox signs nothing. Authenticity is the static header you
    |     // registered with them, replayed on every delivery.
    |     'webhook_header_name' => env('DIGIBLOX_WEBHOOK_HEADER_NAME'),
    |     'webhook_header_value' => env('DIGIBLOX_WEBHOOK_HEADER_VALUE'),
    |     // Withdrawals move real money and there is no sandbox. Off until the
    |     // flow is proven; every transfer is capped at withdrawal_max_amount.
    |     'withdrawals_enabled' => env('DIGIBLOX_WITHDRAWALS_ENABLED', false),
    |     'withdrawal_max_amount' => env('DIGIBLOX_WITHDRAWAL_MAX_AMOUNT', '100'),
    | ],
    |
```

- [ ] **Step 4: Run the full suite to verify nothing regressed**

Run: `vendor/bin/pest`
Expected: PASS — including the new registry test

- [ ] **Step 5: Commit**

```bash
git add src/CashierCoreServiceProvider.php config/cashier-core.php tests/Unit/Drivers/DigibloxProviderTest.php
git commit -m "feat(digiblox): register the driver and document its connection"
```

---

### Task 7: The deposit webhook

The authoritative notice that money arrived (§4). Three rules dominate: verify the static header, answer 2xx to everything we accept including unmatched orders, and deduplicate on `tx_hash`.

**Files:**
- Create: `src/Http/Controllers/Webhooks/DigibloxWebhookController.php`
- Modify: `routes/webhooks.php`
- Test: `tests/Feature/Webhooks/DigibloxWebhookTest.php`

**Interfaces:**
- Consumes: `DigibloxProvider::parseWebhook()`, `ConnectionRegistry`, `ReplayGuard`, `WebhookRelay`, `ProcessPaymentProviderWebhook`
- Produces: the `webhooks.digiblox` route

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Illuminate\Support\Facades\Queue;

function digibloxWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'tx_hash' => '0x9c2f4b81e0a7d3c5f6b2a9184e7d0c3b5a8f1e6d4c2b7a90',
        'from_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'to_address' => '0x0e8091C125FFc084cf4546218b1fB3700F4C6AE0',
        'currency' => 'USDT',
        'network' => 'ETHEREUM',
        'amount' => '149.700000',
        'confirmed' => true,
        'external_transaction_id' => 'DEP-1',
        'expected_amount' => '150.000000',
        'total_amount' => '150.000000',
        'status' => 'COMPLETED',
    ], $overrides);
}

beforeEach(function () {
    Queue::fake();

    config()->set('cashier-core.connections.digiblox', [
        'driver' => 'digiblox',
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
        'webhook_header_name' => 'X-Digiblox-Token',
        'webhook_header_value' => 'shared-secret',
    ]);
});

it('accepts a delivery carrying the registered header', function () {
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload(), [
        'X-Digiblox-Token' => 'shared-secret',
    ])->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('rejects a delivery with a wrong or missing header', function () {
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload(), [
        'X-Digiblox-Token' => 'wrong',
    ])->assertForbidden();

    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload())->assertForbidden();

    Queue::assertNothingPushed();
});

it('answers 200 to a delivery it cannot match, rather than burning a retry', function () {
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload([
        'external_transaction_id' => 'DEP-nobody-knows',
    ]), ['X-Digiblox-Token' => 'shared-secret'])->assertOk();
});

it('deduplicates on tx_hash, so a second payment on one link is not dropped', function () {
    $headers = ['X-Digiblox-Token' => 'shared-secret'];

    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xaaa']), $headers)->assertOk();
    // Same order, different hash — a genuine second payment.
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xbbb']), $headers)->assertOk();
    // Same hash as the first — a retry.
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xaaa']), $headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 2);
});

it('accepts all three verdicts', function (string $status) {
    $this->postJson(route('webhooks.digiblox'), digibloxWebhookPayload([
        'status' => $status,
        'tx_hash' => '0x'.md5($status),
    ]), ['X-Digiblox-Token' => 'shared-secret'])->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
})->with(['COMPLETED', 'PARTIALLY_PAID', 'OVERPAID']);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Webhooks/DigibloxWebhookTest.php`
Expected: FAIL — `Route [webhooks.digiblox] not defined`

- [ ] **Step 3: Write the controller and add the route**

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Services\Webhooks\WebhookRelay;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Digiblox deposit webhook.
 *
 * Digiblox signs nothing — authenticity rests on a static header registered
 * with them and replayed on every delivery. Deliveries are capped at three
 * attempts with a ten-second total timeout, so this ACKs before any business
 * logic and leaves the work to the queue.
 */
class DigibloxWebhookController extends Controller
{
    private const DRIVER = 'digiblox';

    public function __invoke(
        Request $request,
        ReplayGuard $replayGuard,
        WebhookRelay $relay,
    ): JsonResponse {
        $config = (array) config('cashier-core.connections.'.self::DRIVER, []);

        $headerName = (string) ($config['webhook_header_name'] ?? '');
        $expected = (string) ($config['webhook_header_value'] ?? '');

        if ($headerName !== '' && $expected !== '') {
            $presented = (string) $request->header($headerName, '');

            if (! hash_equals($expected, $presented)) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $presented);

                WebhookRejected::dispatch(self::DRIVER, 'invalid header', $request->ip());

                return response()->json(['error' => 'Invalid credentials'], 403);
            }
        }

        $payload = $request->json()->all();

        // Key on tx_hash, never on external_transaction_id: one payment link
        // can legitimately take several payments, each its own delivery with
        // the same order id. Keying on the order would silently drop the
        // second genuine payment.
        $txHash = (string) ($payload['tx_hash'] ?? '');

        if ($txHash !== '' && ! $replayGuard->claim(self::DRIVER, $txHash, $txHash, self::DRIVER)) {
            // Duplicate delivery — ACK so Digiblox stops retrying.
            return response()->json(['status' => 'ok']);
        }

        $relay->maybeRelay(self::DRIVER, self::DRIVER, $payload, $request);

        // Queued, not processed inline: an unmatched order must still be
        // stored and answered 200. A non-2xx burns one of only three retries
        // and the delivery is then abandoned — we would lose the data.
        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);

        WebhookReceived::dispatch(self::DRIVER, self::DRIVER, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }
}
```

In `routes/webhooks.php`, add the import and the route alongside the others:

```php
use Asciisd\CashierCore\Http\Controllers\Webhooks\DigibloxWebhookController;

Route::post('/digiblox', DigibloxWebhookController::class)->name('digiblox');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Webhooks/DigibloxWebhookTest.php`
Expected: PASS

If `ReplayGuard::claim()` has a different signature than assumed, read `src/Services/Webhooks/ReplayGuard.php` and match it — the intent is "claim once per `tx_hash`".

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controllers/Webhooks/DigibloxWebhookController.php routes/webhooks.php tests/Feature/Webhooks/DigibloxWebhookTest.php
git commit -m "feat(digiblox): accept deposit webhooks, deduplicated on tx_hash"
```

---

### Task 8: Transfer service — create a withdrawal safely

This is the task that moves real money with no sandbox and no idempotency key (§6.1). The safety rules are the deliverable as much as the HTTP call is.

**Files:**
- Create: `src/Drivers/Digiblox/DigibloxTransferService.php`
- Test: `tests/Unit/Drivers/DigibloxTransferServiceTest.php`

**Interfaces:**
- Consumes: `DigibloxClient` (for `authToken`), `DigibloxAdapter::mapTransferStatus()`
- Produces: `DigibloxTransferService::create(string $amount, string $toAddress, string $network, string $asset, ?string $note = null): array` returning `['id' => string, 'status' => string]`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxTransferService;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function transferService(array $overrides = []): DigibloxTransferService
{
    return new DigibloxTransferService(array_merge([
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '100',
    ], $overrides));
}

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => Http::response(
            ['id' => 'Qk1ZbFZkN2R3Z1E9', 'status' => 'QUEUED'], 202,
        ),
    ]);
});

it('refuses to send while withdrawals are disabled', function () {
    expect(fn () => transferService(['withdrawals_enabled' => false])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'disabled');

    Http::assertNothingSent();
});

it('refuses an amount above the configured cap', function () {
    expect(fn () => transferService()
        ->create('500', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'exceeds');
});

it('refuses a malformed amount, because the API does not', function () {
    foreach (['abc', '', '0', '-5'] as $amount) {
        expect(fn () => transferService()
            ->create($amount, '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
            ->toThrow(PaymentProcessingException::class);
    }
});

it('refuses an empty destination address, because the API does not validate it', function () {
    expect(fn () => transferService()->create('25', '  ', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'address');
});

it('accepts a 202 and returns the opaque transfer id', function () {
    $result = transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    expect($result['id'])->toBe('Qk1ZbFZkN2R3Z1E9')
        ->and($result['status'])->toBe('QUEUED');
});

it('sends the fixed reporting fields verbatim', function () {
    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['initial_rate'] === '1.00'
        && $request['initial_rate_currency_id'] === 'bkE0RmNjbEhCUmc9'
        && $request['amount'] === '25'
        && $request['network'] === 'ETHEREUM'
        && $request['asset'] === 'USDC');
});

it('carries our reference in user_note so treasury exports can be joined', function () {
    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC', 'WD-777');

    Http::assertSent(fn ($request) => $request['user_note'] === 'WD-777');
});

it('truncates a note to the documented 255 characters', function () {
    transferService()->create('25', '0xabc', 'ETHEREUM', 'USDC', str_repeat('x', 300));

    Http::assertSent(fn ($request) => strlen($request['user_note']) === 255);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxTransferServiceTest.php`
Expected: FAIL — `Class "…\DigibloxTransferService" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;

/**
 * Centralized Transfer — crypto withdrawals.
 *
 * Three facts shape this class. The endpoint has no idempotency key, so a
 * second POST after the first advances past QUEUED creates an independent
 * second withdrawal. There is no sandbox, so the first live run moves real
 * money. And the API validates neither the amount format nor the destination
 * address — malformed input is accepted, not rejected. Everything below is a
 * consequence of one of those three.
 */
class DigibloxTransferService
{
    /**
     * Fixed reporting values. The spec is explicit that these are used for
     * internal fiat-value reporting, are not re-derived by the platform, and
     * must be sent exactly as given — never computed.
     */
    private const INITIAL_RATE = '1.00';

    private const INITIAL_RATE_CURRENCY_ID = 'bkE0RmNjbEhCUmc9';

    private const MAX_NOTE_LENGTH = 255;

    private DigibloxClient $client;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $this->client = DigibloxClient::fromConfig($config);
    }

    /**
     * @return array{id: string, status: string}
     */
    public function create(
        string $amount,
        string $toAddress,
        string $network,
        string $asset,
        ?string $note = null,
    ): array {
        if (! (bool) ($this->config['withdrawals_enabled'] ?? false)) {
            throw new PaymentProcessingException(
                'Digiblox withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // The API accepts non-numeric and malformed amount strings without
        // complaint, so the guard has to live here.
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new PaymentProcessingException('Digiblox withdrawal amount must be a positive number.');
        }

        $cap = (float) ($this->config['withdrawal_max_amount'] ?? 0);

        if ($cap > 0 && (float) $amount > $cap) {
            throw new PaymentProcessingException(
                sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap),
            );
        }

        // Likewise: the API performs no address format or checksum check.
        if (trim($toAddress) === '') {
            throw new PaymentProcessingException('Digiblox withdrawal requires a destination address.');
        }

        $body = array_filter([
            'amount' => $amount,
            'toAddress' => trim($toAddress),
            'network' => strtoupper($network),
            'asset' => strtoupper($asset),
            'initial_rate' => self::INITIAL_RATE,
            'initial_rate_currency_id' => self::INITIAL_RATE_CURRENCY_ID,
            // The one free-text field on the API, and the only way to carry our
            // own reference into Digiblox's treasury exports.
            'user_note' => $note !== null ? substr($note, 0, self::MAX_NOTE_LENGTH) : null,
        ], fn ($value) => $value !== null);

        $response = PspHttp::client()
            ->withToken($this->client->authToken())
            ->acceptJson()
            ->post($this->baseUrl().'/gateway/api/v1/v3/transfers/centralized', $body);

        // 202 Accepted, not 200.
        if ($response->status() !== 202) {
            throw new PaymentProcessingException(
                'Digiblox rejected the withdrawal: '.trim($response->body()),
            );
        }

        return [
            'id' => (string) ($response->json('id') ?? ''),
            'status' => (string) ($response->json('status') ?? 'QUEUED'),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://app.digiblox.io'), '/');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxTransferServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/ tests/Unit/Drivers/DigibloxTransferServiceTest.php
git commit -m "feat(digiblox): create centralized transfers behind safety guards"
```

---

### Task 9: Transfer service — status polling and the no-retry rule

The status endpoint drops the `/v3` segment that creation uses (§6.2). `FINALIZE` is the trap: confirmed on-chain but not terminal.

**Files:**
- Modify: `src/Drivers/Digiblox/DigibloxTransferService.php`
- Test: `tests/Unit/Drivers/DigibloxTransferServiceTest.php`

**Interfaces:**
- Consumes: `DigibloxTransferService` from Task 8
- Produces: `DigibloxTransferService::status(string $transferId): array` returning `['status' => string, 'mapped' => PaymentStatus, 'tx_hash' => ?string, 'raw' => array]`

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Drivers/DigibloxTransferServiceTest.php`:

```php
// Top-level, not inside the describe: a function declared in a closure is
// redeclared globally each time the closure runs, which fatals on the second
// pass.
function fakeTransfer(string $status, ?string $txHash = null): array
{
    return [
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/transfers/*' => Http::response([
            'api_message' => 'TRANSFER_GET_SHOW_SUCCESS',
            'api_data' => ['transfer' => [
                'id' => 'Qk1ZbFZkN2R3Z1E9',
                'status' => $status,
                'amount' => '15.000000000000000000',
                'system_fee' => 0.2,
                'currency' => 'USDT',
                'tx_hash' => $txHash,
                'currency_decimals' => 18,
            ]],
        ]),
    ];
}

describe('status', function () {
    it('reads the transfer from the api_data envelope', function () {
        Http::fake(fakeTransfer('CONFIRMED', '0xabc'));

        $result = transferService()->status('Qk1ZbFZkN2R3Z1E9');

        expect($result['status'])->toBe('CONFIRMED')
            ->and($result['mapped'])->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Succeeded)
            ->and($result['tx_hash'])->toBe('0xabc');
    });

    it('uses the status path without the v3 segment that creation uses', function () {
        Http::fake(fakeTransfer('QUEUED'));

        transferService()->status('Qk1ZbFZkN2R3Z1E9');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/gateway/api/v1/transfers/Qk1ZbFZkN2R3Z1E9')
            && ! str_contains($request->url(), '/v3/transfers'));
    });

    it('keeps FINALIZE pending — confirmed on-chain is not yet terminal', function () {
        Http::fake(fakeTransfer('FINALIZE'));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Pending);
    });

    it('keeps an unlisted status pending rather than erroring out', function () {
        Http::fake(fakeTransfer('SOME_NEW_STATE'));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Pending);
    });

    it('reports a terminal failure for each documented failure state', function (string $status) {
        Http::fake(fakeTransfer($status));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Failed);
    })->with(['FAILED', 'REJECTED', 'DROPPED', 'EXPIRED']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxTransferServiceTest.php --filter=status`
Expected: FAIL — `Call to undefined method … ::status()`

- [ ] **Step 3: Write the implementation**

Add to `DigibloxTransferService`:

```php
    /**
     * Poll a transfer.
     *
     * Note the path: creation posts to /gateway/api/v1/v3/transfers/centralized
     * but the status route has no /v3 segment. That asymmetry is in the API,
     * not a typo here.
     *
     * Because there is no idempotency key, this is also the only safe way to
     * resolve an inconclusive create: poll before you retry, never retry blind.
     *
     * @return array{status: string, mapped: \Asciisd\CashierCore\Enums\PaymentStatus, tx_hash: ?string, raw: array<string, mixed>}
     */
    public function status(string $transferId): array
    {
        $response = PspHttp::idempotent()
            ->withToken($this->client->authToken())
            ->acceptJson()
            ->get($this->baseUrl().'/gateway/api/v1/transfers/'.$transferId);

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox transfer lookup failed: '.trim($response->body()),
            );
        }

        $transfer = (array) ($response->json('api_data.transfer') ?? []);
        $status = (string) ($transfer['status'] ?? '');

        return [
            'status' => $status,
            'mapped' => (new DigibloxAdapter)->mapTransferStatus($status),
            'tx_hash' => isset($transfer['tx_hash']) ? (string) $transfer['tx_hash'] : null,
            'raw' => $transfer,
        ];
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxTransferServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxTransferService.php tests/Unit/Drivers/DigibloxTransferServiceTest.php
git commit -m "feat(digiblox): poll centralized transfer status"
```

---

### Task 10: Flow B — the known guest

Pre-registering the customer lands them past the widget's identification step (§3.1, §3.2). Optional: Flow A works without it. Build it last so deposits ship first.

**Files:**
- Modify: `src/Drivers/Digiblox/DigibloxClient.php`
- Test: `tests/Unit/Drivers/DigibloxClientTest.php`

**Interfaces:**
- Consumes: `DigibloxClient`
- Produces: `DigibloxClient::checkGuestExists(string $email): array` returning `['exists' => bool, 'id' => ?string]`, `DigibloxClient::createGuestWithPii(string $email, array $pii): bool`

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Drivers/DigibloxClientTest.php`:

```php
describe('guest flow', function () {
    it('reports a missing guest as a normal 200, not an error', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/check-guest-exists' => Http::response(
                ['exists' => false, 'id' => null],
            ),
        ]));

        expect(digibloxClient()->checkGuestExists('nobody@test.dev'))
            ->toBe(['exists' => false, 'id' => null]);
    });

    it('returns the permanent user id for an existing guest', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/check-guest-exists' => Http::response(
                ['exists' => true, 'id' => 'V0FrTk5FR3NUeE9wL2lqcE9Rc2h2Zz09'],
            ),
        ]));

        expect(digibloxClient()->checkGuestExists('known@test.dev'))
            ->toBe(['exists' => true, 'id' => 'V0FrTk5FR3NUeE9wL2lqcE9Rc2h2Zz09']);
    });

    it('registers a guest with the email beside the pii object, not inside it', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/create-guest-with-pii' => Http::response(
                ['message' => 'user create/updated successfully'],
            ),
        ]));

        $created = digibloxClient()->createGuestWithPii('new@test.dev', [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dob' => '1990-01-01',
            'phone' => '+15551234567',
            'address' => '123 Main Street',
            'city' => 'New York',
            'country' => 'USA',
            'zipCode' => '10001',
        ]);

        expect($created)->toBeTrue();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/auth/create-guest-with-pii')
            && $request['email'] === 'new@test.dev'
            && $request['pii']['country'] === 'USA'
            && ! isset($request['pii']['email']));
    });

    it('surfaces every entry when field validation returns an array of messages', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/create-guest-with-pii' => Http::response([
                'message' => ['firstName string is required', 'lastName string is required'],
            ], 400),
        ]));

        expect(fn () => digibloxClient()->createGuestWithPii('bad@test.dev', []))
            ->toThrow(
                Asciisd\CashierCore\Exceptions\PaymentProcessingException::class,
                'firstName string is required; lastName string is required',
            );
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/DigibloxClientTest.php --filter="guest flow"`
Expected: FAIL — `Call to undefined method … ::checkGuestExists()`

- [ ] **Step 3: Write the implementation**

Add to `DigibloxClient`:

```php
    /**
     * Look a guest up by email.
     *
     * There is deliberately no 404: a customer we have never seen is a normal
     * 200 with exists:false. Note this answers true only for accounts whose
     * type is `guest` — a full Digiblox account with that email also returns
     * false, and registering it as a guest is rejected later.
     *
     * @return array{exists: bool, id: ?string}
     */
    public function checkGuestExists(string $email): array
    {
        $response = PspHttp::idempotent()
            ->withToken($this->authToken())
            ->acceptJson()
            ->post($this->baseUrl.self::API_PREFIX.'/auth/check-guest-exists', ['username' => $email]);

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox guest lookup failed: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        $id = $response->json('id');

        return [
            'exists' => (bool) $response->json('exists'),
            'id' => $id === null ? null : (string) $id,
        ];
    }

    /**
     * Pre-register a guest. Safe to re-send: calling it again for an existing
     * guest updates the PII rather than failing, so there is no "already
     * exists" case to code around. It does not return the user_id.
     *
     * @param  array<string, string>  $pii
     */
    public function createGuestWithPii(string $email, array $pii): bool
    {
        $response = PspHttp::client()
            ->withToken($this->authToken())
            ->acceptJson()
            // `email` sits beside `pii`, not inside it.
            ->post($this->baseUrl.self::API_PREFIX.'/auth/create-guest-with-pii', [
                'email' => $email,
                'pii' => $pii,
            ]);

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox guest registration failed: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        return true;
    }
```

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Digiblox/DigibloxClient.php tests/Unit/Drivers/DigibloxClientTest.php
git commit -m "feat(digiblox): add the known-guest flow"
```

---

## Deliberately not implemented

Two documented surfaces are skipped on purpose — say so rather than leaving a
future reader to wonder whether they were missed:

- **`GET /v3/payments/guests/{id}` (§3.4)** returns the payment *link
  definition*, written once and never updated. It has no `status` field and
  never will. Everything it reports is already in our own record of what we
  submitted, so it earns nothing. `GET /v3/deposits/merchant` (Task 4) is the
  status surface.
- **The legacy widget flow (§8)** — merchant-side wallet-address generation,
  `/currencies`, `/orders/quotes`. Superseded by the payment link: under the
  current guide everything after the link happens inside the widget. Revisit
  only if we ever build our own deposit UI instead of handing off.

## Before the first production call

No sandbox exists, so the first live call is production. In order:

1. **Resolve the §12 contradictions with one call each.** Auth path (`/auth/login/jwt` vs `/jwt/generate`), deposits path (`/v3/deposits` vs `/v3/deposits/merchant`), and the `notification` vs `system` key. All three collapse the moment a real credential is used. Fix the code to match what answers.
2. **Deposits first.** Create one link at a trivial amount, confirm the 201 and the widget URL, and let it expire unpaid. Nothing moves.
3. **One real deposit** at minimum size. Confirm the webhook arrives, the static header is present, and `total_amount` reconciles.
4. **Withdrawals last.** Keep `withdrawals_enabled=false` until everything above passes. Then send one transfer at `withdrawal_max_amount` of a few units, with `user_note` set, and confirm it appears in the treasury export's `Note` column — which also answers the open question about whether `user_note` surfaces there.
5. **Never retry a transfer on an inconclusive response.** Poll `status()` first. There is no idempotency key; a blind retry sends the money twice.

## Open questions that block none of this

Each has a stated default in the spec (§13); none stops implementation:
webhook signing (we use the static header), sandbox (none), balance endpoint (none — rely on the `Insufficent funds` error), refunds (none — `refund()` throws), withdrawal fees and minimums in advance (read `system_fee` after the fact), rate limits, payment-link expiry.
