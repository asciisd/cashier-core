<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

/**
 * What of a PSP payload may be persisted to `provider_payload`.
 *
 * PSP callbacks routinely carry cardholder emails, postal codes, masked PANs
 * and client IPs. Retaining all of that verbatim, forever, is the single
 * largest data-protection exposure a payment integration accumulates — so the
 * stored copy is redacted with the same rules as the logs. When a per-driver
 * allowlist is configured, only those keys survive at all.
 */
class PayloadSanitizer
{
    public function __construct(
        private readonly PayloadRedactor $redactor = new PayloadRedactor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function forStorage(string $driver, array $payload): array
    {
        $allowlist = config("cashier-core.security.payload_allowlist.{$driver}");

        if (is_array($allowlist) && $allowlist !== []) {
            $payload = array_intersect_key($payload, array_flip($allowlist));
        }

        return $this->redactor->redact($payload);
    }
}
