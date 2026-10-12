<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-WPT.md: credit a mobile wallet. The beneficiary's phone is the
 * wallet number; the provider comes from the beneficiary's wallet details.
 */
final class WalletReceiver extends CustomerReceiver
{
    public function rail(): Rail
    {
        return Rail::Wallet;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        if ($beneficiary->wallet === null) {
            throw self::invalid('wallet', 'is required for wallet payouts');
        }

        if ($beneficiary->phone === null) {
            throw self::invalid('phone', 'is required for wallet payouts');
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        return [
            'receiverServiceProviderName' => $beneficiary->wallet?->providerName,
            'receiverServiceProviderCode' => $beneficiary->wallet?->providerCode,
            'receiverServiceProviderMobile' => $beneficiary->wallet?->providerMobile,
        ];
    }
}
