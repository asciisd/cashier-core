<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * PayCrossBorder payouts: B2C from our pre-registered business to the
 * withdrawing customer, on the crypto, bank or wallet rail.
 *
 * Four facts shape this class. A payout needs a fresh quote — quotes live
 * minutes — so send() always quotes first and pins the quote to our exact
 * amount. There is no idempotency key, so the payout POST is never retried
 * and anything ambiguous is Unknown. There is no callback, so the host polls
 * lookup(). And PCB can only be searched by its own transId, so lookup()
 * without one throws, and every answer is tied back to our reference.
 */
final class PayCrossBorderPayoutService implements SendsPayouts
{
    /**
     * Strict decimal: bcmath 8.3+ throws a ValueError on "2.5e1", padded or
     * signed strings that is_numeric() accepts. Applied after trim().
     */
    private const NUMERIC_PATTERN = '/^\d+(\.\d+)?$/';

    private const SCALE = 8;

    private const DEFAULT_BUFFER_PERCENT = '2';

    /** Definite refusals of the payout POST: nothing created. Every other 4xx is ambiguous. */
    private const REJECTED_STATUSES = [400, 401, 403, 422];

    private const CHARGE_TYPES = ['BEN', 'SHA', 'OUR'];

    private const MAIN_WALLET = 'main wallet';

    private PayCrossBorderClient $client;

