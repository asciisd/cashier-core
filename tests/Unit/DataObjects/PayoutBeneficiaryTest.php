<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\BeneficiaryBank;
use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;

function beneficiaryArray(array $overrides = []): array
{
    return array_replace_recursive([
        'firstName' => 'Kuldeep',
        'lastName' => 'Singh',
        'gender' => 'male',
        'nationality' => 'are',
        'dateOfBirth' => '1990-04-01',
        'phone' => 501234567,
        'address' => [
            'line1' => 'Villa 12',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'postalCode' => '00000',
            'country' => 'ARE',
        ],
        'bank' => [
            'accountNumber' => 'AE110260000959024298101',
            'accountHolderName' => 'Kuldeep Singh',
            'bankName' => 'Emirates NBD',
            'swiftCode' => 'EBILAEAD',
        ],
    ], $overrides);
}

it('reads a beneficiary, normalising countries and int phone numbers', function () {
    $beneficiary = PayoutBeneficiary::fromArray(beneficiaryArray());

    expect($beneficiary->firstName)->toBe('Kuldeep')
        ->and($beneficiary->nationality)->toBe('ARE')
        ->and($beneficiary->phone)->toBe('501234567')
        ->and($beneficiary->address->city)->toBe('Dubai')
        ->and($beneficiary->bank)->toBeInstanceOf(BeneficiaryBank::class)
        ->and($beneficiary->bank->swiftCode)->toBe('EBILAEAD')
        ->and($beneficiary->wallet)->toBeNull()
        ->and($beneficiary->middleName)->toBeNull();
});

it('round-trips through toArray without null keys', function () {
    $array = PayoutBeneficiary::fromArray(beneficiaryArray())->toArray();

    expect(PayoutBeneficiary::fromArray($array)->toArray())->toBe($array)
        ->and($array)->not->toHaveKey('middleName')
        ->and($array['bank'])->not->toHaveKey('bankCode')
        ->and($array)->not->toHaveKey('wallet');
});

it('treats blank strings as absent', function () {
    $beneficiary = PayoutBeneficiary::fromArray(beneficiaryArray(['middleName' => '  ', 'taxId' => '']));

    expect($beneficiary->middleName)->toBeNull()->and($beneficiary->taxId)->toBeNull();
});

it('refuses malformed input, naming the field', function (array $overrides, string $message) {
    expect(fn () => PayoutBeneficiary::fromArray(beneficiaryArray($overrides)))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing last name' => [['lastName' => null], 'lastName is required'],
    'non-string name' => [['firstName' => ['Kuldeep']], 'firstName must be a string'],
    'two-letter country' => [['nationality' => 'AE'], 'nationality must be a three-letter ISO country code'],
    'bad date' => [['dateOfBirth' => '01-04-1990'], 'dateOfBirth must be a Y-m-d date'],
    'impossible date' => [['dateOfBirth' => '1990-02-30'], 'dateOfBirth must be a Y-m-d date'],
    'address not an object' => [['address' => 'Dubai'], 'address must be an object'],
    'address city missing' => [['address' => ['city' => null]], 'address.city is required'],
    'bank account missing' => [['bank' => ['accountNumber' => null]], 'bank.accountNumber is required'],
    'wallet code missing' => [['wallet' => ['providerName' => 'GCASH']], 'wallet.providerCode is required'],
]);

it('refuses a beneficiary without an address', function () {
    $data = beneficiaryArray();
    unset($data['address']);

    expect(fn () => PayoutBeneficiary::fromArray($data))
        ->toThrow(InvalidArgumentException::class, 'address is required');
});

it('leaves existing PayoutRequest call sites without a beneficiary', function () {
    $request = new PayoutRequest('WD-1', '70001', '100.00', 'USD', 'usdttrc20', 'TXyz');

    expect($request->beneficiary)->toBeNull();
});
