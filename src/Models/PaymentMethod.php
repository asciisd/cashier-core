<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Asciisd\CashierCore\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cashier_payment_methods';

    protected $fillable = [
        'user_type',
        'user_id',
        'processor_name',
        'processor_payment_method_id',
        'type',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->type === PaymentMethodType::CreditCard || $this->type === PaymentMethodType::DebitCard) {
            return "{$this->brand} ending in {$this->last_four}";
        }

        return $this->type->label();
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->exp_month || !$this->exp_year) {
            return false;
        }

        $now = now();
        $expirationDate = now()->setYear($this->exp_year)->setMonth($this->exp_month)->endOfMonth();

        return $now->greaterThan($expirationDate);
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->exp_month || !$this->exp_year) {
            return false;
        }

        $now = now();
        $expirationDate = now()->setYear($this->exp_year)->setMonth($this->exp_month)->endOfMonth();
        $threeMonthsFromNow = $now->copy()->addMonths(3);

        return $expirationDate->lessThanOrEqualTo($threeMonthsFromNow) && !$this->is_expired;
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, PaymentMethodType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProcessor($query, string $processor)
    {
        return $query->where('processor_name', $processor);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('exp_year')
              ->orWhere(function ($subQuery) {
                  $currentYear = now()->year;
                  $currentMonth = now()->month;
                  
                  $subQuery->where('exp_year', '>', $currentYear)
                           ->orWhere(function ($yearQuery) use ($currentYear, $currentMonth) {
                               $yearQuery->where('exp_year', $currentYear)
                                        ->where('exp_month', '>=', $currentMonth);
                           });
              });
        });
    }

    public function makeDefault(): void
    {
        // Remove default from other payment methods for this user
        static::where('user_type', $this->user_type)
              ->where('user_id', $this->user_id)
              ->where('id', '!=', $this->id)
              ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }
}
