<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-CRYPTO.md: stablecoin to a wallet. The address is the request's
 * payout_address, the network (ERC20, TRC20 …) its payout_extra_id.
 * receiverDOB is mandatory on this rail only.
 */
final class CryptoReceiver extends CustomerReceiver
{
    public function rail(): Rail
    {
        return Rail::Crypto;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        if ($beneficiary->dateOfBirth === null) {
            throw self::invalid('dateOfBirth', 'is required for crypto payouts');
        }

        if (! preg_match('/^[A-Za-z0-9]{26,50}$/', trim($request->payoutAddress))) {
            throw self::invalid('payout_address', 'must be a 26-50 character wallet address');
        }

        if (! preg_match('/^[A-Za-z0-9]{3,10}$/', trim((string) $request->payoutExtraId))) {
            throw self::invalid('payout_extra_id', 'must name the crypto network, e.g. TRC20');
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        return [
            'walletAddress' => trim($request->payoutAddress),
            'cryptoNetwork' => strtoupper(trim((string) $request->payoutExtraId)),
        ];
    }
}
