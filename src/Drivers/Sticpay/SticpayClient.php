<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Sticpay;

use Asciisd\CashierCore\Support\PspHttp;

/**
 * HTTP client for Sticpay's REST endpoints.
 *
 * Requests are form-encoded, not JSON, and carry no auth header — the `sign`
 * parameter is the authentication. Note that application errors come back as
 * HTTP 200 with `success: false` and a numeric `code`, so `->throw()` alone
 * proves nothing; callers must inspect `success`.
 * {@see SticpayProvider::assertOk()}
 */
class SticpayClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Create a hosted payment. Returns `link` for the payment page on success.
     *
     * The link is valid for five minutes.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function pay(array $body): array
    {
        return $this->post('/rest_pay/pay', $body);
    }

    /**
     * Full refund of a settled transaction. Sticpay takes no amount here.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function refund(array $body): array
    {
        return $this->post('/rest_withdraw/refund', $body);
    }

    /**
     * Look a transaction up by our order id or Sticpay's transaction code.
     *
     * The only endpoint that reports a real status (`approved` / `rejected` /
     * `pending`) — the transaction callback carries none.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transactionDetail(array $body): array
    {
        return $this->post('/rest_transaction/detail', $body);
    }

    /**
     * Ask Sticpay to redeliver a transaction callback.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function resendCallback(array $body): array
    {
        return $this->post('/rest_transaction/resend_callback', $body);
    }

    /**
     * PspHttp::client() deliberately for every endpoint — including the
     * transaction-detail lookup, whose throw-on-transport-failure semantics
     * the confirmation path depends on to retry the queued webhook job. A
     * swallow-retry base (PspHttp::idempotent()) would turn that throw into a
     * silent empty response and credit nothing.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = PspHttp::client()
            ->asForm()
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl.$path, $body)
            ->throw();

        $json = $response->json();

        // A non-whitelisted IP is answered with an HTML error page rather than
        // JSON, and json() returns null for it. `->throw()` catches that on
        // status, but a 200 carrying HTML would otherwise become a TypeError.
        return is_array($json) ? $json : [];
    }
}
