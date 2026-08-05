<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of admin money actions (PCI DSS 10.2): who did what to which
 * transaction, from which status to which, from where. Written by
 * WithdrawalWorkflow and any host code that moves money on an admin's behalf.
 * Append-only — nothing in the package updates or deletes rows.
 */
class AdminAction extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_guard',
        'action',
        'transaction_id',
        'from_status',
        'to_status',
        'context',
        'ip',
    ];

    public const UPDATED_AT = null;

    public function getTable(): string
    {
        return $this->table ?? config('cashier-core.database.tables.admin_actions', 'cashier_admin_actions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
