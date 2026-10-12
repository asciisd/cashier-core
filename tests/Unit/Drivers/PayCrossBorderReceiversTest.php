<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\BankReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\CryptoReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\WalletReceiver;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;

it('picks the rail from the beneficiary', function () {
    expect(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::beneficiary())))->toBe(Rail::Crypto)
        ->and(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::bankBeneficiary())))->toBe(Rail::Bank)
        ->and(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::walletBeneficiary())))->toBe(Rail::Wallet)
        ->and(Rail::Bank->paymentMode())->toBe('Bank')
        ->and(Rail::Crypto->paymentMode())->toBe('Cash')
        ->and(Rail::Wallet->paymentMode())->toBe('Cash')
        ->and(Rail::Bank->builder())->toBeInstanceOf(BankReceiver::class);
});

it('refuses a beneficiary with both bank and wallet details', function () {
    $both = PayCrossBorderApi::bankBeneficiary(['wallet' => ['providerName' => 'GCASH', 'providerCode' => '63001']]);

    expect(fn () => Rail::for(PayoutBeneficiary::fromArray($both)))
        ->toThrow(PaymentProcessingException::class, 'not both');
});

it('builds the crypto receiver', function () {
    expect((new CryptoReceiver)->build(PayCrossBorderApi::request()))->toEqual(['customer' => [
        'receiverFirstName' => 'Kuldeep',
        'receiverLastName' => 'Singh',
        'receiverGender' => 'male',
        'receiverNationality' => 'ARE',
        'receiverDOB' => '1990-04-01',
        'receiverAddressLineOne' => 'Villa 12 Al Wasl Rd',
        'receiverCountry' => 'ARE',
        'receiverAddressState' => 'Dubai',
        'receiverAddresssCity' => 'Dubai',
        'receiverPinCode' => '00000',
        'walletAddress' => 'TXyzCustomerWalletAddress000001',
        'cryptoNetwork' => 'TRC20',
    ]]);
});

it('builds the bank receiver', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::bankBeneficiary(['taxId' => 'TX123']));

    $customer = (new BankReceiver)->build($request)['customer'];

    expect($customer)->toMatchArray([
        'receiverFirstName' => 'Kuldeep',
        'receiverAccountNumber' => 'AE110260000959024298101',
        'receiverAccountHolderName' => 'Kuldeep Singh',
        'receiverBankName' => 'Emirates NBD',
        'receiverSwiftCode' => 'EBILAEAD',
        'taxIdNumber' => 'TX123',
    ])->and($customer)->not->toHaveKeys(['walletAddress', 'cryptoNetwork', 'receiverBankCode']);
});

it('builds the wallet receiver', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::walletBeneficiary());

    expect((new WalletReceiver)->build($request)['customer'])->toMatchArray([
        'receiverMsisdn' => '9171234567',
        'dialCode' => '+63',
        'receiverCountry' => 'PHL',
        'receiverServiceProviderName' => 'GCASH-63001',
        'receiverServiceProviderCode' => '63001',
    ]);
});

it('transliterates, strips and truncates address fields only', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::beneficiary(['address' => [
        'line1' => 'Straße 5, Düsseldorf-Nord #12 and a very long tail here',
        'city' => 'Düsseldorf',
        'postalCode' => 'SW1A 1AA',
    ]]));

    $customer = (new CryptoReceiver)->build($request)['customer'];

    expect($customer['receiverAddressLineOne'])->toBe('Strasse 5 Dusseldorf Nord 12 and a')
        ->and($customer['receiverAddresssCity'])->toBe('Dusseldorf')
        ->and($customer['receiverPinCode'])->toBe('SW1A1AA');
});

it('refuses rather than alters protected or incomplete fields', function (array $beneficiary, array $request, string $message) {
    $payout = PayCrossBorderApi::request($request, PayCrossBorderApi::beneficiary($beneficiary));

    expect(fn () => (new CryptoReceiver)->assertComplete($payout))
        ->toThrow(PaymentProcessingException::class, $message);
})->with([
    'digit in a name' => [['firstName' => 'Kuldeep2'], [], 'firstName'],
    'unknown gender' => [['gender' => 'm'], [], 'gender must be male, female or others'],
    'phone with symbols' => [['phone' => '+971-50'], [], 'phone must be 7-20 digits'],
    'bad dial code' => [['dialCode' => '971'], [], 'dialCode'],
    'id number with symbols' => [['idNumber' => 'A-123'], [], 'idNumber'],
    'city with nothing usable' => [['address' => ['city' => '###']], [], 'address.city'],
    'crypto without birth date' => [['dateOfBirth' => null], [], 'dateOfBirth is required for crypto payouts'],
    'crypto without network' => [[], ['payoutExtraId' => null], 'payout_extra_id'],
    'crypto short address' => [[], ['payoutAddress' => 'TXyz'], 'payout_address'],
]);

it('refuses a payout with no beneficiary', function () {
    expect(fn () => (new CryptoReceiver)->assertComplete(PayCrossBorderApi::request(['beneficiary' => null])))
        ->toThrow(PaymentProcessingException::class, 'need a beneficiary');
});

it('applies the documented per-country bank rules', function (array $overrides, string $message) {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::bankBeneficiary($overrides));

    expect(fn () => (new BankReceiver)->assertComplete($request))
        ->toThrow(PaymentProcessingException::class, $message);
})->with([
    'tax id for BRA' => [['address' => ['country' => 'BRA'], 'bank' => ['accountType' => 'saving'], 'phone' => '11987654321'], 'taxId is required for payouts to BRA'],
    'account type for DOM' => [['address' => ['country' => 'DOM']], 'bank.accountType is required for payouts to DOM'],
    'phone for SWIFT to MEX' => [['address' => ['country' => 'MEX'], 'taxId' => 'TX1'], 'phone is required for SWIFT payouts to MEX'],
    'account number with symbols' => [['bank' => ['accountNumber' => 'AE11-0260']], 'bank.accountNumber'],
    'bad swift' => [['bank' => ['swiftCode' => 'EBIL']], 'bank.swiftCode'],
]);

it('requires bank details on the bank rail and a phone on the wallet rail', function () {
    expect(fn () => (new BankReceiver)->assertComplete(PayCrossBorderApi::request()))
        ->toThrow(PaymentProcessingException::class, 'bank is required');

    $noPhone = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::walletBeneficiary(['phone' => null]));

    expect(fn () => (new WalletReceiver)->assertComplete($noPhone))
        ->toThrow(PaymentProcessingException::class, 'phone is required for wallet payouts');
});

it('refuses a payout_address that is not the bank account or wallet phone being paid', function () {
    $bank = PayCrossBorderApi::request(['payoutAddress' => 'AE000000000000000000000'], PayCrossBorderApi::bankBeneficiary());

    expect(fn () => (new BankReceiver)->assertComplete($bank))
        ->toThrow(PaymentProcessingException::class, 'payout_address');

    $wallet = PayCrossBorderApi::request(['payoutAddress' => '9179999999'], PayCrossBorderApi::walletBeneficiary());

    expect(fn () => (new WalletReceiver)->assertComplete($wallet))
        ->toThrow(PaymentProcessingException::class, 'payout_address');
});

it('accepts the wallet phone with or without the dial code', function (string $address) {
    $request = PayCrossBorderApi::request(['payoutAddress' => $address], PayCrossBorderApi::walletBeneficiary());

    expect(fn () => (new WalletReceiver)->assertComplete($request))->not->toThrow(PaymentProcessingException::class);
})->with(['bare phone' => ['9171234567'], 'plus dial code' => ['+639171234567'], 'dial code' => ['639171234567']]);
