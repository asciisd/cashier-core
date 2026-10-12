<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * The bank account a fiat payout is credited to. `bankCountry` is whatever
 * the target PSP calls the bank's location, which is not always a country
 * code, so it is not normalised.
 */
readonly class BeneficiaryBank
{
    use ReadsFields;

    public function __construct(
        public string $accountNumber,
        public string $accountHolderName,
        public string $bankName,
        public ?string $bankCode = null,
        public ?string $swiftCode = null,
        public ?string $accountType = null,
        public ?string $bankCountry = null,
        public ?string $branchName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountNumber: self::requiredString($data, 'accountNumber', 'bank.'),
            accountHolderName: self::requiredString($data, 'accountHolderName', 'bank.'),
            bankName: self::requiredString($data, 'bankName', 'bank.'),
            bankCode: self::optionalString($data, 'bankCode', 'bank.'),
            swiftCode: self::optionalString($data, 'swiftCode', 'bank.'),
            accountType: self::optionalString($data, 'accountType', 'bank.'),
            bankCountry: self::optionalString($data, 'bankCountry', 'bank.'),
            branchName: self::optionalString($data, 'branchName', 'bank.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'accountNumber' => $this->accountNumber,
            'accountHolderName' => $this->accountHolderName,
            'bankName' => $this->bankName,
            'bankCode' => $this->bankCode,
            'swiftCode' => $this->swiftCode,
            'accountType' => $this->accountType,
            'bankCountry' => $this->bankCountry,
            'branchName' => $this->branchName,
        ], fn ($value) => $value !== null);
    }
}
