<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Xoala (checkout-docs.xoala.com) hosted Standard Checkout deposits.
 *
 * Xoala is a white-label of the Paymentz platform. Standard Checkout has NO
 * server-to-server leg: the customer's browser POSTs a signed field set to
 * {host}/transaction/Checkout and Xoala hosts the payment page from there.
 * `charge()` therefore performs no HTTP at all — it mints an id and hands the
 * engine a signed package route, and {@see XoalaCheckoutController} turns that
 * route into the form POST Xoala expects.
 *
 * The alternatives were weighed and rejected in the design: the REST
 * asynchronous flow returns a redirect URL directly but only avoids PCI scope
 * for non-card methods, and the Invoice API is built around emailing the
 * customer a link that expires.
 */
class XoalaProvider implements PaymentProcessorInterface, PreparesChargeData, ProvidesWebhookTransactionId
{
    /**
     * The route the bridge is registered under, assembled from the checkout
     * group's configured name prefix.
     */
    private const ROUTE = 'xoala';

    /**
     * How long a customer has to arrive at the hosted page. Long enough for a
     * slow redirect chain, short enough that a leaked URL is not a standing
     * invitation to re-open somebody's payment form.
     */
    private const LINK_TTL_MINUTES = 30;

    private XoalaSignatureService $signatureService;

    private XoalaClient $client;

    private XoalaAdapter $adapter;

    /** @var array<string, mixed> */
    private array $config;

    /** @var list<string> */
    private array $supportedFeatures = ['charge', 'webhook'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (empty($config['base_url']) || empty($config['member_id']) || empty($config['secure_key'])) {
            throw new PaymentProcessingException('Xoala provider is not configured.');
        }

        // `totype` is inside the request checksum, so a missing one does not
        // fail loudly at Xoala — it fails as a generic rejection on the hosted
        // page, which is an expensive thing to diagnose.
        if (empty($config['totype'])) {
            throw new PaymentProcessingException('Xoala provider requires a `totype`.');
        }

        $this->signatureService = new XoalaSignatureService(
            memberId: (string) $config['member_id'],
            secureKey: (string) $config['secure_key'],
        );

        $this->client = new XoalaClient(
            baseUrl: $this->baseUrl(),
            memberId: (string) $config['member_id'],
            secureKey: (string) $config['secure_key'],
            // Keyed on the ACCOUNT, not the connection name. ConnectionRegistry
            // hands a provider its raw connection config, which carries no
            // connection name — so a name-based key would be the same constant
            // for every account and hand one merchant's token to another's
            // inquiry. Two connections onto one Xoala account (a per-currency
            // split, say) may legitimately share a token, and this gets that
            // right for free.
            cacheKey: md5($this->baseUrl().'|'.$config['member_id']),
            username: ($config['username'] ?? null) ?: null,
        );

        $this->adapter = new XoalaAdapter;
    }

