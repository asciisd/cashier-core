<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Aps;

use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class ApsClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $merchantGuid,
        private readonly string $appToken,
        private readonly string $appSecret,
        /**
         * APS issues a callback secret separately from the app secret. Falls back
         * to the app secret when unset, preserving the original behaviour.
         */
        private readonly ?string $callbackSecret = null,
    ) {}

    /**
     * Create a deposit transaction. Returns the raw APS response
     * (`id`, `amount_in`, `amount_out`, `how` checkout URL, ...).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createDeposit(array $payload): array
    {
        return $this->request()
            ->post("{$this->baseUrl}/api/v3/{$this->merchantGuid}/transactions", $payload)
            ->throw()
            ->json();
    }

    /**
     * Fetch a transaction by id. Returns null when APS does not know it.
     *
     * The transaction guid sits directly under the merchant guid — the same
     * shape the refund route uses. There is no `/transactions/{id}` collection
     * route: APS answers that path with a plain-text `404 page not found`,
     * which is indistinguishable from an unknown transaction once the status
     * is discarded, so any lookup failure is logged with its status here.
     *
     * @return array<string, mixed>|null
     */
    public function getTransaction(string $transactionId): ?array
    {
        $response = $this->idempotentRequest()
            ->get("{$this->baseUrl}/api/v3/{$this->merchantGuid}/{$transactionId}");

        if (! $response->successful()) {
            PaymentLogger::providerTransactionLookupFailed(
                'aps',
                $transactionId,
                $response->status(),
                Str::limit($response->body(), 500),
            );

            return null;
        }

        return $response->json();
    }

    /**
     * Refund a transaction (partial refunds supported).
     *
     * @param  array<string, mixed>  $payload
     */
    public function refund(string $transactionId, array $payload): Response
    {
        return $this->request()
            ->post("{$this->baseUrl}/api/v3/{$this->merchantGuid}/{$transactionId}/refund", $payload);
    }

    /**
     * Merchant configuration: available methods, limits, and currencies.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->idempotentRequest()
            ->get("{$this->baseUrl}/api/v3/{$this->merchantGuid}/info")
            ->throw()
            ->json();
    }

    /**
     * Verify an APS callback signature against the raw request body.
     *
     * Signed with the dedicated callback secret, NOT the app secret used for
     * outbound request auth. Where no callback secret is configured this falls
     * back to the app secret — which will reject every callback if APS actually
     * signs with the callback secret, so configure `APS_CALLBACK_SECRET`.
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $secret = $this->callbackSecret !== null && $this->callbackSecret !== ''
            ? $this->callbackSecret
            : $this->appSecret;

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function request(): PendingRequest
    {
        return $this->withAuth(PspHttp::client());
    }

    /**
     * For status/info lookups only — safely repeatable, so retries are allowed.
     */
    private function idempotentRequest(): PendingRequest
    {
        return $this->withAuth(PspHttp::idempotent());
    }

    private function withAuth(PendingRequest $request): PendingRequest
    {
        return $request->withHeaders([
            'X-App-Token' => $this->appToken,
            'X-App-Secret' => $this->appSecret,
        ])->acceptJson();
    }
}
