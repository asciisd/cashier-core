<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Traits;

use Asciisd\CashierCore\Models\PaymentMethod;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Payable
{
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    public function paymentMethods(): MorphMany
    {
        return $this->morphMany(PaymentMethod::class, 'user');
    }

    public function getDefaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    public function hasPaymentMethods(): bool
    {
        return $this->paymentMethods()->exists();
    }

    public function hasDefaultPaymentMethod(): bool
    {
        return $this->getDefaultPaymentMethod() !== null;
    }

    public function getSuccessfulTransactions()
    {
        return $this->transactions()->successful();
    }

    public function getFailedTransactions()
    {
        return $this->transactions()->failed();
    }

    public function getPendingTransactions()
    {
        return $this->transactions()->pending();
    }

    public function getTotalSpent(): int
    {
        return $this->getSuccessfulTransactions()->sum('amount');
    }

    public function getFormattedTotalSpent(): string
    {
        return number_format($this->getTotalSpent() / 100, 2);
    }

    public function getTransactionCount(): int
    {
        return $this->transactions()->count();
    }

    public function getSuccessfulTransactionCount(): int
    {
        return $this->getSuccessfulTransactions()->count();
    }
}
