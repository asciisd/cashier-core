<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FeeConfigurationContract;
use Asciisd\CashierCore\Contracts\UsesReportedFee;
use Asciisd\CashierCore\Enums\FeeSource;
use Asciisd\CashierCore\Enums\SettlementMode;
use Asciisd\CashierCore\Fees\FeeCalculator;

function feeSourceConfig(SettlementMode $mode, ?FeeSource $source): FeeConfigurationContract
{
    $base = new class($mode) implements FeeConfigurationContract
    {
        public function __construct(private SettlementMode $mode) {}

        public function settlementMode(): SettlementMode
        {
            return $this->mode;
        }

        public function feePercentage(): float
        {
            return 3.0;
        }

        public function feeFixed(): float
        {
            return 0.0;
        }

        public function markupPercentage(): float
        {
            return 0.0;
        }

        public function markupFixed(): float
        {
            return 0.0;
        }

        public function feeConfigurationId(): int|string|null
        {
            return 'apple-pay';
        }
    };

    if ($source === null) {
        return $base;
    }

    return new class($mode, $source) implements FeeConfigurationContract, UsesReportedFee
    {
        public function __construct(private SettlementMode $mode, private FeeSource $source) {}

        public function settlementMode(): SettlementMode
        {
            return $this->mode;
        }

        public function feePercentage(): float
        {
            return 3.0;
        }

        public function feeFixed(): float
        {
            return 0.0;
        }

        public function markupPercentage(): float
        {
            return 0.0;
        }

        public function markupFixed(): float
        {
            return 0.0;
        }

        public function feeConfigurationId(): int|string|null
        {
            return 'apple-pay';
        }

        public function feeSource(): FeeSource
        {
            return $this->source;
        }
    };
}

it('treats a configuration without the contract as configured', function () {
    $breakdown = (new FeeCalculator)->for(feeSourceConfig(SettlementMode::Deducted, null), 100);

    expect($breakdown->feeSource)->toBe(FeeSource::Configured)
        ->and($breakdown->feeIsEstimate())->toBeFalse()
        ->and($breakdown->toArray()['fee_is_estimate'])->toBeFalse();
});

it('marks a reported-fee breakdown as an estimate without changing the price', function () {
    $configured = (new FeeCalculator)->for(feeSourceConfig(SettlementMode::Deducted, FeeSource::Configured), 100);
    $reported = (new FeeCalculator)->for(feeSourceConfig(SettlementMode::Deducted, FeeSource::Reported), 100);

    expect($reported->feeIsEstimate())->toBeTrue()
        ->and($reported->requestedAmount)->toBe($configured->requestedAmount)
        ->and($reported->pspFee)->toBe($configured->pspFee)
        ->and($reported->toArray()['fee_source'])->toBe('reported');
});

it('allows a reported fee when invoiced', function () {
    $breakdown = (new FeeCalculator)->for(feeSourceConfig(SettlementMode::Invoiced, FeeSource::Reported), 100);

    expect($breakdown->feeIsEstimate())->toBeTrue();
});

it('refuses a reported fee in the added mode', function () {
    (new FeeCalculator)->for(feeSourceConfig(SettlementMode::Added, FeeSource::Reported), 100);
})->throws(InvalidArgumentException::class, 'reported fee');
