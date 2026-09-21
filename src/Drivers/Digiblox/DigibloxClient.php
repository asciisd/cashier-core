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
