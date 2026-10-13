<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-FIAT.md: credit a bank account. The destination country is the
 * beneficiary's address country. The money goes to bank.accountNumber, but
 * the admin approves payout_address, so the two must be equal.
 *
 * Only the rules the payout page states inline are encoded below. The
 * per-country "Local RAIL" tables (COUNTRYVALIDATIONS.md) are not: PCB
 * refuses such a payout itself, which arrives as a clean rejection.
 */
final class BankReceiver extends CustomerReceiver
{
    /** PAYOUT-FIAT.md, receiver `taxIdNumber`. */
    public const TAX_ID_COUNTRIES = ['ARG', 'THA', 'BRA', 'ECU', 'GTM', 'HND', 'KAZ', 'MEX', 'PER', 'CHL', 'PHL', 'JPN', 'URY', 'COL', 'CRI', 'RUS', 'BLR', 'PAK'];

    /** PAYOUT-FIAT.md, receiver `receiverAccountType`. */
    public const ACCOUNT_TYPE_COUNTRIES = ['BRA', 'PHL', 'DOM', 'URY'];

    /** PAYOUT-FIAT.md, receiver `receiverMsisdn` on the SWIFT rail. */
    public const SWIFT_PHONE_COUNTRIES = ['BRA', 'PHL', 'CHN', 'UGA', 'URY', 'COL', 'MEX', 'HKG', 'MYS'];

    public function rail(): Rail
    {
        return Rail::Bank;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        $bank = $beneficiary->bank ?? throw self::invalid('bank', 'is required for bank payouts');
        $country = $beneficiary->address->country;

        if (trim((string) $request->payoutAddress) !== $bank->accountNumber) {
            throw self::invalid('payout_address', 'must equal bank.accountNumber, the account being paid');
        }

        if (! preg_match('/^[A-Za-z0-9]{1,50}$/', $bank->accountNumber)) {
            throw self::invalid('bank.accountNumber', 'must be 1-50 letters or digits, without spaces');
        }

        if (mb_strlen($bank->accountHolderName) > 150) {
            throw self::invalid('bank.accountHolderName', 'must be at most 150 characters');
        }

        if (mb_strlen($bank->bankName) > 100) {
            throw self::invalid('bank.bankName', 'must be at most 100 characters');
        }

        if ($bank->swiftCode !== null && ! preg_match('/^[A-Za-z0-9]{8}([A-Za-z0-9]{3})?$/', $bank->swiftCode)) {
            throw self::invalid('bank.swiftCode', 'must be 8 or 11 letters or digits');
        }

        if (in_array($country, self::TAX_ID_COUNTRIES, true) && $beneficiary->taxId === null) {
            throw self::invalid('taxId', "is required for payouts to {$country}");
        }

        if (in_array($country, self::ACCOUNT_TYPE_COUNTRIES, true) && $bank->accountType === null) {
            throw self::invalid('bank.accountType', "is required for payouts to {$country}");
        }

        if ($bank->swiftCode !== null && in_array($country, self::SWIFT_PHONE_COUNTRIES, true) && $beneficiary->phone === null) {
            throw self::invalid('phone', "is required for SWIFT payouts to {$country}");
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        $bank = $beneficiary->bank;

        return [
            'receiverAccountNumber' => $bank?->accountNumber,
            'receiverAccountHolderName' => $bank?->accountHolderName,
            'receiverBankName' => $bank?->bankName,
            'receiverBankCode' => $bank?->bankCode,
            'receiverSwiftCode' => $bank?->swiftCode === null ? null : strtoupper($bank->swiftCode),
            'receiverAccountType' => $bank?->accountType,
            'receiverBankCountry' => $bank?->bankCountry,
            'receiverBankBranchName' => $bank?->branchName,
            'taxIdNumber' => $beneficiary->taxId,
        ];
    }
}
