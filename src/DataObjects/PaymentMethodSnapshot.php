<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;

readonly class PaymentMethodSnapshot
{
    public function __construct(
        public PaymentMethodType $type,
        public PaymentMethodBrand $brand,
        public ?string $lastFour = null,
        public ?string $displayName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: PaymentMethodType::from($data['type']),
            brand: PaymentMethodBrand::from($data['brand']),
            lastFour: $data['last_four'] ?? null,
            displayName: $data['display_name'] ?? null,
        );
    }

    /**
     * `$lastFour` is nullable because the card rails carry instruments with
     * no card number at all — KNET, and the wallet methods behind it. Passing
     * `''` for those wrote an empty string into a nullable column, since
     * PaymentMethodSnapshotAttributes::known() filters on `!== null` and an
     * empty string survives it.
     */
    public static function fromCardData(string $brand, ?string $lastFour, ?string $displayName = null): self
    {
        $brandEnum = PaymentMethodBrand::tryFrom(strtolower($brand)) ?? PaymentMethodBrand::Other;

        return new self(
            type: $brandEnum->getType(),
            brand: $brandEnum,
            lastFour: $lastFour,
            displayName: $displayName ?? ($lastFour === null
                ? $brandEnum->label()
                : "{$brandEnum->label()} •••• {$lastFour}"),
        );
    }

    public static function fromDigitalWallet(string $brand, ?string $displayName = null): self
    {
        $brandEnum = PaymentMethodBrand::tryFrom(strtolower($brand)) ?? PaymentMethodBrand::Other;
        
        return new self(
            type: $brandEnum->getType(),
            brand: $brandEnum,
            lastFour: null,
            displayName: $displayName ?? $brandEnum->label(),
        );
    }

    public static function fromBankTransfer(string $method = 'wire_transfer', ?string $displayName = null): self
    {
        $brandEnum = PaymentMethodBrand::tryFrom($method) ?? PaymentMethodBrand::Other;
        
        return new self(
            type: $brandEnum->getType(),
            brand: $brandEnum,
            lastFour: null,
            displayName: $displayName ?? $brandEnum->label(),
        );
    }

    public static function fromCryptocurrency(string $currency, ?string $displayName = null): self
    {
        $brandEnum = PaymentMethodBrand::tryFrom(strtolower($currency)) ?? PaymentMethodBrand::Other;
        
        return new self(
            type: $brandEnum->getType(),
            brand: $brandEnum,
            lastFour: null,
            displayName: $displayName ?? $brandEnum->label(),
        );
    }

    public static function fromCash(?string $displayName = null): self
    {
        return new self(
            type: PaymentMethodType::Cash,
            brand: PaymentMethodBrand::Cash,
            lastFour: null,
            displayName: $displayName ?? 'Cash Payment',
        );
    }

    public function toArray(): array
    {
        return [
            'payment_method_type' => $this->type->value,
            'payment_method_brand' => $this->brand->value,
            'payment_method_last_four' => $this->lastFour,
            'payment_method_display_name' => $this->displayName,
        ];
    }

    public function getDisplayName(): string
    {
        if ($this->displayName) {
            return $this->displayName;
        }

        if ($this->lastFour && $this->brand->requiresLastFour()) {
            return "{$this->brand->label()} •••• {$this->lastFour}";
        }

        return $this->brand->label();
    }

    public function getIcon(): string
    {
        return $this->brand->getIcon();
    }
}
