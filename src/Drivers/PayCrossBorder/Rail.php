<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\BankReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\CryptoReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\ReceiverBuilder;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\WalletReceiver;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * The PayCrossBorder payout rail, chosen from what the beneficiary carries:
 * bank details → Bank, a wallet provider → Wallet (PCB's "WPT"), neither →
 * Crypto (wallet address and network from the request).
 */
enum Rail: string
{
    case Crypto = 'crypto';
    case Bank = 'bank';
    case Wallet = 'wallet';

    /**
     * @throws PaymentProcessingException when the beneficiary has both bank and wallet details
     */
    public static function for(PayoutBeneficiary $beneficiary): self
    {
        if ($beneficiary->bank !== null && $beneficiary->wallet !== null) {
            throw new PaymentProcessingException(
                'A PayCrossBorder beneficiary has either bank or wallet details, not both.',
            );
        }

        return match (true) {
            $beneficiary->bank !== null => self::Bank,
            $beneficiary->wallet !== null => self::Wallet,
            default => self::Crypto,
        };
    }

    /**
     * PCB's `paymentMode`. Its crypto and WPT examples both use Cash.
     */
    public function paymentMode(): string
    {
        return $this === self::Bank ? 'Bank' : 'Cash';
    }

    public function builder(): ReceiverBuilder
    {
        return match ($this) {
            self::Crypto => new CryptoReceiver,
            self::Bank => new BankReceiver,
            self::Wallet => new WalletReceiver,
        };
    }
}
