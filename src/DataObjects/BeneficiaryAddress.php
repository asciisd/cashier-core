<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * A payout beneficiary's postal address. `country` is ISO 3166-1 alpha-3.
 */
readonly class BeneficiaryAddress
{
    use ReadsFields;

    public function __construct(
        public string $line1,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
        public ?string $line2 = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: self::requiredString($data, 'line1', 'address.'),
            city: self::requiredString($data, 'city', 'address.'),
            state: self::requiredString($data, 'state', 'address.'),
            postalCode: self::requiredString($data, 'postalCode', 'address.'),
            country: self::requiredCountry($data, 'country', 'address.'),
            line2: self::optionalString($data, 'line2', 'address.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
        ], fn ($value) => $value !== null);
    }
}
