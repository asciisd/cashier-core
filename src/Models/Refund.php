<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund issued against an engine transaction.
 *
 * The column names and types here match `create_refunds_table` — they did not
 * before. The model was carried over from 1.x and still described that
 * schema: `processor_refund_id`/`processor_response` against a migration
 * writing `provider_refund_id`/`provider_payload`, a uuid key against a
 * bigint one, and an integer amount cast against a decimal column. Nothing
 * ever wrote through it, so the mismatch stayed invisible.
 */
class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'provider_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'metadata',
        'provider_payload',
        'processed_at',
        'failed_at',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('cashier-core.database.tables.refunds', 'refunds');
    }

    protected function casts(): array
    {
        return [
            // Major units, matching transactions.amount — a refund of 95.50
            // must not become 95.
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'metadata' => 'array',
            'provider_payload' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * The host may substitute its own transaction model, so resolve it from
     * config rather than pinning the package's.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Cashier::transactionModel(), 'transaction_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === RefundStatus::Succeeded;
    }

    public function isFailed(): bool
    {
        return $this->status === RefundStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === RefundStatus::Processing;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', RefundStatus::Succeeded);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', RefundStatus::Failed);
    }

    public function scopePending($query)
    {
        return $query->where('status', RefundStatus::Pending);
    }

    public function scopeByAmount($query, int $amount)
    {
        return $query->where('amount', $amount);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }
}
