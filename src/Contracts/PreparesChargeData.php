<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

/**
 * A provider that needs to shape the charge payload with connection- or
 * customer-specific data before `charge()` runs.
 *
 * This hook replaces provider-specific branches in the orchestration layer:
 * a card aggregator injects its routing hints, a gateway that needs the full
 * customer model attaches it — each driver owns its own quirks.
 */
interface PreparesChargeData
{
    /**
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array;
}
