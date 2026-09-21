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
use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
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

        try {
            $response = $this->client->createPaymentLink($body);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('digiblox', $status, $e->getMessage());
            throw new PaymentProcessingException('Digiblox payment link could not be created.');
        }

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

        // Never trust rows[0]: searchDeposits sorts newest-created first, not
        // newest-settled, and one link can legitimately hold several rows — a
        // second unrelated payment, or Digiblox's own internal settlement row
        // (spec: "Always iterate result — never assume result[0]"). So a
        // CONFIRMED row can sit behind a later SENT_TOKEN row, and reporting
        // rows[0] would call an already-settled order Pending.
        //
        // Aggregate instead: a CONFIRMED deposit never reverts once it lands,
        // so any row that mapped to Succeeded outranks every other row
        // regardless of position. Only when every row has failed do we report
        // Failed; anything short of that (still awaiting confirmation) is
        // Pending.
        $confirmedRow = null;
        $statuses = [];

        foreach ($rows as $row) {
            $status = $this->adapter->mapDepositStatus($row['status'] ?? null);
            $statuses[] = $status;

            if ($confirmedRow === null && $status === PaymentStatus::Succeeded) {
                $confirmedRow = $row;
            }
        }

        $overallStatus = match (true) {
            $confirmedRow !== null => PaymentStatus::Succeeded,
            array_filter($statuses, fn (PaymentStatus $status) => $status !== PaymentStatus::Failed) === [] => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        // expected_amount / expected_currency come from the payment link
        // itself, so they are identical on every row for this external_id —
        // any row will do; the newest is as good as any other.
        $newestRow = $rows[0];

        // Prefer the tx_hash (and the gross figure) of the row that actually
        // confirmed; fall back to the newest row's when nothing has
        // confirmed yet.
        $settledRow = $confirmedRow ?? $newestRow;

        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: $overallStatus,
            amount: (int) round((float) ($newestRow['expected_amount'] ?? 0)),
            currency: strtoupper((string) ($newestRow['expected_currency'] ?? 'USD')),
            metadata: array_filter([
                'tx_hash' => $settledRow['tx_hash'] ?? null,
                'gross_received' => $this->adapter->grossReceived($settledRow),
                'deposit_rows' => count($rows),
            ], fn ($value) => $value !== null),
            // The full set, not one row — a caller can iterate and dedupe on
            // tx_hash exactly as the spec instructs.
            processorResponse: $rows,
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
        throw new \BadMethodCallException('Digiblox has no refund endpoint; settle the excess manually.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Digiblox does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Digiblox does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Digiblox does not support void.');
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
