<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Support\PspHttp;

class HeropaymentClient
{
    /**
     * Heropayment re-serializes the parsed request body with `JSON.stringify()`
     * before recomputing the signature, so the signed payload must match Node's
     * output exactly: no escaped forward slashes, no \uXXXX escapes. PHP's
     * default `json_encode()` escapes both and yields a 401 "Invalid signature".
     */
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    /**
     * Build a client from a `cashier-core.connections.heropayment` config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://api.heropayments.io'), '/'),
            apiKey: (string) ($config['api_key'] ?? ''),
            apiSecret: (string) ($config['api_secret'] ?? ''),
        );
    }

    /**
     * Supported currencies. Networks are encoded in the ticker itself
     * (`usdttrc20` vs `usdterc20`), so this doubles as the network list.
     *
     * @return list<array<string, mixed>>
     */
    public function getCurrencies(bool $fiat = false): array
    {
        $payload = $this->getSigned('/v2/currencies'.($fiat ? '?fiat=true' : ''));

        return is_array($payload) ? array_values($payload) : [];
    }

    /**
     * Spot exchange rate. `rate` is the amount of $to per 1 unit of $from.
     * The quote is indicative — there is no rate lock or expiry.
     *
     * @return array<string, mixed>|null
     */
    public function getRate(string $from, string $to, string $transactionType = 'deposit'): ?array
    {
        return $this->getSigned('/v2/rate?'.http_build_query([
            'currencyFrom' => $from,
            'currencyTo' => $to,
            'transactionType' => $transactionType,
        ]));
    }

    /**
     * Minimum deposit/withdrawal amounts. Pass $currency for a single ticker
     * (amount in that ticker), or $baseCurrency for every ticker priced in fiat.
     * With neither, returns the bulk list in native units.
     *
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    public function getMinAmount(?string $currency = null, ?string $baseCurrency = null): ?array
    {
        $query = array_filter([
            'currency' => $currency,
            'baseCurrency' => $baseCurrency,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->getSigned('/v2/min-amount'.($query === [] ? '' : '?'.http_build_query($query)));
    }

    /**
     * Blockchain fees for every ticker, as `{networkfee, ticker, type}` rows
     * where type is `deposit` or `withdrawal`.
     *
     * NOTE: the vendor docs describe `networkfee` as "USDT equivalent", but the
     * live values read as native currency units (btc returns 0.000007, which is
     * ~$0.75 as BTC and meaningless as USDT). {@see HeropaymentQuoteService} treats
     * them as native units. Confirm with Heropayments before showing the converted
     * figure to customers.
     *
     * @return list<array<string, mixed>>
     */
    public function getNetworkFees(): array
    {
        $payload = $this->getSigned('/v2/network-fees');

        return is_array($payload) ? array_values($payload) : [];
    }

    /**
     * Create a hosted invoice (payment widget). Returns `invoiceUrl` for redirect.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createInvoice(array $body): array
    {
        return $this->postSigned('/v2/invoices', $body);
    }

    /**
     * Create an API deposit (returns a deposit address instead of a widget URL).
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createPayment(array $body): array
    {
        return $this->postSigned('/v2/payments', $body);
    }

    /**
     * Payment status by Heropayment payment id.
     *
     * @return array<string, mixed>|null
     */
    public function getPayment(string $paymentId): ?array
    {
        return $this->getSigned("/v2/payments/{$paymentId}");
    }

    /**
     * Payment status by our external order id.
     *
     * @return array<string, mixed>|null
     */
    public function getPaymentByOrderId(string $orderId): ?array
    {
        return $this->getSigned("/v2/payments/order/{$orderId}");
    }

    /**
     * Verify a callback signature (x-api-sign) against the raw request body.
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        return hash_equals($this->sign($rawBody), $signature);
    }

    /**
     * HMAC-SHA512 signature of the given payload string using the API secret.
     */
    public function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->apiSecret);
    }

    /**
     * Encode a payload the way Heropayment expects it to be signed.
     *
     * @param  array<string, mixed>  $body
     */
    public function encodePayload(array $body): string
    {
        return (string) json_encode($body, self::JSON_FLAGS);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postSigned(string $path, array $body): array
    {
        $json = $this->encodePayload($body);

        return PspHttp::client()->withHeaders([
            'x-api-key' => $this->apiKey,
            'x-api-sign' => $this->sign($json),
        ])
            ->withBody($json, 'application/json')
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl.$path)
            ->throw()
            ->json();
    }

    /**
     * GET requests sign the query string (without the leading question mark);
     * for plain paths without a query string the signed payload is empty.
     *
     * @return array<string, mixed>|null
     */
    private function getSigned(string $path): ?array
    {
        $query = parse_url($this->baseUrl.$path, PHP_URL_QUERY) ?? '';

        $response = PspHttp::idempotent()->withHeaders([
            'x-api-key' => $this->apiKey,
            'x-api-sign' => $this->sign((string) $query),
        ])
            ->acceptJson()
            ->timeout(30)
            ->get($this->baseUrl.$path);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }
}