    /**
     * Declare the connection's currency BEFORE the charge so the engine can
     * price the leg.
     *
     * Resolving it here rather than inside `charge()` is what makes it visible
     * to PaymentService, which converts the amount when the declared currency
     * differs from the account's. Doing it privately in `charge()` leaves the
     * engine sending one currency's figure under another's label.
     *
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        $paymentData['currency'] = $this->currency($paymentData);

        return $paymentData;
    }

    /**
     * Open a deposit. No HTTP: see the class docblock.
     *
     * @param  array<string, mixed>  $data
     */
    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);

        $merchantTransactionId = (string) ($data['order_id'] ?? $data['order_number'] ?? 'DEP-'.Str::ulid());

        return $this->adapter->fromProviderResponse([
            'merchant_transaction_id' => $merchantTransactionId,
            'amount' => XoalaSignatureService::amount($validated['amount']),
            'currency' => $this->currency($data),
            'redirect_url' => $this->bridgeUrl($merchantTransactionId),
            'fields' => $this->optionalFields($data),
        ]);
    }

    /**
     * The action and field set for the hosted checkout form.
     *
     * Called by the bridge at render time, not at charge time, and every signed
     * value is read from the transaction row — so the checksum is never
     * persisted and there is no second copy of it to drift.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function checkoutForm(Transaction $transaction): array
    {
        $merchantTransactionId = (string) $transaction->provider_transaction_id;

        // Three different amounts live on the row and they are not
        // interchangeable: `amount` is what the customer deposits and the
        // ledger is credited, `requested_amount` is that figure grossed up
        // for fees — what we actually invoice the PSP — and `charge_amount`
        // is the converted foreign leg, set only when the connection invoices
        // a different currency. This chain mirrors the one
        // WebhookProcessor::deviationBeyondTolerance() uses to compute the
        // expected amount, so the figure we sign here and the figure the
        // reconciliation guard expects can never diverge.
        $amount = XoalaSignatureService::amount(
            $transaction->charge_amount ?? $transaction->requested_amount ?? $transaction->amount
        );
        $currency = strtoupper((string) ($transaction->charge_currency ?? $transaction->currency));

        $redirectUrl = $this->url('redirect_url', 'payment.success');

        $fields = array_filter([
            'memberId' => (string) $this->config['member_id'],
            'totype' => (string) $this->config['totype'],
            'amount' => $amount,
            'currency' => $currency,
            'merchantTransactionId' => $merchantTransactionId,
            'merchantRedirectUrl' => $redirectUrl,
            'notificationUrl' => $this->url('webhook_url', 'cashier.webhooks.xoala'),
            // DB authorizes and captures in one step, which is what a deposit
            // wants. PA would leave the funds held and uncaptured.
            'transactionType' => strtoupper((string) ($this->config['transaction_type'] ?? 'DB')),
            'terminalid' => $this->configString('terminal_id'),
            'paymentMode' => $this->configString('payment_mode'),
            'paymentBrand' => $this->configString('payment_brand'),
            'lang' => $this->language(),
        ], fn ($value) => $value !== null && $value !== '');

        $extras = (array) (($transaction->metadata['xoala_fields'] ?? []) ?: []);

        return [
            'action' => $this->baseUrl().'/transaction/Checkout',
            'fields' => array_merge($extras, $fields, [
                'checksum' => $this->signatureService->forCheckout(
                    (string) $this->config['totype'],
                    $amount,
                    $merchantTransactionId,
                    (string) $redirectUrl,
                ),
            ]),
        ];
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        $data = $this->client->inquiry(
            $transactionId,
            $this->signatureService->forInquiry($transactionId),
        );

        return $data === null ? null : $this->adapter->fromProviderPayload($transactionId, $data);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        return $this->retrieve($transactionId)?->status->value ?? 'unknown';
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('Xoala refunds are not implemented: a refund keys on Xoala’s paymentId, which this driver only learns from a callback.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Xoala capture is not supported in the hosted checkout flow.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Xoala authorize is not supported in the hosted checkout flow.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Xoala reversal is not implemented: it keys on Xoala’s paymentId, which this driver only learns from a callback.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validatePaymentData(array $data): array
    {
        return validator($data, [
            'amount' => 'required|numeric|min:0.01',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return $this->signatureService->verifyCallback($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookTransactionId(array $payload): ?string
    {
        $id = (string) ($payload['merchantTransactionId'] ?? '');

        return $id !== '' ? $id : null;
    }

    public function getName(): string
    {
        return 'xoala';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }

    /**
     * The signed, expiring URL the engine redirects the customer to.
     */
    private function bridgeUrl(string $merchantTransactionId): string
    {
        $name = config('cashier-core.routes.checkout.name_prefix', 'cashier.checkout.').self::ROUTE;

        if (! Route::has($name)) {
            throw new PaymentProcessingException(
                "Xoala needs the `{$name}` route. It is registered by the package unless routes are disabled; register your own bridge if you disabled them."
            );
        }

        return URL::temporarySignedRoute(
            $name,
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['transaction' => $merchantTransactionId],
        );
    }

    /**
     * The customer details Xoala accepts but does not sign.
     *
     * Only these are persisted to metadata. Everything the checksum covers is
     * recomputed from the transaction row at render time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function optionalFields(array $data): array
    {
        $metadata = (array) ($data['metadata'] ?? []);
        $name = trim((string) ($metadata['user_name'] ?? ''));

        return array_filter([
            'email' => (string) ($metadata['user_email'] ?? ''),
            'firstName' => $name !== '' ? Str::before($name, ' ') : '',
            'lastName' => $name !== '' ? trim(Str::after($name, ' ')) : '',
            'ip' => (string) ($metadata['user_ip'] ?? ''),
            'country' => strtoupper((string) ($metadata['user_country'] ?? '')),
            'orderDescription' => (string) ($data['description'] ?? ''),
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * The currency this connection invoices in.
     *
     * Upper-cased so the value the engine compares against the account currency
     * is the value Xoala is sent — a lower-case env entry would otherwise read
     * as "foreign" to the engine and as an unknown currency to Xoala.
     *
     * @param  array<string, mixed>  $data
     */
    private function currency(array $data): string
    {
        return strtoupper((string) (($this->config['currency'] ?? null)
            ?: ($data['currency'] ?? config('cashier-core.currency.default', 'USD'))));
    }

    /**
     * A configured callback/redirect URL, falling back to the named route.
     */
    private function url(string $key, string $fallbackRoute): ?string
    {
        $url = trim((string) ($this->config[$key] ?? ''));

        if ($url !== '') {
            return $url;
        }

        return Route::has($fallbackRoute) ? route($fallbackRoute) : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/');
    }

    private function configString(string $key): ?string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * The hosted page's language: two or three letters, defaulting to English.
     */
    private function language(): string
    {
        $configured = $this->configString('language');

        return strtolower($configured ?? substr(app()->getLocale(), 0, 2)) ?: 'en';
    }
}
