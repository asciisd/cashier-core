<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\TransactionType;

it('describes an IB payout as an incoming IB Payout, not a bonus', function () {
    $type = TransactionType::from('ib_payout');

    expect($type)->toBe(TransactionType::IbPayout)
        ->and($type->label())->toBe('IB Payout')
        ->and($type->mt5Prefix())->toBe('IBP')
        ->and($type->isCredit())->toBeTrue()
        ->and($type->isTransfer())->toBeFalse()
        ->and($type)->not->toBe(TransactionType::Bonus);
});

it('gives every type a label, an MT5 prefix and a direction', function (TransactionType $type) {
    expect($type->label())->not->toBe('')
        ->and($type->mt5Prefix())->not->toBe('')
        ->and($type->isCredit())->toBe(! $type->isDebit());
})->with(TransactionType::cases());
