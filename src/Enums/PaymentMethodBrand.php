<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

enum PaymentMethodBrand: string
{
    // Credit/Debit Cards
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case AmericanExpress = 'american_express';
    case Discover = 'discover';
    case JCB = 'jcb';
    case DinersClub = 'diners_club';
    case UnionPay = 'union_pay';
    
    // Digital Wallets & Payment Processors
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';
    case SamsungPay = 'samsung_pay';
    case PayPal = 'paypal';
    case AliPay = 'alipay';
    case WeChat = 'wechat';
    
    // Cryptocurrency
    case BinancePay = 'binance_pay';
    case Bitcoin = 'bitcoin';
    case Ethereum = 'ethereum';
    case USDT = 'usdt';
    case USDC = 'usdc';
    
    // Regional Payment Methods
    case Fawry = 'fawry';
    case Vodafone = 'vodafone';
    case Orange = 'orange';
    case Etisalat = 'etisalat';
    case InstaPay = 'instapay';
    case ValU = 'valu';
    
    // Bank Transfers
    case WireTransfer = 'wire_transfer';
    case SEPA = 'sepa';
    case ACH = 'ach';
    case SWIFT = 'swift';
    
    // Cash & Other
    case Cash = 'cash';
    case Check = 'check';
    case BankDeposit = 'bank_deposit';
    case MoneyOrder = 'money_order';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            // Credit/Debit Cards
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::AmericanExpress => 'American Express',
            self::Discover => 'Discover',
            self::JCB => 'JCB',
            self::DinersClub => 'Diners Club',
            self::UnionPay => 'UnionPay',
            
            // Digital Wallets & Payment Processors
            self::ApplePay => 'Apple Pay',
            self::GooglePay => 'Google Pay',
            self::SamsungPay => 'Samsung Pay',
            self::PayPal => 'PayPal',
            self::AliPay => 'AliPay',
            self::WeChat => 'WeChat Pay',
            
            // Cryptocurrency
            self::BinancePay => 'Binance Pay',
            self::Bitcoin => 'Bitcoin',
            self::Ethereum => 'Ethereum',
            self::USDT => 'USDT',
            self::USDC => 'USDC',
            
            // Regional Payment Methods
            self::Fawry => 'Fawry',
            self::Vodafone => 'Vodafone Cash',
            self::Orange => 'Orange Money',
            self::Etisalat => 'Etisalat Cash',
            self::InstaPay => 'InstaPay',
            self::ValU => 'valU',
            
            // Bank Transfers
            self::WireTransfer => 'Wire Transfer',
            self::SEPA => 'SEPA Transfer',
            self::ACH => 'ACH Transfer',
            self::SWIFT => 'SWIFT Transfer',
            
            // Cash & Other
            self::Cash => 'Cash',
            self::Check => 'Check',
            self::BankDeposit => 'Bank Deposit',
            self::MoneyOrder => 'Money Order',
            self::Other => 'Other',
        };
    }

    public function getType(): PaymentMethodType
    {
        return match ($this) {
            self::Visa, self::Mastercard, self::AmericanExpress, 
            self::Discover, self::JCB, self::DinersClub, self::UnionPay => PaymentMethodType::CreditCard,
            
            self::ApplePay, self::GooglePay, self::SamsungPay, self::PayPal,
            self::AliPay, self::WeChat, self::Fawry, self::Vodafone, 
            self::Orange, self::Etisalat, self::InstaPay, self::ValU => PaymentMethodType::DigitalWallet,
            
            self::BinancePay, self::Bitcoin, self::Ethereum, 
            self::USDT, self::USDC => PaymentMethodType::Cryptocurrency,
            
            self::WireTransfer, self::SEPA, self::ACH, 
            self::SWIFT, self::BankDeposit => PaymentMethodType::BankTransfer,
            
            self::Cash => PaymentMethodType::Cash,
            self::Check, self::MoneyOrder => PaymentMethodType::Check,
            self::Other => PaymentMethodType::Other,
        };
    }

    public function requiresLastFour(): bool
    {
        return match ($this) {
            self::Visa, self::Mastercard, self::AmericanExpress,
            self::Discover, self::JCB, self::DinersClub, self::UnionPay => true,
            default => false,
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Visa => 'visa',
            self::Mastercard => 'mastercard',
            self::AmericanExpress => 'american-express',
            self::ApplePay => 'apple-pay',
            self::GooglePay => 'google-pay',
            self::PayPal => 'paypal',
            self::BinancePay => 'binance-pay',
            self::Fawry => 'fawry',
            self::WireTransfer => 'wire-transfer',
            default => 'credit-card',
        };
    }
}
