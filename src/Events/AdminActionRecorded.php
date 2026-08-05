<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\AdminAction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An audit row was written for an admin money action.
 */
class AdminActionRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AdminAction $action,
    ) {}
}
