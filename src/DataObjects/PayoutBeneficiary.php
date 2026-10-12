<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;
use InvalidArgumentException;

/**
 * Who receives a payout, for PSPs that need the beneficiary's identity
 * (PayCrossBorder). Rail-neutral: it carries no PSP rules, and code-valued
 * fields (`idType`, `occupation`) hold whatever code the target PSP expects.
 * Which fields are required is the driver's call, not this object's.
 *
 * Hosts store it as `withdrawal_details['beneficiary']`; the workflow reads it
 * with fromArray(). Countries are ISO 3166-1 alpha-3; dates are Y-m-d.
 */
readonly class PayoutBeneficiary
{
    use ReadsFields;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $nationality,
        public BeneficiaryAddress $address,
        public ?string $middleName = null,
        public ?string $dateOfBirth = null,
        public ?string $phone = null,
        public ?string $dialCode = null,
        public ?string $email = null,
        public ?string $occupation = null,
        public ?string $taxId = null,
        public ?string $idType = null,
        public ?string $idNumber = null,
        public ?string $idCountry = null,
        public ?string $idIssueDate = null,
        public ?string $idExpiry = null,
        public ?BeneficiaryBank $bank = null,
        public ?BeneficiaryWallet $wallet = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException naming the first bad field
     */
    public static function fromArray(array $data): self
    {
        $address = self::optionalArray($data, 'address')
            ?? throw new InvalidArgumentException('address is required.');
        $bank = self::optionalArray($data, 'bank');
        $wallet = self::optionalArray($data, 'wallet');

        return new self(
            firstName: self::requiredString($data, 'firstName'),
            lastName: self::requiredString($data, 'lastName'),
            gender: self::requiredString($data, 'gender'),
            nationality: self::requiredCountry($data, 'nationality'),
            address: BeneficiaryAddress::fromArray($address),
            middleName: self::optionalString($data, 'middleName'),
            dateOfBirth: self::optionalDate($data, 'dateOfBirth'),
            phone: self::optionalString($data, 'phone'),
            dialCode: self::optionalString($data, 'dialCode'),
            email: self::optionalString($data, 'email'),
            occupation: self::optionalString($data, 'occupation'),
            taxId: self::optionalString($data, 'taxId'),
            idType: self::optionalString($data, 'idType'),
            idNumber: self::optionalString($data, 'idNumber'),
            idCountry: self::optionalCountry($data, 'idCountry'),
            idIssueDate: self::optionalDate($data, 'idIssueDate'),
            idExpiry: self::optionalDate($data, 'idExpiry'),
            bank: $bank === null ? null : BeneficiaryBank::fromArray($bank),
            wallet: $wallet === null ? null : BeneficiaryWallet::fromArray($wallet),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'dateOfBirth' => $this->dateOfBirth,
            'phone' => $this->phone,
            'dialCode' => $this->dialCode,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'taxId' => $this->taxId,
            'idType' => $this->idType,
            'idNumber' => $this->idNumber,
            'idCountry' => $this->idCountry,
            'idIssueDate' => $this->idIssueDate,
            'idExpiry' => $this->idExpiry,
            'address' => $this->address->toArray(),
            'bank' => $this->bank?->toArray(),
            'wallet' => $this->wallet?->toArray(),
        ], fn ($value) => $value !== null);
    }
}
