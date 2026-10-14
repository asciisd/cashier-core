<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A webhook passed signature verification and was queued for processing.
 * The payload here is already redacted — safe to log or forward.
 */
class WebhookReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $redactedPayload
     */
    public function __construct(
        public readonly string $driver,
        public readonly ?string $connection,
        public readonly array $redactedPayload,
    ) {}
}
