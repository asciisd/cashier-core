<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Asciisd\CashierCore\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cashier_transactions';

    protected $fillable = [
        'processor_name',
        'processor_transaction_id',
        'payable_type',
        'payable_id',
        'payment_method_id',
        'amount',
        'currency',
        'status',
        'description',
        'metadata',
        'processor_response',
        'error_code',
        'error_message',
        'processed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'metadata' => 'array',
            'processor_response' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function payable(): BelongsTo
    {
        return $this->morphTo();
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::Processing;
    }

    public function requiresAction(): bool
    {
        return $this->status->requiresAction();
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2);
    }

    public function getTotalRefundedAttribute(): int
    {
        return $this->refunds()->where('status', 'succeeded')->sum('amount');
    }

    public function getRemainingRefundableAmountAttribute(): int
    {
        return $this->amount - $this->total_refunded;
    }

    public function canBeRefunded(): bool
    {
        return $this->isSuccessful() && $this->remaining_refundable_amount > 0;
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::Succeeded);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::Failed);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    public function scopeByProcessor($query, string $processor)
    {
        return $query->where('processor_name', $processor);
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
