<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Jenapay;

use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\PendingRequest;

class JenapayClient
{
    public function __construct(
        private readonly string $checkoutUrl,
        private readonly string $apiUrl,
        private readonly string $merchantKey,
        private readonly JenapayHashService $hashService,
    ) {}

    /**
     * Create a hosted Checkout session. Returns `redirect_url` on success.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createSession(array $body): array
    {
        return PspHttp::client()
            ->acceptJson()
            ->post("{$this->checkoutUrl}/api/v1/session", $body)
            ->throw()
            ->json();
    }

    /**
     * Get transaction status by payment id.
     *
     * @return array<string, mixed>
     */
    public function statusByPaymentId(string $paymentId): array
    {
        return $this->postIdempotent('/api/v1/payment/status', [
            'merchant_key' => $this->merchantKey,
            'payment_id' => $paymentId,
            'hash' => $this->hashService->forPaymentAction($paymentId),
        ]);
    }

    /**
     * Get transaction status by merchant order id.
     *
     * @return array<string, mixed>
     */
    public function statusByOrderId(string $orderId): array
    {
        return $this->postIdempotent('/api/v1/payment/status', [
            'merchant_key' => $this->merchantKey,
            'order_id' => $orderId,
            'hash' => $this->hashService->forOrderStatus($orderId),
        ]);
    }

    /**
     * Refund a settled payment. Final outcome arrives via a `type=refund` callback.
     *
     * @return array<string, mixed>
     */
    public function refund(string $paymentId, string $amount): array
    {
        return $this->post('/api/v1/payment/refund', [
            'merchant_key' => $this->merchantKey,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'hash' => $this->hashService->forPaymentAmountAction($paymentId, $amount),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->send(PspHttp::client(), $path, $body);
    }

    /**
     * For status lookups only — safely repeatable, so retries are allowed.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postIdempotent(string $path, array $body): array
    {
        return $this->send(PspHttp::idempotent(), $path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(PendingRequest $request, string $path, array $body): array
    {
        return $request->acceptJson()
            ->post($this->apiUrl.$path, $body)
            ->throw()
            ->json();
    }
}