    private PayCrossBorderAdapter $adapter;

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.*` array
     */
    public function __construct(private readonly array $config, ?PayCrossBorderClient $client = null)
    {
        $this->client = $client ?? PayCrossBorderClient::fromConfig($config);
        $this->adapter = new PayCrossBorderAdapter;
    }

    /**
     * PCB's `sendClientTrxReference` (10–30 letters and digits) for our order
     * id: the id without hyphens and underscores when that fits — readable in
     * PCB's back office — else a hash. Deterministic, so lookup() recomputes
     * it. Ids differing only in hyphens or underscores would collide; the
     * workflow's WD-<ULID>[-n] ids never do.
     */
    public static function reference(string $externalOrderId): string
    {
        $readable = strtoupper(str_replace(['-', '_'], '', trim($externalOrderId)));

        if (preg_match('/^[A-Z0-9]{10,30}$/', $readable)) {
            return $readable;
        }

        return 'PCB'.strtoupper(substr(hash('sha256', $externalOrderId), 0, 27));
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        $amount = $this->guard($request);
        $currency = $this->sendCurrency();

        // Live, never cached: a stale balance defeats the check. Any failure
        // refuses — it never passes.
        try {
            $wallet = $this->mainWallet($this->client->balance($this->walletOwnerCode()), $currency);
        } catch (PaymentProcessingException) {
            $wallet = null;
        }

        $balance = $wallet === null ? null : self::decimal($wallet['value'] ?? null);

        if ($balance === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::BALANCE_UNAVAILABLE,
                "The PayCrossBorder {$currency} Main Wallet balance could not be read. Nothing was sent.",
            );
        }

        $minimum = self::decimal($wallet['minTransValue'] ?? null);

        if ($minimum !== null && bccomp($amount, $minimum, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::BELOW_MINIMUM,
                sprintf('%s %s is below the PayCrossBorder minimum of %s %s.', $amount, $currency, $minimum, $currency),
            );
        }

        $required = bcmul($amount, self::onePlusPercent($this->bufferPercent()), self::SCALE);

        if (bccomp($balance, $required, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::INSUFFICIENT_FUNDS,
                sprintf('PayCrossBorder balance %s %s; this payout needs %s %s.', $balance, $currency, $required, $currency),
                $balance,
                $required,
                $currency,
            );
        }

        return PayoutPreflight::passed($balance, $required, $currency);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        $amount = $this->guard($request);

        /** @var \Asciisd\CashierCore\DataObjects\PayoutBeneficiary $beneficiary guaranteed by guard() */
        $beneficiary = $request->beneficiary;
        $rail = Rail::for($beneficiary);
        $country = $beneficiary->address->country;
        $receiveCurrency = $this->currencyCode($request->payoutCurrency);
        $reference = self::reference($request->externalOrderId);

        $quote = $this->quote($amount, $receiveCurrency, $country, $rail);

        $body = [
            'transactionInfo' => [
                'payinAmount' => $amount,
                'payinCurrency' => $this->sendCurrency(),
                'type' => 'B2C',
                'requestDate' => now()->format('d-m-Y'),
                'sendClientTrxReference' => $reference,
                'descriptionText' => substr($reference, 0, 25),
                'paymentMode' => $rail->paymentMode(),
                'sendClientCode' => $this->setting('sender_code'),
                'payoutCurrency' => $receiveCurrency,
                'payoutAmount' => (string) $quote['payoutAmount'],
                'destinationCountryCode' => $country,
                'settlementCurrency' => $this->sendCurrency(),
                'sourceCountry' => $this->setting('source_country'),
                'fxRateValue' => (string) $quote['fxRateValue'],
                // The docs: "in case of 0 it will reflect the fxRateValue".
                'senderMargin' => (string) ($quote['senderMargin'] ?? $quote['fxRateValue']),
            ],
            'sender' => ['business' => [
                'isAutoRegistered' => false,
                'declaration' => false,
                'docReferenceNumber' => $reference,
                'customerCode' => $this->setting('customer_code'),
            ]],
            'receiver' => $rail->builder()->build($request),
            'compliance' => [
                'forexQuoteId' => (string) $quote['code'],
                'remittancePurpose' => $this->setting('remittance_purpose'),
                'sourceOfFund' => $this->setting('source_of_fund'),
                'relationship' => $this->setting('relationship'),
            ],
        ];

        try {
            $response = $this->client->payout($body);
        } catch (ConnectionException $e) {
            throw new PayoutOutcomeUnknownException(
                "PayCrossBorder did not answer the payout request ({$e->getMessage()}). "
                .'It may have been created — do not resend before checking its status.',
            );
        }

        return $this->sendReceipt($response);
    }

    /**
     * Never null: PCB cannot say "no such payout" for our order id, because
     * it can only be searched by its own transId.
     */
    public function lookup(string $externalOrderId, ?string $reference = null): ?PayoutReceipt
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            throw new PayoutOutcomeUnknownException(
                "PayCrossBorder can only look a payout up by its own transaction id, not by order {$externalOrderId}. "
                .'Link the PayCrossBorder reference, or confirm the payout was never sent.',
            );
        }

        try {
            $response = $this->client->transaction($reference);
        } catch (PaymentProcessingException $e) {
            throw new PayoutOutcomeUnknownException("PayCrossBorder lookup failed for {$reference}: {$e->getMessage()}", previous: $e);
        }

        $rows = $response['transactionInfoResponse'] ?? null;

        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[array_key_first($rows)])) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder returned %d transactions for %s; the payout outcome is unknown.',
                is_array($rows) ? count($rows) : 0,
                $reference,
            ));
        }

        $row = $rows[array_key_first($rows)];
        $returned = (string) ($row['transId'] ?? '');

        if ($returned !== $reference) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder answered for transaction %s when %s was requested; the payout outcome is unknown.',
                $returned === '' ? '(no id)' : $returned,
                $reference,
            ));
        }

        $owner = (string) ($row['senderUniqueId'] ?? '');

        if ($owner !== self::reference($externalOrderId)) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder transaction %s belongs to %s, not order %s.',
                $reference,
                $owner === '' ? '(no reference)' : $owner,
                $externalOrderId,
            ));
        }

        return $this->adapter->payoutReceipt($row);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        throw new PaymentProcessingException('PayCrossBorder sends no payout callbacks; payouts are polled.');
    }

    /**
     * Fail closed on configuration and input; returns the trimmed amount.
     * Shared by preflight() and send(), and makes no HTTP call.
     *
     * @throws PaymentProcessingException
     */
    private function guard(PayoutRequest $request): string
    {
        if (! filter_var($this->config['withdrawals_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new PaymentProcessingException(
                'PayCrossBorder withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // Missing, empty, zero or malformed is a configuration error — never "no cap".
        $cap = trim((string) ($this->config['withdrawal_max_amount'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $cap) || bccomp($cap, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException(
                'PayCrossBorder withdrawals are enabled but no valid withdrawal_max_amount is configured. Refusing to send.',
            );
        }

        $amount = trim($request->amount);

        if (! preg_match(self::NUMERIC_PATTERN, $amount) || bccomp($amount, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException('PayCrossBorder withdrawal amount must be a positive number.');
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new PaymentProcessingException('PayCrossBorder withdrawal amount must have at most 2 decimal places.');
        }

        if (bccomp($amount, $cap, self::SCALE) > 0) {
            throw new PaymentProcessingException(sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap));
        }

        if ($request->beneficiary === null) {
            throw new PaymentProcessingException('PayCrossBorder payouts need a beneficiary in the withdrawal details.');
        }

        $send = $this->sendCurrency();

        if ($this->currencyCode($request->currency) !== $send) {
            throw new PaymentProcessingException(sprintf(
                'An %s withdrawal cannot be paid from the PayCrossBorder %s wallet.',
                strtoupper($request->currency),
                $send,
            ));
        }

        $this->currencyCode($request->payoutCurrency);

        Rail::for($request->beneficiary)->builder()->assertComplete($request);

        return $amount;
    }

    /**
     * A fresh quote pinned to our amount.
     *
     * @return array<string, mixed> the `fxRateQuotation` object
     *
     * @throws PaymentProcessingException nothing was sent
     */
    private function quote(string $amount, string $receiveCurrency, string $country, Rail $rail): array
    {
        $response = $this->client->quote([
            'payinAmount' => $amount,
            'payoutAmount' => '',
            'sendCurrencyCode' => $this->sendCurrency(),
            'receiveCurrencyCode' => $receiveCurrency,
            'settlementCurrencyCode' => $this->sendCurrency(),
            'destinationCountryCode' => $country,
            'sourceCountry' => $this->setting('source_country'),
            'serviceTypeCode' => 'B2C',
            'paymentMode' => $rail->paymentMode(),
            'senderCode' => $this->setting('sender_code'),
            'customerCode' => $this->setting('customer_code'),
            'chargeTypeCode' => $this->chargeType(),
        ]);

        $quote = $response['fxRateQuotation'] ?? null;

        foreach (['code', 'fxRateValue', 'payinAmount', 'payoutAmount'] as $key) {
            if (! is_array($quote) || ! is_scalar($quote[$key] ?? null) || (string) $quote[$key] === '') {
                throw new PaymentProcessingException("PayCrossBorder returned a quote without {$key}. Nothing was sent.");
            }
        }

        $quoted = self::decimal($quote['payinAmount']);

        if ($quoted === null || bccomp($quoted, $amount, self::SCALE) !== 0) {
            throw new PaymentProcessingException(sprintf(
                'PayCrossBorder quoted %s for a %s payout. Nothing was sent.',
                (string) $quote['payinAmount'],
                $amount,
            ));
        }

        return $quote;
    }

    /**
     * @throws PayoutRejectedException
     * @throws PayoutOutcomeUnknownException
     */
    private function sendReceipt(Response $response): PayoutReceipt
    {
        $json = $response->json();
        $bean = is_array($json) && is_array($json['payoutResponseBean'] ?? null) ? $json['payoutResponseBean'] : [];
        $reference = trim((string) ($bean['transReference'] ?? ''));
        $resultCode = is_array($json) && is_scalar($json['resultCode'] ?? null) ? (string) $json['resultCode'] : null;

        if ($response->successful()) {
            if ($reference !== '' && $resultCode === '0') {
                return new PayoutReceipt(
                    reference: $reference,
                    rawStatus: (string) ($bean['status'] ?? ''),
                    state: PayoutState::Sent,
                    payload: $json,
                );
            }

            if ($reference === '' && $resultCode !== null && $resultCode !== '0') {
                throw new PayoutRejectedException('PayCrossBorder refused the payout: '.PayCrossBorderClient::describe($response));
            }

            // The reference is the only handle on the payout. A 2xx without
            // one is as ambiguous as a 5xx, not a success.
            throw new PayoutOutcomeUnknownException(
                'PayCrossBorder answered the payout without a transaction reference ('.PayCrossBorderClient::describe($response).'). '
                .'It may have been created — do not resend before checking its status.',
            );
        }

        if (in_array($response->status(), self::REJECTED_STATUSES, true)) {
            throw new PayoutRejectedException('PayCrossBorder refused the payout: '.PayCrossBorderClient::describe($response));
        }

        throw new PayoutOutcomeUnknownException(sprintf(
            'PayCrossBorder returned %d for the payout request: %s. It may have been created — do not resend before checking its status.',
            $response->status(),
            PayCrossBorderClient::describe($response),
        ));
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function mainWallet(array $response, string $currency): ?array
    {
        foreach ((array) ($response['walletList'] ?? []) as $wallet) {
            if (is_array($wallet)
                && strtolower(trim((string) ($wallet['walletTypeName'] ?? ''))) === self::MAIN_WALLET
                && strtoupper(trim((string) ($wallet['currencyName'] ?? ''))) === strtoupper($currency)) {
                return $wallet;
            }
        }

        return null;
    }

    /**
     * Our currency code → PCB's, via `currency_codes` (case-insensitive keys).
     *
     * @throws PaymentProcessingException when unmapped
     */
    private function currencyCode(string $ours): string
    {
        $key = strtolower(trim($ours));

        foreach ((array) ($this->config['currency_codes'] ?? []) as $code => $pcb) {
            if (strtolower(trim((string) $code)) === $key && is_string($pcb) && trim($pcb) !== '') {
                return trim($pcb);
            }
        }

        throw new PaymentProcessingException("PayCrossBorder has no currency code configured for {$key}; add it to currency_codes.");
    }

    private function sendCurrency(): string
    {
        return $this->setting('send_currency');
    }

    private function walletOwnerCode(): string
    {
        $code = trim((string) ($this->config['wallet_owner_code'] ?? ''));

        return $code !== '' ? $code : $this->setting('sender_code');
    }

    private function chargeType(): string
    {
        $type = strtoupper(trim((string) ($this->config['charge_type'] ?? 'OUR')));

        if (! in_array($type, self::CHARGE_TYPES, true)) {
            throw new PaymentProcessingException("PayCrossBorder charge_type must be BEN, SHA or OUR, not '{$type}'.");
        }

        return $type;
    }

    /**
     * @throws PaymentProcessingException when the connection lacks the key
     */
    private function setting(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        if ($value === '') {
            throw new PaymentProcessingException("PayCrossBorder connection is missing {$key}.");
        }

        return $value;
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

    /**
     * A PCB number (JSON float or string) as a strict decimal string, or null
     * when it is not one — never coerced to zero.
     */
    private static function decimal(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match(self::NUMERIC_PATTERN, $value) ? $value : null;
    }
}
