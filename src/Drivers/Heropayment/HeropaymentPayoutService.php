<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Route;

/**
 * Heropayments V2 withdrawals (payouts).
 *
 * Three facts shape this class. There is no sandbox, so the first live send
 * moves real money — hence the enable flag and the fail-closed cap. The
 * deduction from our balance is amount + processing fee + network fee, and
 * the fee percentage is only revealed after the payout exists — hence the
 * configured fee_percent and a safety buffer. And `externalOrderId` must be
 * unique, which is the idempotency key a retried send relies on.
 */
final class HeropaymentPayoutService implements SendsPayouts
{
    private const WITHDRAWAL = 'withdrawal';

    /**
     * Strict decimal: bcmath 8.3+ throws a ValueError on "2.5e1", padded or
     * signed strings that is_numeric() accepts. Applied after trim().
     */
    private const NUMERIC_PATTERN = '/^\d+(\.\d+)?$/';

    private const SCALE = 8;

    private const DEFAULT_BUFFER_PERCENT = '2';

    private HeropaymentClient $client;

    private HeropaymentQuoteService $quotes;

    private HeropaymentAdapter $adapter;

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.heropayment` array
     */
    public function __construct(
        private readonly array $config,
        ?HeropaymentClient $client = null,
        ?HeropaymentQuoteService $quotes = null,
    ) {
        $this->client = $client ?? HeropaymentClient::fromConfig($config);
        $this->quotes = $quotes ?? new HeropaymentQuoteService($this->client, $config);
        $this->adapter = new HeropaymentAdapter;
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        $amount = $this->guard($request);
        $currency = strtolower($request->currency);
        $payout = strtolower(trim($request->payoutCurrency));

        // The minimum is in the payout coin's own units.
        $minimum = $this->quotes->minWithdrawal($payout);
        $toPayout = $this->quotes->rate($currency, $payout, self::WITHDRAWAL);

        if ($minimum === null || $toPayout === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::QUOTE_UNAVAILABLE,
                "Heropayments has no withdrawal quote for {$payout} right now.",
            );
        }

        $inPayout = bcmul($amount, self::decimal($toPayout), self::SCALE);

        if (bccomp($inPayout, self::decimal($minimum), self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::BELOW_MINIMUM,
                sprintf('%s %s is below the Heropayments minimum withdrawal of %s %s.', $inPayout, $payout, self::decimal($minimum), $payout),
            );
        }

        // Live, never cached: a stale balance defeats the check. A
        // ConnectionException (timeout) is treated exactly like an
        // unreadable balance — refuse, never pass.
        try {
            $wallet = $this->client->getBalance();
        } catch (ConnectionException) {
            return PayoutPreflight::refused(
                PayoutPreflight::BALANCE_UNAVAILABLE,
                'The Heropayments balance could not be read. Nothing was sent.',
            );
        }

        $balance = trim((string) ($wallet['balance'] ?? ''));
        $walletCurrency = strtolower(trim((string) ($wallet['walletCurrency'] ?? '')));

        if (! preg_match(self::NUMERIC_PATTERN, $balance) || $walletCurrency === '') {
            return PayoutPreflight::refused(
                PayoutPreflight::BALANCE_UNAVAILABLE,
                'The Heropayments balance could not be read. Nothing was sent.',
            );
        }

        $required = $this->required($amount, $currency, $payout, $walletCurrency);

        if ($required === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::QUOTE_UNAVAILABLE,
                "Heropayments has no withdrawal rate or network fee for {$payout} right now.",
            );
        }

        if (bccomp($balance, $required, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::INSUFFICIENT_FUNDS,
                sprintf('Heropayments balance %s %s; this payout needs ~%s %s.', $balance, $walletCurrency, $required, $walletCurrency),
                $balance,
                $required,
                $walletCurrency,
            );
        }

        return PayoutPreflight::passed($balance, $required, $walletCurrency);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        $amount = $this->guard($request);
        $callbackUrl = $this->callbackUrl();

        $body = array_filter([
            'customerId' => $request->customerId,
            'payoutAddress' => trim($request->payoutAddress),
            'payoutCurrency' => strtolower(trim($request->payoutCurrency)),
            'priceCurrency' => strtolower($request->currency),
            'priceAmount' => $amount,
            'payoutExtraId' => $request->payoutExtraId,
            'customerEmail' => $request->customerEmail,
            'externalOrderId' => $request->externalOrderId,
            'callbackUrl' => $callbackUrl,
        ], fn ($value) => $value !== null && $value !== '');

        // The withdrawal is priced in the account's fiat currency.
        $body['fiat'] = true;

        try {
            $response = $this->client->createWithdrawal($body);
        } catch (ConnectionException $e) {
            throw new PayoutOutcomeUnknownException(
                "Heropayments did not answer the withdrawal request ({$e->getMessage()}). "
                .'It may have been created — do not resend before checking its status.',
            );
        }

        if ($response->successful()) {
            $payload = (array) $response->json();

            // The id is the only handle on the payout. A 2xx without one is as
            // ambiguous as a 5xx, not a success.
            if ((string) ($payload['id'] ?? '') === '') {
                throw new PayoutOutcomeUnknownException(
                    'Heropayments accepted the withdrawal but returned no payment id. '
                    .'It may have been created — do not resend before checking its status.',
                );
            }

            return $this->adapter->payoutReceipt($payload);
        }

        $message = $this->errorMessage($response);

        if ($response->clientError()) {
            // externalOrderId is unique per merchant: "not unique" means an
            // earlier attempt with this id landed. Resolve it, never resend.
            if (str_contains(strtolower($message), 'not unique')) {
                try {
                    $existing = $this->lookup($request->externalOrderId);
                } catch (PayoutOutcomeUnknownException $e) {
                    throw new PayoutOutcomeUnknownException(
                        "Heropayments reports order {$request->externalOrderId} already exists, but it could not be looked up ({$e->getMessage()}).",
                    );
                }

                if ($existing !== null) {
                    return $existing;
                }

                throw new PayoutOutcomeUnknownException(
                    "Heropayments reports order {$request->externalOrderId} already exists, but it could not be looked up.",
                );
            }

            throw new PayoutRejectedException($message);
        }

        // errors.md: on "timeout of 15000ms exceeded" and "internal server
        // error", check whether the withdrawal was created. Every 5xx is
        // treated that way — the lookup resolves the ones that were rejections.
        throw new PayoutOutcomeUnknownException(
            sprintf(
                'Heropayments returned %d for the withdrawal request: %s. It may have been created — do not resend before checking its status.',
                $response->status(),
                $message,
            ),
        );
    }

    /**
     * Tri-state on purpose: a lookup that failed must never read as "not
     * found", or an unknown payout that landed would be sent a second time.
     * Only a 404 is "not found" (quirks #14, unverified).
     */
    public function lookup(string $externalOrderId): ?PayoutReceipt
    {
        try {
            $response = $this->client->getPaymentByOrderIdResponse($externalOrderId);
        } catch (ConnectionException $e) {
            throw new PayoutOutcomeUnknownException(
                "Heropayments lookup failed for order {$externalOrderId}: {$e->getMessage()}",
            );
        }

        if ($response->status() === 404) {
            return null;
        }

        $payload = $response->successful() ? $response->json() : null;

        if (! is_array($payload) || (string) ($payload['id'] ?? '') === '') {
            throw new PayoutOutcomeUnknownException(sprintf(
                'Heropayments lookup failed for order %s: HTTP %d%s.',
                $externalOrderId,
                $response->status(),
                $response->successful() ? ' without a payment id' : ' '.$this->errorMessage($response),
            ));
        }

        return $this->adapter->payoutReceipt($payload);
    }

    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        return $this->adapter->payoutReceipt($payload);
    }

    /**
     * Payouts are closed by callback, so a send without one is refused.
     * Falls back to the package's own webhook route under its configured
     * name prefix.
     */
    private function callbackUrl(): string
    {
        $route = config('cashier-core.routes.name_prefix', 'cashier.webhooks.').'heropayment';

        $url = $this->config['webhook_url'] ?? (Route::has($route) ? route($route) : null);

        if ($url === null || $url === '') {
            throw new PaymentProcessingException(
                'Heropayment withdrawals need a callback URL: set webhook_url or register the package webhook routes.',
            );
        }

        return (string) $url;
    }

    private function errorMessage(Response $response): string
    {
        $json = $response->json();
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        if (is_array($message)) {
            $message = implode('; ', array_map('strval', $message));
        }

        $message = trim((string) ($message ?? $response->body()));

        return $message === '' ? "HTTP {$response->status()}" : $message;
    }

    /**
     * What Heropayments will deduct, in the wallet currency:
     * (amount × rate × (1 + fee%) + networkFee × payout→wallet rate) × (1 + buffer%).
     *
     * Network fees are native payout-coin units (HeropaymentClient::getNetworkFees()),
     * so they are converted — skipped when the payout coin is the wallet coin.
     * Null when any input is unavailable: an unknown fee is never assumed zero.
     */
    private function required(string $amount, string $currency, string $payout, string $walletCurrency): ?string
    {
        $toWallet = $this->quotes->rate($currency, $walletCurrency, self::WITHDRAWAL);
        $networkFee = $this->quotes->withdrawalNetworkFees()[$payout] ?? null;
        $feeToWallet = $payout === $walletCurrency ? 1.0 : $this->quotes->rate($payout, $walletCurrency, self::WITHDRAWAL);

        if ($toWallet === null || $networkFee === null || $feeToWallet === null) {
            return null;
        }

        $base = bcmul($amount, self::decimal($toWallet), self::SCALE);
        $withFee = bcmul($base, self::onePlusPercent($this->feePercent()), self::SCALE);
        $withNetwork = bcadd($withFee, bcmul(self::decimal($networkFee), self::decimal($feeToWallet), self::SCALE), self::SCALE);

        return bcmul($withNetwork, self::onePlusPercent($this->bufferPercent()), self::SCALE);
    }

    /**
     * Fail closed on configuration and input; returns the trimmed amount.
     *
     * @throws PaymentProcessingException
     */
    private function guard(PayoutRequest $request): string
    {
        if (! filter_var($this->config['withdrawals_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // Missing, empty, zero or malformed is a configuration error — never "no cap".
        $cap = trim((string) ($this->config['withdrawal_max_amount'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $cap)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals are enabled but no valid withdrawal_max_amount is configured. Refusing to send.',
            );
        }

        if (bccomp($cap, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException(
                sprintf('Heropayment withdrawal cap is configured as %s, which blocks all withdrawals.', $cap),
            );
        }

        $this->feePercent();

        $amount = trim($request->amount);

        if (! preg_match(self::NUMERIC_PATTERN, $amount) || bccomp($amount, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException('Heropayment withdrawal amount must be a positive number.');
        }

        if (bccomp($amount, $cap, self::SCALE) > 0) {
            throw new PaymentProcessingException(
                sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap),
            );
        }

        if (trim($request->payoutAddress) === '') {
            throw new PaymentProcessingException('Heropayment withdrawal requires a payout address.');
        }

        if (trim($request->payoutCurrency) === '') {
            throw new PaymentProcessingException('Heropayment withdrawal requires a payout currency.');
        }

        return $amount;
    }

    private function feePercent(): string
    {
        $percent = trim((string) ($this->config['fee_percent'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $percent)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals need fee_percent configured to estimate the balance a payout requires.',
            );
        }

        return $percent;
    }

    private function bufferPercent(): string
    {
        $percent = trim((string) ($this->config['balance_buffer_percent'] ?? self::DEFAULT_BUFFER_PERCENT));

        return preg_match(self::NUMERIC_PATTERN, $percent) ? $percent : self::DEFAULT_BUFFER_PERCENT;
    }

    private static function onePlusPercent(string $percent): string
    {
        return bcadd('1', bcdiv($percent, '100', self::SCALE), self::SCALE);
    }

    private static function decimal(float $value): string
    {
        return sprintf('%.8F', $value);
    }
}
