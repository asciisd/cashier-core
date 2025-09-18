<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

enum PaymentMethodType: string
{
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case BankTransfer = 'bank_transfer';
    case DigitalWallet = 'digital_wallet';
    case Cryptocurrency = 'cryptocurrency';
    case Cash = 'cash';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CreditCard => 'Credit Card',
            self::DebitCard => 'Debit Card',
            self::BankTransfer => 'Bank Transfer',
            self::DigitalWallet => 'Digital Wallet',
            self::Cryptocurrency => 'Cryptocurrency',
            self::Cash => 'Cash',
            self::Check => 'Check',
            self::Other => 'Other',
        };
    }
}
