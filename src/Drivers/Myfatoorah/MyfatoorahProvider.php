<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
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

/**
 * MyFatoorah, on the V3 API.
 *
 * One instance serves one MyFatoorah account, which means one country:
 * MyFatoorah issues a separate API key per country and each key must be sent
 * to that country's host. A merchant trading in two countries is two
 * connections on this driver, exactly as a second APS account is.
 *
 * Deposits, webhooks and sync only. Refunds are deliberately absent rather
 * than forgotten — a MyFatoorah refund is a request a human approves, takes
 * no currency, and has no server-side duplicate guard, so it does not map
 * onto processRefund()'s synchronous contract without a design of its own.
 */
class MyfatoorahProvider implements PaymentProcessorInterface, PreparesChargeData, ProvidesWebhookTransactionId
{
    /**
     * The eight currencies MyFatoorah's Order.Currency accepts. USD is not
     * among them, which is why this driver overrides the currency
     * PaymentService pins.
     */
    private const CURRENCIES = ['SAR', 'BHD', 'AED', 'QAR', 'OMR', 'KWD', 'JOD', 'EGP'];

    private MyfatoorahClient $client;

    private MyfatoorahAdapter $adapter;

    private MyfatoorahSignatureService $signatures;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['base_url']) || empty($config['api_key'])) {
            throw new PaymentProcessingException('MyFatoorah provider is not configured.');
        }

        // Not optional, and not merely "callbacks would be refused": a blank
        // secret is a WORKING HMAC key. The canonical string is built from
        // fields an attacker already holds — the InvoiceId is in their own
        // redirect URL — so hash_hmac(..., '') produces a signature they can
        // compute themselves, verifyWebhookSignature() accepts it, and a
        // forged SUCCESS credits the ledger. Refusing to resolve is the only
        // safe reading of a missing secret.
        // Trimmed, so a whitespace-only value is refused too: ` ` is a
        // non-empty key, but it is a guessable one, and an operator who set it
        // meant "unset" every time.
        if (trim((string) ($config['webhook_secret'] ?? '')) === '') {
            throw new PaymentProcessingException('MyFatoorah connection has no webhook secret configured.');
        }

        $currency = strtoupper((string) ($config['currency'] ?? ''));

        if ($currency === '') {
            throw new PaymentProcessingException('MyFatoorah connection has no currency configured.');
        }

        // Fail at resolve time rather than as an opaque ValidationError on the
        // first live charge.
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new PaymentProcessingException(
                "MyFatoorah cannot charge in {$currency}; it accepts ".implode(', ', self::CURRENCIES).'.'
            );
        }

        $this->client = new MyfatoorahClient(
            baseUrl: rtrim((string) $config['base_url'], '/'),
            apiKey: (string) $config['api_key'],
        );

        $this->adapter = new MyfatoorahAdapter;
        $this->signatures = new MyfatoorahSignatureService((string) ($config['webhook_secret'] ?? ''));
    }

    /**
     * PaymentService pins every charge to `cashier-core.currency.default` and
     * forbids callers choosing one; this hook is the documented escape for a
     * driver that charges in a fixed currency. MyFatoorah cannot charge USD,
     * so without this every charge is rejected.
     *
     * The customer name rides along too: PaymentService merges `user_id` and
     * `user_email` into metadata but not the name, and MyFatoorah shows
     * `Customer.Name` on the hosted page.
     *
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        $paymentData['currency'] = $this->currency();

        $paymentData['metadata'] = array_merge($paymentData['metadata'] ?? [], [
            'user_name' => $customer->cashierName(),
            'user_locale' => $customer->cashierLocale(),
        ]);

        return $paymentData;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $externalId = $data['external_id'] ?? 'DEP-'.Str::ulid();

        $payload = array_filter([
            // Omitted, not nulled: leaving the key out is what lands the
            // customer on MyFatoorah's own picker showing every method
            // enabled on the account.
            'PaymentMethod' => $this->setting('payment_method'),
            'Order' => [
                // The exact figure we were handed. transactions.amount is
                // decimal(16,2) so this is already two-decimal; no scaling,
                // no minor units.
                'Amount' => (float) $validated['amount'],
                'Currency' => $this->currency(),
                'ExternalIdentifier' => $externalId,
            ],
            'Customer' => array_filter([
                'Name' => $data['metadata']['user_name'] ?? null,
                'Email' => $data['metadata']['user_email'] ?? null,
                'Reference' => isset($data['metadata']['user_id'])
                    ? (string) $data['metadata']['user_id']
                    : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'IntegrationUrls' => array_filter([
                'Redirection' => $this->setting('redirect_url')
                    ?? (Route::has('payment.success') ? route('payment.success') : null),
                'Webhook' => $this->setting('webhook_url') ?? $this->webhookRoute(),
            ], fn ($value) => $value !== null),
            'Language' => $this->language($data),
            'IpAddress' => $data['metadata']['ip_address'] ?? request()->ip(),
            'MetaData' => ['UDF1' => $externalId],
        ], fn ($value) => $value !== null && $value !== []);

        try {
            // The idempotency key carries the same ULID as
            // Order.ExternalIdentifier, so a retry is recognisable from either
            // end. It is the only protection against a double charge.
            $response = $this->client->createPayment($payload, $externalId);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout
            // or DNS failure is a ConnectionException — a sibling, not a
            // subclass — and would otherwise escape as an uncaught 500 with
            // nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('myfatoorah', $status, $e->getMessage());

            throw new PaymentProcessingException('MyFatoorah payment could not be created.');
        }

        $result = $this->adapter->fromProviderResponse($response + [
            'amount' => (float) $validated['amount'],
            'currency' => $this->currency(),
        ]);

        return new PaymentResult(
            success: $result->success,
            transactionId: $result->transactionId,
            status: $result->status,
            amount: (int) $validated['amount'],
            currency: $this->currency(),
            message: $result->message,
            metadata: array_merge($result->metadata ?? [], ['myfatoorah_external_id' => $externalId]),
            processorResponse: $result->processorResponse,
            paymentMethodSnapshot: $result->paymentMethodSnapshot,
        );
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support refunds.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('The MyFatoorah driver does not support void.');
    }

    /**
     * The recovery path, and it matters more here than for most drivers:
     * MyFatoorah's V1 webhooks retry four times and then give up permanently,
     * and V2's are capped at five, so a delivery can be lost for good.
     */
    public function retrieve(string $transactionId): ?PaymentResult
    {
        $payload = $this->client->getInvoice($transactionId);

        if ($payload === null) {
            return null;
        }

        return $this->adapter->fromProviderPayload($transactionId, $payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $payload = $this->client->getInvoice($transactionId);

        if ($payload === null) {
            return 'unknown';
        }

        $transactions = (array) ($payload['Transactions'] ?? []);

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && ($transaction['Status'] ?? null) === 'SUCCESS') {
                return 'SUCCESS';
            }
        }

        return (string) (data_get($payload, 'Invoice.Status') ?? 'unknown');
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

    /**
     * Verify a webhook body against this account's secret.
     *
     * Takes the WHOLE body: the event code selects the field list, and the
     * signature is over a canonical string built from `Data`, never over the
     * raw body. Returns false for an event this driver does not implement,
     * so an unverifiable event can never be mistaken for a verified one.
     *
     * Every MyFatoorah account posts to the same callback URL, so identifying
     * the sender means trying each account's secret in turn — a match is
     * itself the proof of which account sent it. That loop lives in
     * MyfatoorahWebhookController.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $eventCode = (int) data_get($payload, 'Event.Code', 0);

        if (! $this->signatures->supports($eventCode)) {
            return false;
        }

        return $this->signatures->verify($eventCode, (array) ($payload['Data'] ?? []), $signature);
    }

    /**
     * The correlation key is the invoice id. See MyfatoorahAdapter for why it
     * is not the payment id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookTransactionId(array $payload): ?string
    {
        $invoiceId = data_get($payload, 'Data.Invoice.Id');

        return $invoiceId !== null ? (string) $invoiceId : null;
    }

    public function getName(): string
    {
        return 'myfatoorah';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    private function currency(): string
    {
        return strtoupper((string) $this->config['currency']);
    }

    /**
     * An optional connection setting, with a set-but-EMPTY value read as
     * unset.
     *
     * `MYFATOORAH_PAYMENT_METHOD=` in a .env leaves the key present holding
     * an empty string, so `??` never fires; the final array_filter drops null
     * and [] but not ''. `PaymentMethod: ''` is not the method picker — that
     * needs the key absent — and is a validation error on every charge.
     *
     * It is worse on the two IntegrationUrls: an empty `webhook_url` would
     * skip the route fallback entirely and send `Webhook: ''`. Omitting the
     * parameter routes events to the URL configured in the dashboard;
     * sending an empty one is undefined and could lose every webhook for
     * that invoice.
     */
    private function setting(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * This package's own webhook endpoint, or null when the host has not
     * registered it.
     *
     * The route's NAME is host-configurable: `routes.name_prefix` defaults to
     * `cashier.webhooks.`, but an application that took over a set of callback
     * URLs already registered with its PSPs commonly sets it to `webhooks.`.
     * Hardcoding either prefix means the fallback silently never fires in half
     * of all installs — and a missing `IntegrationUrls.Webhook` is invisible,
     * because MyFatoorah quietly falls back to whatever the portal has, which
     * may be nothing at all.
     *
     * So the configured prefix is asked first, then both known conventions.
     * A host that disabled the package routes and registered its own gets null
     * and should set `webhook_url` on the connection explicitly.
     */
    private function webhookRoute(): ?string
    {
        $configured = (string) config('cashier-core.routes.name_prefix', 'cashier.webhooks.');

        $candidates = array_unique([
            $configured.'myfatoorah',
            'cashier.webhooks.myfatoorah',
            'webhooks.myfatoorah',
        ]);

        foreach ($candidates as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }

    /**
     * MyFatoorah accepts EN or AR. An explicit connection setting wins;
     * otherwise the customer's locale decides, which prepareChargeData()
     * put in metadata.
     *
     * @param  array<string, mixed>  $data
     */
    private function language(array $data): string
    {
        $configured = $this->config['language'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return strtoupper($configured);
        }

        $locale = strtolower((string) ($data['metadata']['user_locale'] ?? ''));

        return str_starts_with($locale, 'ar') ? 'AR' : 'EN';
    }
}
