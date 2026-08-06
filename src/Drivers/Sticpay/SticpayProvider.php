<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Sticpay;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Sticpay e-wallet deposits over the REST PAY API.
 *
 * A charge opens a payment and redirects the customer to Sticpay's hosted
 * page, where they log into their wallet and confirm the transfer. The outcome
 * arrives asynchronously as a form-encoded callback wrapped in a double
 * JSON-encoded envelope. {@see SticpayCallbackPayload}
 *
 * Two things make this provider unlike the other hosted-redirect ones:
 *
 * 1. **The callback carries no status.** Its code is always `-1`, documented as
 *    having "no specific meaning". Sticpay only fires it for a transfer it has
 *    saved, so a valid signature reads as success — but that makes the
 *    signature the sole authorisation for an MT5 credit, and leaves no way to
 *    see a transfer that was later rejected. `confirm_with_detail_api` (on by
 *    default) resolves the outcome with an outbound authenticated lookup
 *    instead. {@see parseWebhook()}
 * 2. **Sandbox and live share one endpoint**, distinguished only by an
 *    `interface_version` field. The vendor docs are explicit that a sandbox
 *    callback must never credit real funds, so every callback is checked
 *    against the connection's own setting.
 */
class SticpayProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId
{
    public const LIVE = 'live';

    public const SANDBOX = 'sandbox';

    /** How long Sticpay keeps a PAY link alive. */
    private const LINK_TTL_MINUTES = 5;

    /** "Merchant signature is invalid" — §8. */
    private const CODE_INVALID_SIGNATURE = 809;

    private SticpayClient $client;

    private SticpaySignatureService $signatureService;

