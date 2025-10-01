<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Asciisd\CashierCore\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cashier_refunds';

    protected $fillable = [
        'transaction_id',
        'processor_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'metadata',
        'processor_response',
        'processed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'metadata' => 'array',
            'processor_response' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
        return number_format($this->amount, 2);
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
