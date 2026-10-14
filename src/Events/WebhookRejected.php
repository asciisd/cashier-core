<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A webhook was refused before processing — bad signature, replay, or
 * verification disabled in an environment where that is not allowed.
 */
class WebhookRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $driver,
        public readonly string $reason,
        public readonly ?string $ip = null,
    ) {}
}
