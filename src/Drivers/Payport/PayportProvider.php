<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Payport;

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

/**
 * Payport (payme.center) hosted-invoice deposits over API5.
 *
 * A charge creates an invoice and redirects the customer to Payport's payment
 * page, where they pick a local rail — SAR (IBAN, SWIFT, Barq/Emirates NBD
 * wallets) or EGP (Vodafone, InstaPay, Telda, Orange and other mobile wallets)
 * — and pay. The outcome arrives asynchronously as a form-encoded callback.
 *
 * API3 is a different, trader-mediated flow: it returns a list of counterparty
 * ads and requires us to render bank details, collect a receipt and confirm the
 * payment ourselves. It does not fit the hosted-redirect model the rest of this
 * app is built on, so only the API5 key is used.
 */
class PayportProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    /**
     * Payment-form localisations Payport offers. Anything else falls back to
     * English rather than being rejected at invoice creation.
     *
     * @var list<string>
     */
    private const SUPPORTED_LOCALES = ['en', 'ru', 'tr', 'hi', 'es', 'ua', 'vn', 'ar'];

    private PayportClient $client;

    private PayportSignatureService $signatureService;

    private PayportAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'webhook', 'void'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['base_url']) || empty($config['api_key'])) {
            throw new PaymentProcessingException('Payport provider is not configured.');
        }

        $this->signatureService = new PayportSignatureService(
            apiKey: (string) $config['api_key'],
            alternateKeys: array_values(array_filter([(string) ($config['api3_key'] ?? '')])),
        );

        $this->client = new PayportClient(
            baseUrl: rtrim((string) $config['base_url'], '/'),
            apiKey: (string) $config['api_key'],
        );

        $this->adapter = new PayportAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $orderId = (string) ($data['order_id'] ?? $data['order_number'] ?? 'DEP-'.Str::ulid());
        $description = (string) ($data['description'] ?? "Deposit {$orderId}");

        /*
         * The connection's currency wins over the one PaymentService sets, which
         * is always the app default. A Payport merchant account is provisioned
         * for either fiat or non-fiat invoices: sending a currency the account
         * has no offers for still creates the invoice, and the customer then
         * lands on a payment page with nothing to pick.
         * {@see currency2currency below}
         */
        $currency = (string) (($this->config['currency'] ?? null)
            ?: ($data['currency'] ?? config('cashier-core.currency.default', 'USD')));

        $body = array_filter([
            'order_id' => $orderId,
            'amount' => (float) $validated['amount'],
            'currency' => $currency,
            'customer_id' => $this->customerId($data),
            'order_desc' => $description,
            'server_url' => $this->url('webhook_url', 'webhooks.payport'),
            'return_url' => $this->url('success_url', 'payment.success'),
            'response_url' => $this->url('success_url', 'payment.success'),
            'cancel_url' => $this->url('cancel_url', 'payment.failed'),
            'locale' => $this->locale(),
            /*
             * Makes the invoice a *fiat* invoice: the currency and amount are
             * taken exactly as sent. Left off, Payport opens a non-fiat (USDT)
             * invoice and the customer chooses a fiat to settle in — which only
             * works on an account that has non-fiat offers. Whether an account
             * has them is a Payport-side setting, so this is per connection.
             */
            'currency2currency' => isset($this->config['currency2currency'])
                ? (int) (bool) $this->config['currency2currency']
                : null,
            // Restricts the payment page to one country's rails, which is how a
            // single Payport merchant account backs both the SAR and the EGP
            // processor. Unset offers every rail the account has.
            'filter_fiats' => $this->listConfig('filter_fiats'),
            'filter_payment_system_types' => $this->listConfig('filter_payment_system_types'),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            $response = $this->client->createInvoice($body);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerChargeRequestFailed('payport', $status, $e->getMessage());
            throw new PaymentProcessingException('Payport invoice could not be created.');
        }

        $this->assertOk($response, 'invoice creation');

        return $this->adapter->fromProviderResponse([
            'url' => $response['url'] ?? null,
            'order_id' => $orderId,
            'invoice_id' => $response['invoice_id'] ?? null,
            'merchant_id' => $response['merchant_id'] ?? null,
            // The requested amount lands in `amount_currency`; `amount` is the
            // USDT leg and is 0 until Payport fixes a rate, so it must not be
            // read as the charged figure.
            'amount_currency' => $response['amount_currency'] ?? $validated['amount'],
            'currency' => $response['currency'] ?? $currency,
            'response' => $response,
        ]);
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        throw new \BadMethodCallException('Payport refunds are handled by the provider’s support team.');
    }

    public function capture(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Payport capture is not supported in the hosted invoice flow.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Payport authorize is not supported in the hosted invoice flow.');
    }

    /**
     * Cancel an invoice that has not been paid yet.
     *
     * Cancellation is keyed on Payport's own invoice id, which the callback and
     * status responses carry but our transaction id (the order id) does not, so
     * it is resolved with a status lookup first.
     */
    public function void(string $transactionId): PaymentResult
    {
        $data = $this->statusData($transactionId);

        if ($data === null) {
            throw new PaymentProcessingException('Payport invoice could not be found for cancellation.');
        }

        $invoiceId = $data['invoice']['invoice_id'] ?? null;

        if ($invoiceId === null) {
            throw new PaymentProcessingException('Payport invoice id could not be resolved for cancellation.');
        }

        $response = $this->client->cancelInvoice((int) $invoiceId);

        $this->assertOk($response, 'invoice cancellation');

        return $this->adapter->fromProviderPayload(
            $transactionId,
            ['status' => -1, 'message' => 'Invoice cancelled'] + $data,
        );
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $data = $this->statusData($transactionId);

        return $data === null ? null : $this->adapter->fromProviderPayload($transactionId, $data);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $data = $this->statusData($transactionId);

        return $data === null ? 'unknown' : (string) ($data['status'] ?? 'unknown');
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
        return $this->signatureService->verify($payload);
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        return isset($payload['order_id']) && $payload['order_id'] !== ''
            ? (string) $payload['order_id']
            : null;
    }

    public function getName(): string
    {
        return 'payport';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    /**
     * The `data` node of a status lookup, or null when the invoice is unknown
     * or Payport is unreachable.
     *
     * @return array<string, mixed>|null
     */
    private function statusData(string $orderId): ?array
    {
        try {
            $response = $this->client->invoiceStatusByOrderId($orderId);
        } catch (HttpClientException) {
            return null;
        }

        if ((int) ($response['status'] ?? 0) !== 1) {
            return null;
        }

        return is_array($response['data'] ?? null) ? $response['data'] : null;
    }

    /**
     * Payport identifies the paying customer and refuses to open a second
     * invoice while one is still outstanding for them (P10008), which is what
     * keeps a customer from starting several deposits at once.
     *
     * @param  array<string, mixed>  $data
     */
    private function customerId(array $data): string
    {
        $customerId = $data['metadata']['user_id'] ?? null;

        if ($customerId === null || $customerId === '') {
            throw new PaymentProcessingException('Payport requires a customer id to create an invoice.');
        }

        return (string) $customerId;
    }

    /**
     * A configured callback/redirect URL, falling back to the named route.
     *
     * Whitespace is stripped because Payport validates these as hostnames and
     * rejects the whole request with a generic "Invalid parameters" — a leading
     * space in an env value is otherwise an expensive thing to find.
     */
    private function url(string $key, string $fallbackRoute): ?string
    {
        $url = trim((string) ($this->config[$key] ?? ''));

        if ($url !== '') {
            return $url;
        }

        return Route::has($fallbackRoute) ? route($fallbackRoute) : null;
    }

    private function locale(): string
    {
        $locale = strtolower(substr(app()->getLocale(), 0, 2));

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en';
    }

    /**
     * A config value that Payport expects as a list, normalised and dropped
     * when empty.
     *
     * @return list<string>|null
     */
    private function listConfig(string $key): ?array
    {
        $value = $this->config[$key] ?? null;

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return null;
        }

        $values = array_values(array_filter(array_map(
            fn ($entry): string => trim((string) $entry),
            $value,
        ), fn (string $entry): bool => $entry !== ''));

        return $values === [] ? null : $values;
    }

    /**
     * Payport answers application errors with HTTP 200 and `status: 0`, so a
     * successful transport does not mean a successful operation.
     *
     * @param  array<string, mixed>  $response
     */
    private function assertOk(array $response, string $operation): void
    {
        if ((int) ($response['status'] ?? 0) === 1) {
            return;
        }

        $message = (string) ($response['message'] ?? 'Unknown error');
        $codes = $this->errorCodes($response);
        $errors = array_filter([
            'errors' => $response['errors'] ?? null,
            'error' => $response['error'] ?? null,
        ], fn ($value) => $value !== null);

        // `message` is generic — "Invalid parameters" covers everything from a
        // malformed URL to an unsupported currency. The field-level node is what
        // names the offending field, so it is logged rather than summarised away.
        PaymentLogger::providerRequestRejected(
            provider: 'payport',
            operation: $operation,
            message: $message,
            codes: $codes,
            errors: $errors,
        );

        // P10008 — the customer still has an unpaid invoice. Payport returns its
        // URL, but sending the customer there would leave the callback pointing
        // at the earlier order id, so the earlier deposit is the one to finish.
        if (in_array('P10008', $codes, true)) {
            throw new PaymentProcessingException(
                'You already have a Payport deposit awaiting payment. Complete or cancel it before starting another.'
            );
        }

        throw new PaymentProcessingException("Payport {$operation} was rejected: {$message}");
    }

    /**
     * The `Pxxxxx`/`Ixxxxx` codes buried in a Payport error response, which
     * nests them under either `errors` or `error` depending on the failure.
     *
     * @param  array<string, mixed>  $response
     * @return list<string>
     */
    private function errorCodes(array $response): array
    {
        $codes = [];

        // Assigned first: array_walk_recursive takes its subject by reference
        // and rejects a literal.
        $nodes = [
            'errors' => $response['errors'] ?? [],
            'error' => $response['error'] ?? [],
        ];

        array_walk_recursive($nodes, function ($value) use (&$codes): void {
            if (is_string($value) && preg_match('/\b([PI]\d{5})\b/', $value, $matches) === 1) {
                $codes[] = $matches[1];
            }
        });

        return array_values(array_unique($codes));
    }
}
