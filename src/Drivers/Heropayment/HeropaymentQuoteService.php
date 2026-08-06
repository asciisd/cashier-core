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
     */
    public function rate(string $from, string $to): ?float
    {
        $payload = $this->remember(
            "rate:{$from}:{$to}",
            $this->quoteTtl(),
            fn () => $this->client->getRate($from, $to, self::DEPOSIT),
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
     * Deposit network fees keyed by lowercased ticker, in native currency units.
     *
     * @return array<string, float>
     */
    public function depositNetworkFees(): array
    {
        $rows = $this->remember('network-fees', $this->referenceTtl(), fn () => $this->client->getNetworkFees()) ?? [];

        $fees = [];

        foreach ($rows as $row) {
            if (($row['type'] ?? null) !== self::DEPOSIT) {
                continue;
            }

            $fees[strtolower((string) ($row['ticker'] ?? ''))] = (float) ($row['networkfee'] ?? 0);
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
