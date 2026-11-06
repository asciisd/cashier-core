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

    /**
     * Commission paid to an introducing broker. It is earned money, not a
     * promotional credit, so it must never be filed as a Bonus.
     */
    case IbPayout = 'ib_payout';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Refund => 'Refund',
            self::Bonus => 'Bonus',
            self::TransferFrom => 'Transfer From',
            self::TransferTo => 'Transfer To',
            self::IbPayout => 'IB Payout',
        };
    }

    /**
     * MT5 comment prefix for each transaction type.
     *
     * DEP = Deposit, FTD = First Time Deposit (handled in Transaction model),
     * WDL = Withdrawal, REF = Refund, BNS = Bonus, IBP = IB Payout.
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
            self::IbPayout => 'IBP',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::Bonus, self::TransferTo, self::IbPayout => true,
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
