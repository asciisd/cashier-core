<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class DigibloxClient
{
    /**
     * Short of the documented hour, so a token cannot expire in flight between
     * our cache read and Digiblox's clock.
     */
    private const TOKEN_TTL_SECONDS = 3300;

    /**
     * How long a lock holder may hold the refresh lock before releasing it.
     */
    private const LOCK_HOLD_SECONDS = 10;

    /**
     * How long to wait for a lock before timing out and minting anyway.
     * Kept short so tests can exercise the timeout path without slowing the suite.
     */
    private const LOCK_WAIT_SECONDS = 1;

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

        // A superseded token (minted elsewhere for this merchant, e.g. an ops
        // login in the Digiblox dashboard) reads back as 401. Nothing else
        // invalidates our cache, so without this the wedged token fails every
        // call until its TTL expires; forgetting it lets the next call
        // re-mint. See XoalaClient for the same pattern.
        if ($response->status() === 401) {
            $this->forgetToken();
        }

        // 201 Created, not 200. A strict 200 check fails a good link.
        if ($response->status() !== 201) {
            // Per spec pitfall #13: a 500 may have created the link anyway, and
            // the same external_id must not be resent blind. A 4xx is a clean
            // rejection — nothing was created, safe to correct and retry.
            if ($response->status() >= 500) {
                throw new PaymentProcessingException(
                    sprintf(
                        'Digiblox returned a server error (%d) creating the payment link. The link may or may not '.
                        'exist — do NOT resend the same external_id blind. Response: %s',
                        $response->status(),
                        trim($response->body()),
                    ),
                );
            }

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
     *
     * Implements single-flight caching: the fast path returns a cached token
     * without taking a lock. On a miss, one worker acquires a refresh lock,
     * re-checks the cache (the lock holder before us may have just written),
     * and mints if still a miss. If a lock times out, the worker mints anyway
     * rather than failing a live payment — a wedged lock should never take down
     * a customer deposit.
     */
    public function authToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $lock = Cache::lock($this->tokenCacheKey().':refresh', self::LOCK_HOLD_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            // The lock holder is wedged or slow. Rather than failing a live
            // payment, mint a token anyway — a timeout is rare, and a failed
            // deposit is worse than a transient duplicate mint.
            return $this->mintToken();
        }

        try {
            // Re-read the cache: the lock holder before us has probably written
            // a token. This is the whole point of single-flight refresh.
            $cached = Cache::get($this->tokenCacheKey());

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            return $this->mintToken();
        } finally {
            $lock->release();
        }
    }

    /**
     * Mint a new JWT token and cache it.
     *
     * @throws PaymentProcessingException on auth failure or invalid response
     */
    private function mintToken(): string
    {
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

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox deposit lookup failed: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        return array_values((array) ($response->json('result') ?? []));
    }

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

        if ($response->status() === 401) {
            $this->forgetToken();
        }

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

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox guest registration failed: '.$this->errorMessage($response->json(), $response->body()),
            );
        }

        return true;
    }
}
