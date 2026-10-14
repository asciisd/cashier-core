<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * PayCrossBorder's HTTP surface: endpoints and auth, no business rules.
 *
 * Login is an OAuth2 password grant whose token is cached until a minute
 * before `expires_in`. Every call also carries the channel / source /
 * Accept-Language headers the docs mark mandatory. Success is HTTP 2xx AND
 * `resultCode === "0"` — PCB reports many failures inside a 200.
 *
 * payout() returns the raw response and is never retried: PCB has no
 * idempotency key, so a replayed POST could pay twice. The caller tells a
 * rejection from an unknown outcome.
 */
final class PayCrossBorderClient
{
    private const TOKEN_PATH = '/ewallet/oauth/token';

    private const API = '/ewallet/api/v1';

    private const HEADERS = [
        'channel' => 'WEB',
        'source' => 'AGENT',
        'Accept-Language' => 'en',
    ];

    /** Seconds shaved off `expires_in`, so a cached token never dies mid-call. */
    private const TOKEN_MARGIN = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $passwordHash = null,
        private readonly ?string $oauthClientId = null,
        private readonly ?string $oauthClientSecret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.*` array
     *
     * @throws PaymentProcessingException on an unsupported password_hash
     */
    public static function fromConfig(array $config): self
    {
        $hash = strtolower(trim((string) ($config['password_hash'] ?? '')));

        if (! in_array($hash, ['', 'md5'], true)) {
            throw new PaymentProcessingException("PayCrossBorder password_hash must be null or 'md5', not '{$hash}'.");
        }

        $clientId = trim((string) ($config['oauth_client_id'] ?? ''));

        return new self(
            baseUrl: rtrim((string) ($config['base_url'] ?? ''), '/'),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            passwordHash: $hash === '' ? null : $hash,
            oauthClientId: $clientId === '' ? null : $clientId,
            oauthClientSecret: (string) ($config['oauth_client_secret'] ?? ''),
        );
    }

    /**
     * @throws PaymentProcessingException when login fails or times out
     */
    public function token(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $request = PspHttp::client()->asForm()->acceptJson()->withHeaders(self::HEADERS);

        if ($this->oauthClientId !== null) {
            $request = $request->withBasicAuth($this->oauthClientId, (string) $this->oauthClientSecret);
        }

        try {
            $response = $request->post($this->baseUrl.self::TOKEN_PATH, [
                'grant_type' => 'password',
                'scope' => 'read write',
                'username' => $this->username,
                'password' => $this->loginPassword(),
            ]);
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("Could not reach PayCrossBorder to log in: {$e->getMessage()}");
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            throw new PaymentProcessingException(sprintf(
                'Could not authenticate with PayCrossBorder: HTTP %d %s',
                $response->status(),
                self::describe($response),
            ));
        }

        $ttl = max(self::TOKEN_MARGIN, (int) $response->json('expires_in', 0) - self::TOKEN_MARGIN);

        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    public function tokenCacheKey(): string
    {
        return 'cashier:paycrossborder:token:'.md5($this->baseUrl.'|'.$this->username);
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function quote(array $body): array
    {
        try {
            $response = $this->writer()->post($this->baseUrl.self::API.'/fxratequotation/api', $body);
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("PayCrossBorder quote failed: {$e->getMessage()}");
        }

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        return $this->accepted('quote', $response);
    }

    /**
     * @param  array<string, mixed>  $body  the payout object, without the `payout` wrapper
     *
     * @throws ConnectionException when PCB did not answer — the payout may exist
     * @throws PaymentProcessingException when login fails (nothing was sent)
     */
    public function payout(array $body): Response
    {
        $response = $this->writer()->post($this->baseUrl.self::API.'/payoutProcess/api', ['payout' => $body]);

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function transaction(string $transId): array
    {
        return $this->read('transaction enquiry', $this->baseUrl.self::API.'/transactionInfo/api?'.http_build_query([
            'types' => 'all',
            'status' => 'all',
            'transId' => $transId,
        ]));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function balance(string $walletOwnerCode): array
    {
        return $this->read('balance', $this->baseUrl.self::API.'/wallet/walletOwner/'.rawurlencode($walletOwnerCode));
    }

    /**
     * A short, log-safe description of a PCB answer: its resultCode and
     * description when it has them, else the start of the body.
     */
    public static function describe(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $code = $json['resultCode'] ?? null;
            $text = $json['resultDescription'] ?? $json['error_description'] ?? $json['message'] ?? $json['error'] ?? null;

            $parts = array_values(array_filter([
                is_scalar($code) && (string) $code !== '' ? 'resultCode '.$code : null,
                is_string($text) && $text !== '' ? $text : null,
            ]));

            if ($parts !== []) {
                return implode(': ', $parts);
            }
        }

        $body = trim($response->body());

        return $body === '' ? '(empty body)' : mb_substr($body, 0, 200);
    }

    /**
     * Read-only GET: retried once with a fresh token after a 401.
     *
     * @return array<string, mixed>
     */
    private function read(string $what, string $url): array
    {
        try {
            $response = $this->reader()->get($url);

            if ($response->status() === 401) {
                $this->forgetToken();
                $response = $this->reader()->get($url);
            }
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("PayCrossBorder {$what} failed: {$e->getMessage()}");
        }

        return $this->accepted($what, $response);
    }

    /**
     * @return array<string, mixed>
     */
    private function accepted(string $what, Response $response): array
    {
        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || (string) ($json['resultCode'] ?? '') !== '0') {
            throw new PaymentProcessingException(sprintf(
                'PayCrossBorder %s failed: HTTP %d %s',
                $what,
                $response->status(),
                self::describe($response),
            ));
        }

        return $json;
    }

    private function writer(): PendingRequest
    {
        return PspHttp::client()->timeout(30)->withToken($this->token())->acceptJson()->withHeaders(self::HEADERS);
    }

    private function reader(): PendingRequest
    {
        return PspHttp::idempotent()->timeout(30)->withToken($this->token())->acceptJson()->withHeaders(self::HEADERS);
    }

    /**
     * The docs show the login password as an uppercase hex digest. Until PCB
     * confirms whether it is issued that way or must be derived, both work.
     */
    private function loginPassword(): string
    {
        return $this->passwordHash === 'md5' ? strtoupper(md5($this->password)) : $this->password;
    }
}
