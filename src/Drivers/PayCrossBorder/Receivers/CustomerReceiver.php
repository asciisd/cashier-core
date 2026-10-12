<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Str;

/**
 * The `receiver.customer` block every rail shares (B2C: the beneficiary is a
 * person). Field names are PCB's, including `receiverAddresssCity` with three
 * s's.
 *
 * Only free-text address fields are cleaned — transliterated to ASCII,
 * stripped to letters, digits and spaces ("special characters not allowed")
 * and cut to PCB's documented length. Names, phone numbers and ID numbers
 * are never altered: a value PCB would refuse is refused here, naming the
 * field, so the host fixes it rather than paying a mangled name.
 */
abstract class CustomerReceiver implements ReceiverBuilder
{
    private const GENDERS = ['male', 'female', 'others'];

    /** Letters in any script, plus the punctuation real names carry. */
    private const NAME_PATTERN = "/^\p{L}[\p{L} .'-]*$/u";

    public function assertComplete(PayoutRequest $request): void
    {
        $beneficiary = $this->beneficiary($request);

        $this->assertName($beneficiary->firstName, 'firstName');
        $this->assertName($beneficiary->lastName, 'lastName');

        if ($beneficiary->middleName !== null) {
            $this->assertName($beneficiary->middleName, 'middleName');
        }

        if (! in_array(strtolower($beneficiary->gender), self::GENDERS, true)) {
            throw self::invalid('gender', 'must be male, female or others');
        }

        if ($beneficiary->phone !== null && ! preg_match('/^\d{7,20}$/', $beneficiary->phone)) {
            throw self::invalid('phone', 'must be 7-20 digits, without the dial code');
        }

        if ($beneficiary->dialCode !== null && ! preg_match('/^\+\d{1,3}$/', $beneficiary->dialCode)) {
            throw self::invalid('dialCode', 'must look like +971');
        }

        if ($beneficiary->idNumber !== null && ! preg_match('/^[A-Za-z0-9]{1,50}$/', $beneficiary->idNumber)) {
            throw self::invalid('idNumber', 'must be 1-50 letters or digits');
        }

        $address = $beneficiary->address;

        foreach (['line1' => $address->line1, 'city' => $address->city, 'state' => $address->state, 'postalCode' => $address->postalCode] as $field => $value) {
            if (self::clean($value, 50) === '') {
                throw self::invalid("address.{$field}", 'has no letters or digits PayCrossBorder accepts');
            }
        }

        $this->assertRail($request, $beneficiary);
    }

    public function build(PayoutRequest $request): array
    {
        $this->assertComplete($request);

        $beneficiary = $this->beneficiary($request);
        $address = $beneficiary->address;
        $line2 = $address->line2 === null ? '' : self::clean($address->line2, 35);

        return ['customer' => self::compact(array_merge([
            'receiverFirstName' => $beneficiary->firstName,
            'receiverMiddleName' => $beneficiary->middleName,
            'receiverLastName' => $beneficiary->lastName,
            'receiverGender' => strtolower($beneficiary->gender),
            'receiverNationality' => $beneficiary->nationality,
            'receiverDOB' => $beneficiary->dateOfBirth,
            'receiverIdType' => $beneficiary->idType,
            'receiverIdNumber' => $beneficiary->idNumber,
            'receiverIssueDate' => $beneficiary->idIssueDate,
            'receiverIdExpiration' => $beneficiary->idExpiry,
            'receiverIdCountry' => $beneficiary->idCountry,
            'receiverOccupation' => $beneficiary->occupation,
            'dialCode' => $beneficiary->dialCode,
            'receiverMsisdn' => $beneficiary->phone,
            'receiverAddressLineOne' => self::clean($address->line1, 35),
            'receiverAddressLineTwo' => $line2 === '' ? null : $line2,
            'receiverCountry' => $address->country,
            'receiverAddressState' => self::clean($address->state, 50),
            'receiverAddresssCity' => self::clean($address->city, 50),
            'receiverPinCode' => mb_substr(str_replace(' ', '', self::clean($address->postalCode, 50)), 0, 10),
        ], $this->railFields($request, $beneficiary)))];
    }

    /**
     * @throws PaymentProcessingException
     */
    abstract protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void;

    /**
     * @return array<string, string|null>
     */
    abstract protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array;

    protected function beneficiary(PayoutRequest $request): PayoutBeneficiary
    {
        return $request->beneficiary ?? throw new PaymentProcessingException(
            'PayCrossBorder payouts need a beneficiary in the withdrawal details.',
        );
    }

    protected static function invalid(string $field, string $reason): PaymentProcessingException
    {
        return new PaymentProcessingException("PayCrossBorder beneficiary {$field} {$reason}.");
    }

    private function assertName(string $name, string $field): void
    {
        if (mb_strlen($name) > 75 || ! preg_match(self::NAME_PATTERN, $name)) {
            throw self::invalid($field, 'must be letters only (spaces, dots, apostrophes and hyphens allowed), at most 75');
        }
    }

    /**
     * ASCII letters, digits and single spaces, at most $max characters.
     */
    private static function clean(string $value, int $max): string
    {
        $value = preg_replace('/[^A-Za-z0-9 ]+/', ' ', Str::ascii($value)) ?? '';
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return rtrim(mb_substr($value, 0, $max));
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, string>
     */
    private static function compact(array $fields): array
    {
        return array_filter($fields, fn ($value) => $value !== null);
    }
}
