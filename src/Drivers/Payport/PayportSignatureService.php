<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Payport;

/**
 * Payport callback signatures for "Default" API keys.
 *
 * The signature is the API key followed by every non-empty payload value, each
 * prefixed with a pipe, in ascending key order, hashed with SHA-1 and
 * lowercased:
 *
 *     strtolower(sha1($apiKey . '|' . $v1 . '|' . $v2 . ...))
 *
 * Only the values enter the digest — the keys are used solely for ordering, so
 * two payloads whose fields sort the same way collide by design. That is
 * Payport's scheme, reproduced verbatim from the vendor documentation.
 *
 * Payport also documents a second scheme for "Secret" API keys, which signs
 * `timestamp + method + url + values` with HMAC-SHA256 and delivers the result
 * in `Signature`/`Timestamp` headers. That key is issued only on request and,
 * per the vendor docs, works with API3 only — this merchant uses the default
 * key, so the header scheme is deliberately not implemented.
 */
final class PayportSignatureService
{
    /**
     * @param  list<string>  $alternateKeys  Keys that may have signed an incoming
     *                                       callback but are never used to sign our own requests. Payport
     *                                       signs a callback with "Api3_key or Api5_key, depending on which
     *                                       Api the invoice was created using", so an invoice opened through
     *                                       API3 — or before a key rotation — comes back signed with a key
     *                                       other than the one we charge with.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly array $alternateKeys = [],
    ) {}

    /**
     * The expected signature for a set of callback or request parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public function sign(array $params): string
    {
        return strtolower(sha1($this->apiKey.$this->flatten($params)));
    }

    /**
     * Verify the `signature` field of a Payport callback.
     *
     * The signature field is excluded from its own digest.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $received = (string) ($payload['signature'] ?? '');

        if ($received === '') {
            return false;
        }

        unset($payload['signature']);

        $body = $this->flatten($payload);

        foreach ([$this->apiKey, ...$this->alternateKeys] as $key) {
            if ($key !== '' && hash_equals(strtolower(sha1($key.$body)), $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Concatenate values as `|value`, sorted by key, skipping empty ones.
     *
     * Nested arrays recurse and contribute their own values in their own key
     * order — callbacks deliver grouped fields as `payment_info[upi_id]`, which
     * PHP's form decoder turns into a nested array.
     *
     * @param  array<string, mixed>  $params
     */
    private function flatten(array $params): string
    {
        $filtered = array_filter(
            $params,
            fn ($value): bool => $value !== '' && $value !== null,
        );

        ksort($filtered);

        $signature = '';

        foreach ($filtered as $value) {
            $signature .= is_array($value)
                ? $this->flatten($value)
                : '|'.trim((string) $value);
        }

        return $signature;
    }
}
