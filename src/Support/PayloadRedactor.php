<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Illuminate\Support\Str;

/**
 * Strips PII and secrets from PSP payloads before they reach a log line.
 *
 * One redactor for every driver, driven by `cashier-core.security.redact_keys`
 * — the alternative is what it replaces: per-controller private helpers with
 * divergent hardcoded field lists, where the sixth webhook controller forgets
 * the list entirely.
 *
 * Keys support a trailing wildcard (`card*` matches `card_number`,
 * `cardholder`, …). Matching is case-insensitive and applies recursively.
 */
class PayloadRedactor
{
    public const MASK = '[redacted]';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $patterns = $this->patterns();

        $walker = function (array $data) use (&$walker, $patterns): array {
            $clean = [];

            foreach ($data as $key => $value) {
                if (is_string($key) && $this->matches($key, $patterns)) {
                    $clean[$key] = self::MASK;

                    continue;
                }

                $clean[$key] = is_array($value) ? $walker($value) : $value;
            }

            return $clean;
        };

        return $walker($payload);
    }

    /**
     * Redact query-string and fragment from a URL — hosted-checkout links
     * routinely carry one-time session tokens.
     */
    public function redactUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        return strtok($url, '?#') ?: $url;
    }

    /**
     * @param  list<string>  $patterns
     */
    protected function matches(string $key, array $patterns): bool
    {
        $key = Str::lower($key);

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($key, rtrim($pattern, '*'))) {
                    return true;
                }

                continue;
            }

            if ($key === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function patterns(): array
    {
        return array_map(
            fn ($key): string => Str::lower((string) $key),
            (array) config('cashier-core.security.redact_keys', [])
        );
    }
}
