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
            $response = PspHttp::client()
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
            $response = PspHttp::client()
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
