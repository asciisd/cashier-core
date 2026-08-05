<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

/**
 * The host application's paying customer, as the package needs to see it.
 *
 * Implement on your user model and point `cashier-core.models.customer` at
 * it. The package never assumes an Eloquent user or a `users` table — these
 * four answers are the whole dependency.
 */
interface CustomerContract
{
    public function cashierId(): int|string;

    public function cashierEmail(): string;

    public function cashierName(): string;

    /**
     * Locale for customer-facing communication ('en', 'ar', ...).
     */
    public function cashierLocale(): string;
}
