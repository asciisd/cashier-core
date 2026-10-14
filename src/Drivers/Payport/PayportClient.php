<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Payport;

use Asciisd\CashierCore\Support\PspHttp;

/**
 * HTTP client for the Payport API5 invoice endpoints.
 *
 * Authentication is a bearer token — the merchant's API5 key. Note that
 * application errors come back as HTTP 200 with `status: 0` and a `message`,
 * so `->throw()` alone proves nothing; callers must inspect `status`.
 * {@see PayportProvider::assertOk()}
 */
class PayportClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Create a hosted invoice. Returns `url` for the payment page on success.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createInvoice(array $body): array
    {
        return $this->post('/api/v5/invoice/get', $body);
    }

    /**
     * Look an invoice up by the order id we assigned it.
     *
     * @return array<string, mixed>
     */
    public function invoiceStatusByOrderId(string $orderId): array
    {
        return $this->post('/api/v5/invoice/status', ['order_id' => $orderId]);
    }

    /**
     * Cancel an invoice that has not been paid.
     *
     * @return array<string, mixed>
     */
    public function cancelInvoice(int $invoiceId): array
    {
        return $this->post('/api/v5/invoice/cancel', ['invoice_id' => $invoiceId]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = PspHttp::client()->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl.$path, $body)
            ->throw();

        $json = $response->json();

        // A rejected key is answered with an HTML error page, not JSON, and
        // json() returns null for it. `->throw()` catches that case on status,
        // but a 200 carrying HTML would otherwise become a TypeError here.
        return is_array($json) ? $json : [];
    }
}
