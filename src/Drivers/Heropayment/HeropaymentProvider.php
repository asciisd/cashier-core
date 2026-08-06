<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class HeropaymentProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    private HeropaymentClient $client;

    private HeropaymentAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['api_key']) || empty($config['api_secret'])) {
            throw new PaymentProcessingException('Heropayment provider is not configured.');
        }

        $this->client = HeropaymentClient::fromConfig($config);

        $this->adapter = new HeropaymentAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $externalOrderId = $data['external_id'] ?? 'DEP-'.Str::ulid();
        $currency = strtolower((string) ($data['currency'] ?? config('cashier-core.currency.default', 'USD')));

        $body = array_filter([
            'priceCurrency' => $currency,
            'priceAmount' => (string) $validated['amount'],
            'payCurrency' => $this->resolvePayCurrency($data),
            'customerId' => $this->resolveCustomerId($data),
            'customerEmail' => $data['metadata']['user_email'] ?? null,
            'externalOrderId' => $externalOrderId,
            'successUrl' => $this->config['success_url']
                ?? (Route::has('payment.success') ? route('payment.success') : null),
            'failUrl' => $this->config['fail_url']
                ?? (Route::has('payment.failed') ? route('payment.failed') : null),
            'callbackUrl' => $this->config['webhook_url']
                ?? (Route::has('webhooks.heropayment') ? route('webhooks.heropayment') : null),
        ]);

        try {
            $response = $this->client->createInvoice($body);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('heropayment', $status, $e->getMessage());
            throw new PaymentProcessingException('Heropayment invoice could not be created.');
        }

        $result = $this->adapter->fromProviderResponse($response);

        // Correlate by our externalOrderId: the invoice id from the create
        // response differs from the payment id used in status callbacks, but
        // both carry the external order id.
        return new PaymentResult(
            success: $result->success,
            transactionId: $externalOrderId,
            status: $result->status,
            amount: (int) $validated['amount'],
            currency: strtoupper($currency),
            message: $result->message,
            metadata: array_merge($result->metadata ?? [], [
                'heropayment_invoice_id' => $response['id'] ?? null,
            ]),
            processorResponse: $result->processorResponse,
            paymentMethodSnapshot: $result->paymentMethodSnapshot,
        );
    }

    /**
     * The customer identifier Heropayment shows in its merchant console.
     *
     * This is the MT5 trading account login being funded, not our internal user
     * id: support and reconciliation both work from the login, and a user with
     * several accounts otherwise collapses into one Heropayment customer.
     *
     * Omitted (array_filter drops the null) when no trading account is known.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomerId(array $data): ?string
    {
        $login = $data['metadata']['trading_account_login'] ?? $data['trading_account_login'] ?? null;

        return $login === null || $login === '' ? null : (string) $login;
    }

    /**
     * The crypto the customer pays in, as a Heropayment ticker (`usdttrc20`).
     *
     * The widget takes no currency allow-list — pinning `payCurrency` is the only
     * way to control what the customer sees, and it pins exactly one. So the
     * customer picks from `currencies` on this connection in our own deposit
     * modal, and their choice arrives here; the widget then opens straight on
     * the payment step for that network.
     *
     * Left null, the widget lists every currency Heropayments supports —
     * including ones whose minimum deposit we never quoted.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePayCurrency(array $data): ?string
    {
        $ticker = $data['pay_currency'] ?? null;

        return $ticker === null || $ticker === '' ? null : strtolower((string) $ticker);
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('Heropayment does not support merchant-initiated refunds.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Heropayment does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Heropayment does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Heropayment does not support void.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $payload = $this->client->getPaymentByOrderId($transactionId);

        if ($payload === null) {
            return null;
        }

        return $this->adapter->fromProviderPayload($transactionId, $payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $payload = $this->client->getPaymentByOrderId($transactionId);

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
        return $this->verifyRawSignature($this->client->encodePayload($payload), $signature);
    }

    /**
     * Verify the x-api-sign header against the raw callback body.
     */
    public function verifyRawSignature(string $rawBody, string $signature): bool
    {
        return $this->client->verifySignature($rawBody, $signature);
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        $orderId = $payload['externalOrderId'] ?? $payload['orderID'] ?? $payload['orderId'] ?? null;

        return $orderId !== null ? (string) $orderId : null;
    }

    public function getName(): string
    {
        return 'heropayment';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }
}
