<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * The mobile-wallet provider a wallet payout is credited through. The
 * beneficiary's own wallet number is PayoutBeneficiary::$phone.
 */
readonly class BeneficiaryWallet
{
    use ReadsFields;

    public function __construct(
        public string $providerName,
        public string $providerCode,
        public ?string $providerMobile = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerName: self::requiredString($data, 'providerName', 'wallet.'),
            providerCode: self::requiredString($data, 'providerCode', 'wallet.'),
            providerMobile: self::optionalString($data, 'providerMobile', 'wallet.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'providerName' => $this->providerName,
            'providerCode' => $this->providerCode,
            'providerMobile' => $this->providerMobile,
        ], fn ($value) => $value !== null);
    }
}
