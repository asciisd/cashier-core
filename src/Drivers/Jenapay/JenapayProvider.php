<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Jenapay;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

class JenapayProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    private JenapayClient $client;

    private JenapayHashService $hashService;

    private JenapayAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'refund', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['merchant_key']) || empty($config['password']) || empty($config['checkout_url'])) {
            throw new PaymentProcessingException('Jenapay provider is not configured.');
        }

        $this->hashService = new JenapayHashService((string) $config['password']);

        $this->client = new JenapayClient(
            checkoutUrl: rtrim((string) $config['checkout_url'], '/'),
            apiUrl: rtrim((string) ($config['api_url'] ?? $config['checkout_url']), '/'),
            merchantKey: (string) $config['merchant_key'],
            hashService: $this->hashService,
        );

        $this->adapter = new JenapayAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $orderNumber = $data['order_number'] ?? 'DEP-'.Str::ulid();
        $amount = number_format((float) $validated['amount'], 2, '.', '');
        $currency = (string) ($data['currency'] ?? config('cashier-core.currency.default', 'USD'));
        $description = (string) ($data['description'] ?? "Deposit {$orderNumber}");

        $body = [
            'merchant_key' => $this->config['merchant_key'],
            'operation' => 'purchase',
            'order' => [
                'number' => $orderNumber,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
            ],
            'customer' => array_filter([
                'name' => $data['metadata']['user_name'] ?? null,
                'email' => $data['metadata']['user_email'] ?? null,
            ]),
            'success_url' => $this->config['success_url'] ?? (\Illuminate\Support\Facades\Route::has('payment.success') ? route('payment.success') : null),
            'cancel_url' => $this->config['cancel_url'] ?? (\Illuminate\Support\Facades\Route::has('payment.failed') ? route('payment.failed') : null),
            'hash' => $this->hashService->forSale($orderNumber, $amount, $currency, $description),
        ];

        try {
            $response = $this->client->createSession($body);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('jenapay', $status, $e->getMessage());
            throw new PaymentProcessingException('Jenapay checkout session could not be created.');
        }

        return $this->adapter->fromProviderResponse([
            'redirect_url' => $response['redirect_url'] ?? null,
            'order_number' => $orderNumber,
            'order_amount' => $amount,
            'order_currency' => $currency,
            'session_response' => $response,
        ]);
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        $status = $this->client->statusByOrderId($transactionId);
        $paymentId = (string) ($status['payment_id'] ?? '');

        if ($paymentId === '') {
            throw new PaymentProcessingException('Jenapay payment id could not be resolved for refund.');
        }

        $refundAmount = number_format(
            $amount !== null ? (float) $amount : (float) ($status['order']['amount'] ?? 0),
            2,
            '.',
            ''
        );

        $response = $this->client->refund($paymentId, $refundAmount);
        $accepted = strtolower((string) ($response['status'] ?? '')) !== 'error';

        // The final refund outcome arrives asynchronously via a `type=refund` callback.
        return new RefundResult(
            success: $accepted,
            refundId: $paymentId,
            originalTransactionId: $transactionId,
            status: $accepted ? RefundStatus::Pending : RefundStatus::Failed,
            amount: $amount ?? (int) round((float) $refundAmount),
            currency: (string) ($status['order']['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $response['reason'] ?? null,
            metadata: $response,
        );
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Jenapay capture is not supported in the hosted checkout flow.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Jenapay authorize is not supported in the hosted checkout flow.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Jenapay void is not supported in the hosted checkout flow.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        try {
            $payload = $this->client->statusByOrderId($transactionId);
        } catch (HttpClientException) {
            return null;
        }

        return $this->adapter->fromProviderPayload($transactionId, $payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        try {
            $payload = $this->client->statusByOrderId($transactionId);
        } catch (HttpClientException) {
            return 'unknown';
        }

        return (string) ($payload['status'] ?? 'unknown');
    }

    public function validatePaymentData(array $data): array
    {
        return validator($data, [
            'amount' => 'required|numeric|min:0.01',
        ])->validate();
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return $this->hashService->verifyCallback($payload);
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        return isset($payload['order_number']) ? (string) $payload['order_number'] : null;
    }

    public function getName(): string
    {
        return 'jenapay';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }
}
