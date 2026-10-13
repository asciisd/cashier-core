<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * PayCrossBorder (docs.paycrossborder.com) — payouts only.
 *
 * PCB's API is for Financial Institution customers: it sends money out on
 * crypto, bank and mobile-wallet rails and offers no collection, refund or
 * callback endpoints. Everything on the deposit side is unsupported; payouts
 * go through PayCrossBorderPayoutService and are polled with lookup().
 */
class PayCrossBorderProvider implements PaymentProcessorInterface, SendsPayouts
{
    private const REQUIRED = ['base_url', 'username', 'password', 'sender_code', 'customer_code'];

    /** @var string[] */
    private array $supportedFeatures = ['payout'];

    private ?PayCrossBorderPayoutService $payouts = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        foreach (self::REQUIRED as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new PaymentProcessingException("PayCrossBorder provider is not configured: {$key} is missing.");
            }
        }
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        return $this->payouts()->preflight($request);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        return $this->payouts()->send($request);
    }

    public function lookup(string $externalOrderId, ?string $reference = null): ?PayoutReceipt
    {
        return $this->payouts()->lookup($externalOrderId, $reference);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        return $this->payouts()->parsePayoutWebhook($payload);
    }

    public function charge(array $data): PaymentResult
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it cannot take deposits.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        return null;
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it has no payment status.');
    }

    public function validatePaymentData(array $data): array
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it cannot take deposits.');
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new PaymentProcessingException('PayCrossBorder sends no callbacks.');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support refunds.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support void.');
    }

    public function getName(): string
    {
        return 'paycrossborder';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }

    private function payouts(): PayCrossBorderPayoutService
    {
        return $this->payouts ??= new PayCrossBorderPayoutService($this->config);
    }
}
