<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Aps;

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

class ApsProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    private ApsClient $client;

    private ApsAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'refund', 'webhook'];

    /**
     * One instance serves one APS merchant account.
     *
     * APS issues a separate account — own GUID, app key and callback secret —
     * per product, and Binance Pay is not reachable with the card account's
     * credentials. Each account is its own connection in
     * `config('cashier-core.connections')`, so the account is chosen by which
     * connection a payment method names rather than by anything in here.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['merchant_guid']) || empty($config['app_token']) || empty($config['app_secret'])) {
            throw new PaymentProcessingException('APS provider is not configured.');
        }

        $this->client = new ApsClient(
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://fpf-api.proc-gw.com'), '/'),
            merchantGuid: (string) $config['merchant_guid'],
            appToken: (string) $config['app_token'],
            appSecret: (string) $config['app_secret'],
            callbackSecret: isset($config['callback_secret']) ? (string) $config['callback_secret'] : null,
        );

        $this->adapter = new ApsAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $methodGuid = $this->config['deposit_method'] ?? null;

        if (! $methodGuid) {
            throw new PaymentProcessingException('APS connection has no deposit_method configured.');
        }

        $externalId = $data['external_id'] ?? 'DEP-'.Str::ulid();

        $payload = [
            'amount' => (float) $validated['amount'],
            'fields' => [
                'transaction' => [
                    'deposit_method' => $methodGuid,
                    'deposit' => array_filter([
                        'redirect_url' => $this->config['redirect_url'] ?? (\Illuminate\Support\Facades\Route::has('payment.success') ? route('payment.success') : null),
                        'status_callback_url' => $this->config['webhook_url'] ?? (\Illuminate\Support\Facades\Route::has('webhooks.aps') ? route('webhooks.aps') : null),
                        'external_id' => $externalId,
                        'customer_ip_address' => $data['metadata']['ip_address'] ?? request()->ip(),
                    ]),
                ],
            ],
        ];

        try {
            $response = $this->client->createDeposit($payload);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('aps', $status, $e->getMessage());
            throw new PaymentProcessingException('APS deposit could not be created.');
        }

        $result = $this->adapter->fromProviderResponse($response + ['currency' => $data['currency'] ?? null]);

        $metadata = array_merge($result->metadata ?? [], ['aps_external_id' => $externalId]);

        if (isset($metadata['redirect_url'])) {
            $metadata['redirect_url'] = $this->customerFacingUrl((string) $metadata['redirect_url']);
        }

        return new PaymentResult(
            success: $result->success,
            transactionId: $result->transactionId,
            status: $result->status,
            amount: (int) $validated['amount'],
            currency: (string) ($data['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $result->message,
            metadata: $metadata,
            processorResponse: $result->processorResponse,
        );
    }

    /**
     * Swap the host of an APS checkout URL for the customer-facing one.
     *
     * The BANKCARD `how` URL points at the PCI gateway's JSON API host, which
     * shows the customer a raw JSON payment session instead of the card form.
     * The form SPA serves the identical path from a sibling host, so only the
     * host is replaced — the path and query are APS's and stay untouched.
     *
     * Hosts absent from `checkout_host_map` pass through unchanged, so this is
     * inert for every other APS method and for the day APS returns the form URL
     * directly.
     */
    private function customerFacingUrl(string $url): string
    {
        /** @var array<string, string> $map */
        $map = $this->config['checkout_host_map'] ?? [];

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! isset($map[$host]) || $map[$host] === '') {
            return $url;
        }

        return substr_replace($url, $map[$host], (int) strpos($url, $host), strlen($host));
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        $payload = array_filter([
            'reason' => 'Merchant refund',
            'external_id' => 'REF-'.Str::ulid(),
            'amount' => $amount !== null ? (float) $amount : null,
        ], fn ($value) => $value !== null);

        $response = $this->client->refund($transactionId, $payload);
        $body = $response->json() ?? [];
        $success = $response->successful();

        return new RefundResult(
            success: $success,
            refundId: (string) ($body['id'] ?? $payload['external_id']),
            originalTransactionId: $transactionId,
            status: $success ? RefundStatus::Succeeded : RefundStatus::Failed,
            amount: $amount ?? 0,
            currency: config('cashier-core.currency.default', 'USD'),
            message: $body['external_message'] ?? null,
            metadata: $body,
        );
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('APS does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('APS does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('APS does not support void.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $payload = $this->client->getTransaction($transactionId);

        if ($payload === null) {
            return null;
        }

        return $this->adapter->fromProviderPayload($transactionId, $payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $payload = $this->client->getTransaction($transactionId);

        return (string) ($payload['status'] ?? $payload['sep31_status'] ?? 'unknown');
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
        return $this->verifyRawSignature((string) json_encode($payload), $signature);
    }

    /**
     * Verify the X-Signature header against the raw callback body, for this
     * account only.
     *
     * Every APS account posts to the same callback URL and the payload carries
     * no merchant identifier, so identifying the sender means trying each
     * account's callback secret in turn — a match is itself the proof of which
     * account sent it. That loop lives in ApsWebhookController, which iterates
     * the connections sharing this driver.
     */
    public function verifyRawSignature(string $rawBody, string $signature): bool
    {
        return $this->client->verifySignature($rawBody, $signature);
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        $inner = $payload['payload'] ?? $payload;

        return isset($inner['transaction_id']) ? (string) $inner['transaction_id'] : null;
    }

    public function getName(): string
    {
        return 'aps';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }
}