    private SticpayAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var string[] */
    private array $supportedFeatures = ['charge', 'refund', 'webhook', 'retrieve'];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['merchant_email']) || empty($config['api_key'])) {
            throw new PaymentProcessingException('Sticpay provider is not configured.');
        }

        $this->signatureService = new SticpaySignatureService(
            apiKey: (string) $config['api_key'],
            signType: strtoupper((string) ($config['sign_type'] ?? SticpaySignatureService::MD5)),
        );

        $this->client = new SticpayClient(
            baseUrl: rtrim((string) ($config['base_url'] ?: 'https://api.sticpay.com'), '/'),
        );

        $this->adapter = new SticpayAdapter;
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $orderNo = (string) ($data['order_id'] ?? $data['order_number'] ?? 'DEP-'.Str::ulid());

        /*
         * The connection's currency wins over the one PaymentService sets,
         * which is always the app default. Sticpay accepts a currency the
         * merchant holds no wallet for and then silently settles into the
         * default wallet currency — so an account that is not provisioned for
         * USD needs its currency pinned here.
         */
        $currency = (string) (($this->config['currency'] ?? null)
            ?: ($data['currency'] ?? config('cashier-core.currency.default', 'USD')));

        /*
         * Fixed-2 string, matching the vendor's "125.03". The signature is
         * computed over the literal value, so a float that renders as "125.0"
         * on the wire but "125.03" in the digest fails with error 809.
         */
        $signed = [
            'merchant_email' => (string) $this->config['merchant_email'],
            'order_no' => $orderNo,
            'order_time' => now()->format('Y-m-d H:i:s'),
            'order_amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'order_currency' => $currency,
        ];

        $body = $signed + array_filter([
            'sign' => $this->signatureService->sign($signed, SticpaySignatureService::PAY),
            'sign_type' => $this->signType(),
            'interface_version' => $this->interfaceVersion(),
            'input_charset' => 'UTF-8',
            'callback_url' => $this->url('callback_url', 'webhooks.sticpay'),
            'success_url' => $this->url('success_url', 'payments.sticpay.return.success'),
            'failure_url' => $this->url('failure_url', 'payments.sticpay.return.failure'),
            'referrer_url' => $this->url('referrer_url', 'payments.sticpay.return.cancel'),
            'product_name' => $data['description'] ?? null,
            'full_name' => $data['metadata']['user_name'] ?? null,
            'client_ip' => request()->ip(),
            /*
             * Pins the payment to one Sticpay account. Off by default: a
             * customer's Sticpay wallet email is frequently not the email they
             * registered here, and a mismatch is a hard failure (error 1500)
             * at the payment page rather than a warning.
             */
            'registered_email' => ($this->config['restrict_to_registered_email'] ?? false)
                ? ($data['metadata']['user_email'] ?? null)
                : null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->client->pay($body);
        } catch (HttpClientException $e) {
            // HttpClientException, not RequestException: a connection timeout or
            // DNS failure is a ConnectionException — a sibling, not a subclass —
            // and would otherwise escape as an uncaught 500 with nothing logged.
            $status = $e instanceof RequestException ? $e->response->status() : null;

            PaymentLogger::providerChargeRequestFailed('sticpay', $status, $e->getMessage());
            throw new PaymentProcessingException('Sticpay payment could not be created.');
        }

        $this->assertOk($response, 'payment creation');

        return $this->adapter->fromProviderResponse([
            'link' => $response['link'] ?? null,
            'link_expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES)->toIso8601String(),
            'order_no' => $orderNo,
            'order_amount' => $signed['order_amount'],
            'order_currency' => $currency,
            'response' => $response,
        ]);
    }

    /**
     * Refund a settled deposit in full.
     *
     * Sticpay's refund endpoint takes no amount — it reverses the whole
     * transaction. `ProcessRefund` always passes one (defaulting to the
     * unrefunded remainder), so a partial request has to be refused rather
     * than quietly returning everything.
     */
    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        $detail = $this->detailData($transactionId);

        if ($detail === null) {
            throw new PaymentProcessingException('Sticpay transaction could not be found for refund.');
        }

        $settled = (float) ($detail['from_amount'] ?? 0);

        if ($amount !== null && abs((float) $amount - $settled) >= 1.0) {
            throw new PaymentProcessingException('Sticpay supports full refunds only.');
        }

        $signed = array_filter([
            'merchant' => (string) $this->config['merchant_email'],
            'transaction_code' => $detail['transaction_code'] ?? null,
            'order_id' => isset($detail['transaction_code']) ? null : $transactionId,
            'request_datetime' => now()->format('Y-m-d H:i:s'),
        ], fn ($value) => $value !== null && $value !== '');

        $order = isset($signed['transaction_code'])
            ? SticpaySignatureService::REFUND_BY_TRANSACTION
            : SticpaySignatureService::REFUND_BY_ORDER;

        $response = $this->client->refund($signed + [
            'sign' => $this->signatureService->sign($signed, $order),
            'sign_type' => $this->signType(),
            'interface_version' => $this->interfaceVersion(),
        ]);

        $this->assertOk($response, 'refund');

        return new RefundResult(
            success: true,
            refundId: (string) ($response['transaction_code'] ?? $transactionId),
            originalTransactionId: $transactionId,
            status: RefundStatus::Succeeded,
            amount: (int) round($settled),
            currency: (string) ($detail['from_currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: isset($response['message']) ? (string) $response['message'] : null,
            // RefundResult::$processorResponse is a ?string, so the raw response
            // travels as metadata instead.
            metadata: $response,
        );
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Sticpay capture is not supported in the hosted payment flow.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Sticpay authorize is not supported in the hosted payment flow.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Sticpay exposes no endpoint for cancelling an unpaid payment.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $data = $this->detailData($transactionId);

        return $data === null ? null : $this->adapter->fromProviderPayload($transactionId, $data);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $data = $this->detailData($transactionId);

        return $data === null ? 'unknown' : (string) ($data['status'] ?? 'unknown');
    }

    public function validatePaymentData(array $data): array
    {
        return validator($data, [
            'amount' => 'required|numeric|min:0.01',
        ])->validate();
    }

    /**
     * Turn a flattened Sticpay callback into a transaction update.
     *
     * Both guards live here rather than in the webhook controller: this is the
     * only class on *both* credit paths — the webhook pipeline and
     * `PaymentService::syncTransaction()` — that holds the connection config.
     * A guard in the controller alone would be bypassed by an admin-initiated
     * sync.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        $orderNo = $this->extractWebhookTransactionId($payload);

        /*
         * Not "is this sandbox?" but "does it match this connection?" — a live
         * merchant receiving a sandbox callback and a sandbox merchant
         * receiving a live one are equally wrong, and the vendor docs are
         * explicit that a sandbox callback must not credit real funds.
         *
         * Processing, not Failed: the transaction visibly moves off Pending and
         * trips the requires-attention alarm, without the invoice mail or the
         * MT5 credit (both gated on Succeeded) and without closing the door on
         * a legitimate callback arriving afterwards.
         */
        $received = isset($payload['interface_version']) ? (string) $payload['interface_version'] : null;

        if ($received !== null && $received !== $this->interfaceVersion()) {
            PaymentLogger::providerCallbackEnvironmentMismatch(
                'sticpay',
                $orderNo,
                $this->interfaceVersion(),
                $received,
            );

            return $this->adapter->toUpdate($payload, PaymentStatus::Processing, ['requires_attention' => true]);
        }

        /*
         * Fall back to reading the envelope when confirmation is switched off,
         * or when there is no order number to look the transfer up by — the
         * latter cannot be correlated to a transaction anyway, so the pipeline
         * drops it a step later.
         */
        if ($orderNo === null || ! ($this->config['confirm_with_detail_api'] ?? true)) {
            return $this->adapter->fromWebhook($payload);
        }

        /*
         * The callback says a transfer exists, not that it succeeded. The
         * Transaction Detail API is the only place Sticpay publishes an actual
         * status, so the credit is driven by an outbound authenticated lookup
         * rather than by an inbound POST.
         */
        try {
            $detail = $this->lookupTransaction($orderNo);
        } catch (HttpClientException $e) {
            PaymentLogger::providerCallbackUnconfirmed('sticpay', $orderNo, $e->getMessage());

            // Rethrown so the queued job retries. Falling through to Succeeded
            // would credit MT5 on the strength of a lookup that never happened.
            throw new PaymentProcessingException('Sticpay transaction could not be confirmed.');
        }

        if (($detail['success'] ?? false) !== true) {
            PaymentLogger::providerCallbackUnconfirmed(
                'sticpay',
                $orderNo,
                (string) ($detail['message'] ?? 'lookup rejected'),
            );

            return $this->adapter->toUpdate($payload, PaymentStatus::Processing, ['requires_attention' => true]);
        }

        return $this->adapter->toUpdate(
            $payload + array_filter($detail, 'is_scalar'),
            $this->adapter->mapStatus($detail['status'] ?? null),
        );
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // The signature travels inside the payload, so the argument is ignored.
        return $this->signatureService->verifyCallback($payload);
    }

    public function extractWebhookTransactionId(array $payload): ?string
    {
        $orderNo = $payload['order_no'] ?? $payload['order_id'] ?? null;

        return $orderNo === null || $orderNo === '' ? null : (string) $orderNo;
    }

    public function getName(): string
    {
        return 'sticpay';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    public function interfaceVersion(): string
    {
        return ($this->config['interface_version'] ?? null) === self::SANDBOX ? self::SANDBOX : self::LIVE;
    }

    /**
     * The transaction record behind one of our order numbers, or null when it
     * is unknown or Sticpay is unreachable.
     *
     * @return array<string, mixed>|null
     */
    private function detailData(string $orderNo): ?array
    {
        try {
            $response = $this->lookupTransaction($orderNo);
        } catch (HttpClientException) {
            return null;
        }

        if (($response['success'] ?? false) !== true) {
            // 1400/1401 — Sticpay never saw this order. That is the expected
            // answer for a PAY link that expired before the customer opened it.
            return null;
        }

        return $response;
    }

    /**
     * Look a transaction up, retrying once under the other documented
     * signature form.
     *
     * §5.2 signs the lookup without `interface_version`; §6.2 signs the same
     * lookup with it. Only one of the two is what the merchant account
     * actually accepts, and the wrong one is answered with 809. Rather than
     * guess in config, the §6 form is tried first and the §5 form once after —
     * one retry, never a loop.
     *
     * @return array<string, mixed>
     */
    private function lookupTransaction(string $orderNo): array
    {
        $response = $this->client->transactionDetail($this->detailBody($orderNo));

        if ((int) ($response['code'] ?? 0) !== self::CODE_INVALID_SIGNATURE) {
            return $response;
        }

        PaymentLogger::providerRequestRejected(
            provider: 'sticpay',
            operation: 'transaction lookup',
            message: 'Signature rejected; retrying without interface_version.',
            codes: [(string) self::CODE_INVALID_SIGNATURE],
            errors: [],
        );

        return $this->client->transactionDetail($this->detailBody($orderNo, legacySignature: true));
    }

    /**
     * A signed transaction-detail request.
     *
     * §5.2 documents this signature without `interface_version` while §6.2
     * documents the same lookup with it. The two disagree; the field is sent
     * either way, and only its presence in the digest differs.
     *
     * @return array<string, mixed>
     */
    private function detailBody(string $orderNo, bool $legacySignature = false): array
    {
        $signed = [
            'merchant' => (string) $this->config['merchant_email'],
            'order_id' => $orderNo,
            'request_datetime' => now()->format('Y-m-d H:i:s'),
            'interface_version' => $this->interfaceVersion(),
        ];

        $order = $legacySignature
            ? SticpaySignatureService::DETAIL_BY_ORDER_LEGACY
            : SticpaySignatureService::DETAIL_BY_ORDER;

        return $signed + [
            'sign' => $this->signatureService->sign($signed, $order),
            'sign_type' => $this->signType(),
        ];
    }

    /**
     * A configured callback/redirect URL, falling back to the named route.
     *
     * The package-registered webhook route (`cashier.webhooks.sticpay`) is
     * preferred over the legacy app-side name; when neither route exists the
     * URL is omitted rather than guessed.
     *
     * Trimmed because a leading space in an env value is otherwise an
     * expensive thing to find in a signed request.
     */
    private function url(string $key, string $fallbackRoute): ?string
    {
        $url = trim((string) ($this->config[$key] ?? ''));

        if ($url !== '') {
            return $url;
        }

        if ($fallbackRoute === 'webhooks.sticpay' && Route::has('cashier.webhooks.sticpay')) {
            return route('cashier.webhooks.sticpay');
        }

        return Route::has($fallbackRoute) ? route($fallbackRoute) : null;
    }

    private function signType(): string
    {
        return strtoupper((string) ($this->config['sign_type'] ?? SticpaySignatureService::MD5)) === SticpaySignatureService::SHA256
            ? SticpaySignatureService::SHA256
            : SticpaySignatureService::MD5;
    }

    /**
     * Sticpay answers application errors with HTTP 200 and `success: false`, so
     * a successful transport does not mean a successful operation.
     *
     * @param  array<string, mixed>  $response
     */
    private function assertOk(array $response, string $operation): void
    {
        if (($response['success'] ?? false) === true) {
            return;
        }

        $code = isset($response['code']) && is_numeric($response['code']) ? (int) $response['code'] : null;
        $message = (string) ($response['message'] ?? 'Unknown error');

        PaymentLogger::providerRequestRejected(
            provider: 'sticpay',
            operation: $operation,
            message: $message,
            codes: $code === null ? [] : [(string) $code],
            errors: array_filter(['errors' => $response['errors'] ?? null], fn ($value) => $value !== null),
        );

        throw new PaymentProcessingException($this->errorMessage($code, $operation, $message));
    }

    /**
     * A message the customer or the admin can act on, where Sticpay's own
     * `message` is a bare "Invalid parameter".
     */
    private function errorMessage(?int $code, string $operation, string $message): string
    {
        return match ($code) {
            // Sticpay refuses to reuse an order number. With a ULID this cannot
            // happen by accident, so it means a genuine double-submission.
            1300 => 'A Sticpay deposit with this reference already exists.',
            700 => 'Your Sticpay balance is not enough to complete this deposit.',
            410 => 'This amount is below Sticpay’s minimum.',
            411 => 'This amount is above Sticpay’s maximum.',
            412, 413, 414, 415 => 'This deposit exceeds your Sticpay account limits.',
            1402 => 'This Sticpay transaction is no longer refundable.',
            1500 => 'This deposit must be paid from the Sticpay account registered to you.',
            // Operator-facing: these mean our own configuration is wrong.
            1000 => 'The Sticpay merchant API is disabled.',
            1200 => 'Sticpay rejected the request from this server’s IP address.',
            809 => 'Sticpay rejected our request signature.',
            default => "Sticpay {$operation} was rejected: {$message}",
        };
    }
}
