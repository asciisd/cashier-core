<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Replay-guard ledger: one row per accepted webhook delivery, keyed by a
 * digest of (driver, signature, body). A second delivery with the same digest
 * is acknowledged without processing. Rows expire via `cashier:purge`.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'driver',
        'connection',
        'digest',
        'received_at',
    ];

    public $timestamps = false;

    public function getTable(): string
    {
        return $this->table ?? config('cashier-core.database.tables.webhook_events', 'cashier_webhook_events');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }
}
