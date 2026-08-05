<?php

namespace Asciisd\CashierCore\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
    case Bonus = 'bonus';
    case TransferFrom = 'transfer_from';
    case TransferTo = 'transfer_to';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Refund => 'Refund',
            self::Bonus => 'Bonus',
            self::TransferFrom => 'Transfer From',
            self::TransferTo => 'Transfer To',
        };
    }

    /**
     * MT5 comment prefix for each transaction type.
     *
     * DEP = Deposit, FTD = First Time Deposit (handled in Transaction model),
     * WDL = Withdrawal, REF = Refund, BNS = Bonus.
     * Transfers use full words for readability in MT5.
     */
    public function mt5Prefix(): string
    {
        return match ($this) {
            self::Deposit => 'DEP',
            self::Withdrawal => 'WDL',
            self::Refund => 'REF',
            self::Bonus => 'BNS',
            self::TransferFrom => 'transfer from',
            self::TransferTo => 'transfer to',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::Bonus, self::TransferTo => true,
            self::Withdrawal, self::Refund, self::TransferFrom => false,
        };
    }

    public function isDebit(): bool
    {
        return ! $this->isCredit();
    }

    public function isTransfer(): bool
    {
        return $this === self::TransferFrom || $this === self::TransferTo;
    }
}
