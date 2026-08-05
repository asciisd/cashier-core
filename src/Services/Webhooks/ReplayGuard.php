<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services\Webhooks;

use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Replay protection for webhook deliveries.
 *
 * A signature proves who sent a payload, not when — a captured valid callback
 * verifies forever. Claiming a digest of (driver, signature, body) under the
 * table's unique index makes the second delivery detectable: the controller
 * acknowledges it with the provider's expected response and processes nothing.
 *
 * Duplicates are acknowledged rather than erroring so a PSP retrying after
 * OUR failure to respond still converges: the first delivery already claimed
 * and dispatched, the retry just gets its ACK.
 */
class ReplayGuard
{
    /**
     * Claim this delivery. True when it is first-seen and must be processed;
     * false when the digest was already claimed (replay or PSP retry).
     */
    public function claim(string $driver, string $rawBody, string $signature, ?string $connection = null): bool
    {
        try {
            WebhookEvent::query()->create([
                'driver' => $driver,
                'connection' => $connection,
                'digest' => hash('sha256', "{$driver}|{$signature}|{$rawBody}"),
                'received_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
