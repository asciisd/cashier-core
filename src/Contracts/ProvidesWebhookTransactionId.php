<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

/**
 * The webhook correlation seam. A provider whose webhook payload names the
 * transaction differently from its charge response MUST implement this, or
 * its webhooks will never find their transaction.
 */
interface ProvidesWebhookTransactionId
{
    /**
     * Extract the provider transaction id used to correlate a webhook payload
     * with a local transaction (matched against `provider_transaction_id`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookTransactionId(array $payload): ?string;
}
