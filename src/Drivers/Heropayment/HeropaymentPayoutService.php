<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Http\Client\ConnectionException;
use LogicException;

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
        throw new LogicException('Implemented in Task 4.');
    }

    public function lookup(string $externalOrderId): ?PayoutReceipt
    {
        throw new LogicException('Implemented in Task 4.');
    }

    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        throw new LogicException('Implemented in Task 4.');
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
