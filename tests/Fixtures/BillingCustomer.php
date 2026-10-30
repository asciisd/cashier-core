<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\ProvidesBillingDetails;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted customer that offers billing details, for tests that resolve the
 * customer through `cashier-core.models.customer`.
 */
class BillingCustomer extends Model implements CustomerContract, ProvidesBillingDetails
{
    protected $table = 'billing_customers';

    protected $guarded = [];

    public $timestamps = false;

    public function cashierId(): int|string
    {
        return $this->getKey();
    }

    public function cashierEmail(): string
    {
        return (string) $this->email;
    }

    public function cashierName(): string
    {
        return (string) $this->name;
    }

    public function cashierLocale(): string
    {
        return 'en';
    }

    public function cashierBillingDetails(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country_code' => $this->phone_country_code,
            'country' => $this->country,
        ];
    }
}
