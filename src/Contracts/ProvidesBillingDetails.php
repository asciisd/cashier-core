<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

/**
 * Optional enrichment of the customer contract for card-gateway billing.
 *
 * `CustomerContract` carries id, email, name and locale. Card and wallet rails
 * routinely want more — country, phone, and a billing address — and some
 * refuse the charge without them: an APS Apple Pay deposit is rejected with
 * `transaction_info_needed` unless email, country, street, town and post code
 * are all present.
 *
 * A host customer model that implements this feeds those fields into the
 * charge; one that does not still charges with whatever the driver can derive.
 * Drivers MUST NOT substitute invented values for missing ones — a fabricated
 * billing country buys a downstream decline in place of an upfront error.
 */
interface ProvidesBillingDetails
{
    /**
     * Billing details keyed by canonical name. Any subset of:
     * `first_name`, `last_name`, `email`, `phone`, `country` (ISO-2),
     * `currency`, `date_of_birth` (Y-m-d), `gender` (Male|Female),
     * `street`, `city`, `region`, `zip_code`.
     *
     * Null and empty values are ignored by consumers.
     *
     * @return array<string, mixed>
     */
    public function cashierBillingDetails(): array;
}
