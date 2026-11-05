<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\Models\Transaction;

/**
 * A provider whose callbacks can describe a second payment against a charge
 * that already exists.
 *
 * Some rails keep accepting money after the charge they were opened for. A
 * crypto deposit address is static per customer: a customer who sends to it
 * again without opening a new deposit raises a new payment at the PSP, and the
 * PSP reports it under the original charge's order id. Applied to the original
 * transaction, that payment is dropped as a duplicate or out-of-order update
 * and the customer is never credited.
 *
 * Implementing this contract lets the webhook pipeline give such a payment its
 * own transaction instead.
 */
interface IdentifiesRepeatPayments
{
    /**
     * The provider transaction id a repeat payment is tracked under, or null
     * when the callback belongs to the original charge.
     *
     * The id must be stable across every callback for the same payment and
     * must differ from `$original->provider_transaction_id`.
     *
     * @param  array<string, mixed>  $payload  the callback body
     * @param  Transaction  $original  the transaction the callback correlated to
     */
    public function repeatPaymentId(array $payload, Transaction $original): ?string;
}
