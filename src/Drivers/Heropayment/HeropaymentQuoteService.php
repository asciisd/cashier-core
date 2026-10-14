<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Pre-redirect deposit numbers for Heropayment: the exchange rate, network fee
 * and minimum for a given crypto, before the customer reaches the widget.
 *
 * Which cryptos we offer is *not* decided here — the `currencies` list on the
 * Heropayment connection is the single source of truth, and the customer picks
 * one from it in the deposit modal. Pass those tickers to
 * {@see self::availableCurrencies()} rather than maintaining a second list.
 */
final class HeropaymentQuoteService
{
    private const DEPOSIT = 'deposit';

    private const WITHDRAWAL = 'withdrawal';

    /**
     * Strict decimal, applied after trim(): a withdrawal network fee that
     * fails this (null, missing, "", "n/a", ...) is never read as zero.
     */
    private const NUMERIC_PATTERN = '/^\d+(\.\d+)?$/';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly HeropaymentClient $client,
        private readonly array $config = [],
    ) {}

    /**
     * Supported currencies, each annotated with its deposit network fee.
     *
     * Pass $tickers (the connection's `currencies`) to narrow and order the
     * result; omit it for the full list.
     *
     * @param  list<string>  $tickers
     * @return list<array<string, mixed>>
     */
    public function availableCurrencies(array $tickers = []): array
    {
        $currencies = $this->remember('currencies', $this->referenceTtl(), fn () => $this->client->getCurrencies()) ?? [];

        $allowed = $this->normalizeTickers($tickers);
        $fees = $this->depositNetworkFees();

        $annotate = function (array $currency) use ($fees): array {
            $currency['network_fee'] = $fees[strtolower((string) ($currency['ticker'] ?? ''))] ?? null;

            return $currency;
        };

        if ($allowed === []) {
            return array_map($annotate, $currencies);
        }

        $byTicker = [];

        foreach ($currencies as $currency) {
            $byTicker[strtolower((string) ($currency['ticker'] ?? ''))] = $currency;
        }

        $picked = [];

        foreach ($allowed as $ticker) {
            if (isset($byTicker[$ticker])) {
                $picked[] = $annotate($byTicker[$ticker]);
            }
        }

        return $picked;
    }

    /**
     * Quote a deposit of $amount (in the account's price currency) paid in $payCurrency.
     *
     * @throws PaymentProcessingException when the currency has no rate available
     */
    public function quote(float $amount, string $payCurrency, ?string $priceCurrency = null): HeropaymentQuote
    {
        $payCurrency = strtolower($payCurrency);
        $priceCurrency = strtolower($priceCurrency ?? (string) config('cashier-core.currency.default', 'USD'));

        $rate = $this->rate($priceCurrency, $payCurrency);

        if ($rate === null || $rate <= 0.0) {
            throw new PaymentProcessingException("Heropayment has no {$priceCurrency}/{$payCurrency} rate available.");
        }

        $payAmount = $amount * $rate;

        $networkFee = $this->depositNetworkFees()[$payCurrency] ?? null;
        $minDeposit = $this->minDeposit($payCurrency);

        // Fees and minimums come back in the pay currency's own units; dividing
        // by the rate expresses them in the price currency the customer sees.
        $networkFeeInPrice = $networkFee !== null ? $networkFee / $rate : null;
        $minDepositInPrice = $minDeposit !== null ? $minDeposit / $rate : null;

        $feePercent = $this->providerFeePercent();
        $feeAmount = $feePercent !== null ? $amount * $feePercent / 100 : null;

        return new HeropaymentQuote(
            priceCurrency: $priceCurrency,
            priceAmount: $amount,
            payCurrency: $payCurrency,
            rate: $rate,
            payAmount: $payAmount,
            networkFee: $networkFee,
            networkFeeInPriceCurrency: $networkFeeInPrice,
            providerFeePercent: $feePercent,
            providerFeeAmount: $feeAmount,
            minDeposit: $minDeposit,
            minDepositInPriceCurrency: $minDepositInPrice,
            meetsMinimum: $minDepositInPrice === null || $amount >= $minDepositInPrice,
            estimatedNetCredit: max(0.0, $amount - ($feeAmount ?? 0.0) - ($networkFeeInPrice ?? 0.0)),
            quotedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Spot rate: units of $to per 1 unit of $from. Null when unavailable.
     *
     * Heropayments quotes deposits and withdrawals separately, so the type is
     * part of both the request and the cache key.
     */
    public function rate(string $from, string $to, string $transactionType = self::DEPOSIT): ?float
    {
        $payload = $this->remember(
            "rate:{$transactionType}:{$from}:{$to}",
            $this->quoteTtl(),
            fn () => $this->client->getRate($from, $to, $transactionType),
        );

        return isset($payload['rate']) ? (float) $payload['rate'] : null;
    }

    /**
     * Minimum deposit for a ticker, in that ticker's own units. Null when unavailable.
     */
    public function minDeposit(string $currency): ?float
    {
        $payload = $this->remember(
            "min-amount:{$currency}",
            $this->referenceTtl(),
            fn () => $this->client->getMinAmount(currency: $currency),
        );

        return isset($payload['minDeposit']) ? (float) $payload['minDeposit'] : null;
    }

    /**
     * Minimum withdrawal for a ticker, in that ticker's own units. Null when unavailable.
     */
    public function minWithdrawal(string $currency): ?float
    {
        $payload = $this->remember(
            "min-amount:{$currency}",
            $this->referenceTtl(),
            fn () => $this->client->getMinAmount(currency: $currency),
        );

        return isset($payload['minWithdrawal']) ? (float) $payload['minWithdrawal'] : null;
    }

    /**
     * Deposit network fees keyed by lowercased ticker, in native currency units.
     *
     * @return array<string, float>
     */
    public function depositNetworkFees(): array
    {
        return $this->networkFees(self::DEPOSIT);
    }

    /**
     * Withdrawal network fees keyed by lowercased ticker, in native currency
     * units (see HeropaymentClient::getNetworkFees() on why not USDT).
     *
     * @return array<string, float>
     */
    public function withdrawalNetworkFees(): array
    {
        return $this->networkFees(self::WITHDRAWAL);
    }

    /**
     * @return array<string, float>
     */
    private function networkFees(string $type): array
    {
        $rows = $this->remember('network-fees', $this->referenceTtl(), fn () => $this->client->getNetworkFees()) ?? [];

        $fees = [];

        foreach ($rows as $row) {
            if (($row['type'] ?? null) !== $type) {
                continue;
            }

            $ticker = strtolower((string) ($row['ticker'] ?? ''));

            // Withdrawal fees gate a real money transfer: a null, missing or
            // non-numeric networkfee must never be read as zero, so the
            // ticker is simply absent from the map (preflight then refuses
            // with QUOTE_UNAVAILABLE instead of assuming no fee). Deposit
            // rows keep the historical `?? 0` behaviour the deposit screen
            // depends on.
            if ($type === self::WITHDRAWAL) {
                $raw = trim((string) ($row['networkfee'] ?? ''));

                if (! preg_match(self::NUMERIC_PATTERN, $raw)) {
                    continue;
                }

                $fees[$ticker] = (float) $raw;

                continue;
            }

            $fees[$ticker] = (float) ($row['networkfee'] ?? 0);
        }

        return $fees;
    }

    /**
     * The contracted processing fee percentage.
     *
     * Heropayments exposes no endpoint for this — `feePercent` only appears on a
     * created payment and on status callbacks. The configured value is what we
     * display; reconcile it against the callback's `feePercent` after the fact.
     */
    public function providerFeePercent(): ?float
    {
        $percent = $this->config['fee_percent'] ?? null;

        return $percent === null || $percent === '' ? null : (float) $percent;
    }

    /**
     * @param  list<string>  $tickers
     * @return list<string>
     */
    private function normalizeTickers(array $tickers): array
    {
        return array_values(array_filter(array_map(
            fn ($ticker) => strtolower(trim((string) $ticker)),
            $tickers,
        )));
    }

    /**
     * Reference data (currencies, network fees, minimums) is cached longer than
     * rates. A lookup failure is cached as null for a short window so a provider
     * outage cannot turn one deposit screen into a burst of retries.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null null when the lookup failed (cached briefly as a negative result)
     */
    private function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = Cache::remember("heropayment:quote:{$key}", $ttl, function () use ($key, $callback) {
            try {
                return ['value' => $callback()];
            } catch (\Throwable $e) {
                PaymentLogger::providerQuoteLookupFailed('heropayment', $key, $e->getMessage());

                return ['value' => null];
            }
        });

        return $cached['value'];
    }

    private function quoteTtl(): int
    {
        return (int) ($this->config['quote_cache_ttl'] ?? 60);
    }

    private function referenceTtl(): int
    {
        return (int) ($this->config['reference_cache_ttl'] ?? 300);
    }
}
