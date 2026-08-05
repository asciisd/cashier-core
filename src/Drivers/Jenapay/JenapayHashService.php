<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Jenapay;

class JenapayHashService
{
    public function __construct(
        private readonly string $password,
    ) {}

    /**
     * Checkout session / sale hash:
     * sha1(md5(uppercase(order.number + order.amount + order.currency + order.description + password)))
     */
    public function forSale(string $orderNumber, string $orderAmount, string $orderCurrency, string $orderDescription): string
    {
        return $this->hash($orderNumber.$orderAmount.$orderCurrency.$orderDescription.$this->password);
    }

    /**
     * Capture / refund hash: sha1(md5(uppercase(payment_id + amount + password)))
     */
    public function forPaymentAmountAction(string $paymentId, string $amount): string
    {
        return $this->hash($paymentId.$amount.$this->password);
    }

    /**
     * Void / retry / get-status-by-payment_id hash: sha1(md5(uppercase(payment_id + password)))
     */
    public function forPaymentAction(string $paymentId): string
    {
        return $this->hash($paymentId.$this->password);
    }

    /**
     * Get-status-by-order_id hash: sha1(md5(uppercase(order_id + password)))
     */
    public function forOrderStatus(string $orderId): string
    {
        return $this->hash($orderId.$this->password);
    }

    /**
     * Callback hash: sha1(md5(uppercase(id + order_number + order_amount + order_currency + order_description + password)))
     */
    public function forCallback(string $paymentId, string $orderNumber, string $orderAmount, string $orderCurrency, string $orderDescription): string
    {
        return $this->hash($paymentId.$orderNumber.$orderAmount.$orderCurrency.$orderDescription.$this->password);
    }

    /**
     * Verify the `hash` field of a Jenapay callback payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $received = (string) ($payload['hash'] ?? '');

        if ($received === '') {
            return false;
        }

        // Jenapay names the payment id `id` on callbacks but `payment_id` on
        // the status endpoint, and nests the order fields under `order` there.
        // Falling back rather than defaulting to '' means a payload using the
        // status-endpoint shape hashes its real values instead of hashing
        // empty strings and failing verification unconditionally.
        $expected = $this->forCallback(
            (string) ($payload['id'] ?? $payload['payment_id'] ?? ''),
            (string) ($payload['order_number'] ?? $payload['order']['number'] ?? ''),
            (string) ($payload['order_amount'] ?? $payload['order']['amount'] ?? ''),
            (string) ($payload['order_currency'] ?? $payload['order']['currency'] ?? ''),
            (string) ($payload['order_description'] ?? $payload['order']['description'] ?? ''),
        );

        return hash_equals($expected, $received);
    }

    private function hash(string $concatenated): string
    {
        return sha1(md5(strtoupper($concatenated)));
    }
}
