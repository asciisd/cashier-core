<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Str;

/**
 * Digiblox (Crymbo Gateway v3) — hosted crypto deposits.
 *
 * charge() creates a payment link and hands the customer to the Digiblox
 * widget, which owns currency selection, the deposit address, pricing, the QR
 * code and chain monitoring. There is no server-to-server settlement leg: the
 * webhook is the only authoritative notice that money arrived.
 */
class DigibloxProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    private DigibloxClient $client;

    private DigibloxAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['api_key']) || empty($config['api_secret']) || empty($config['merchant_id'])) {
            throw new PaymentProcessingException('Digiblox provider is not configured.');
        }

        $this->client = DigibloxClient::fromConfig($config);
        $this->adapter = new DigibloxAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        // Plain text and ours — the only unencrypted identifier in the API, and
        // the key that ties the webhook back to the order.
        $externalId = (string) ($data['external_id'] ?? 'DEP-'.Str::ulid());

        $body = $this->adapter->buildLinkPayload(
            array_merge($data, ['external_id' => $externalId, 'amount' => $validated['amount']]),
            $this->config,
        );

        $response = $this->client->createPaymentLink($body);

        return new PaymentResult(
            success: true,
            transactionId: $externalId,
            status: PaymentStatus::Pending,
            amount: (int) round((float) $validated['amount']),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['paymentLink'] ?? null,
                'digiblox_external_id' => $externalId,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
        );
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $rows = $this->client->searchDeposits($transactionId);

        if ($rows === []) {
            // Not an error: no deposit has been recorded for this link yet.
            return null;
        }

        // Rows are sorted newest first. A link may hold several payments; the
        // caller reconciles the set, this reports the latest state.
        $row = $rows[0];

        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: $this->adapter->mapDepositStatus($row['status'] ?? null),
            amount: (int) round((float) ($row['expected_amount'] ?? 0)),
            currency: strtoupper((string) ($row['expected_currency'] ?? 'USD')),
            metadata: array_filter([
                'tx_hash' => $row['tx_hash'] ?? null,
                'gross_received' => $this->adapter->grossReceived($row),
                'deposit_rows' => count($rows),
            ], fn ($value) => $value !== null),
            processorResponse: $row,
        );
    }

    public function getPaymentStatus(string $transactionId): string
    {
        return ($this->retrieve($transactionId)?->status ?? PaymentStatus::Pending)->value;
    }

    public function validatePaymentData(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new PaymentProcessingException('Digiblox requires a positive amount.');
        }

        return ['amount' => $amount];
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    /**
     * Digiblox signs nothing. Authenticity rests entirely on the static header
     * registered with them and replayed on every delivery, which the webhook
     * controller checks. See the spec's open questions.
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        return isset($payload['external_transaction_id'])
            ? (string) $payload['external_transaction_id']
            : null;
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new PaymentProcessingException('Digiblox has no refund endpoint; settle the excess manually.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new PaymentProcessingException('Digiblox does not support void.');
    }

    public function getName(): string
    {
        return 'digiblox';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }
}
