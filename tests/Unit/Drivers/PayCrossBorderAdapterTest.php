<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderAdapter;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;

it('maps PCB statuses to payout states', function (?string $name, ?string $code, PayoutState $state) {
    expect((new PayCrossBorderAdapter)->mapPayoutStatus($name, $code))->toBe($state);
})->with([
    'in process' => ['In Process', null, PayoutState::Sent],
    'payout processing' => ['Payout Processing', 'IP', PayoutState::Sent],
    'case and spacing' => ['  payout PROCESSING ', null, PayoutState::Sent],
    'pass' => ['Payout Pass', null, PayoutState::Paid],
    'fail' => ['Payout Fail', null, PayoutState::Failed],
    'reverse' => ['Reverse', null, PayoutState::Failed],
    // The docs do not say whether money moved; Failed would allow a refund or resend.
    'technical failure' => ['Technical Failure', null, PayoutState::Unknown],
    'unrecognised' => ['Screening Hold', null, PayoutState::Unknown],
    'short code only' => [null, 'IP', PayoutState::Sent],
    'unknown short code only' => ['', 'PP', PayoutState::Unknown],
    'nothing' => [null, null, PayoutState::Unknown],
]);

it('builds a receipt from an enquiry row', function () {
    $row = PayCrossBorderApi::transaction()['transactionInfoResponse'][0];

    $receipt = (new PayCrossBorderAdapter)->payoutReceipt($row);

    expect($receipt->reference)->toBe('197930')
        ->and($receipt->rawStatus)->toBe('Payout Processing')
        ->and($receipt->state)->toBe(PayoutState::Sent)
        ->and($receipt->payload)->toBe($row)
        ->and($receipt->error)->toBeNull();
});

it('carries PCB\'s reason on a failed row', function () {
    $row = PayCrossBorderApi::transaction([
        'statusName' => 'Payout Fail',
        'transactionStatus' => 'PF',
        'vendorResultDescription' => 'Beneficiary account closed',
    ])['transactionInfoResponse'][0];

    $receipt = (new PayCrossBorderAdapter)->payoutReceipt($row);

    expect($receipt->state)->toBe(PayoutState::Failed)
        ->and($receipt->error)->toBe('PayCrossBorder Payout Fail: Beneficiary account closed');
});

it('falls back to the short code for the raw status', function () {
    $row = PayCrossBorderApi::transaction(['statusName' => null])['transactionInfoResponse'][0];

    expect((new PayCrossBorderAdapter)->payoutReceipt($row)->rawStatus)->toBe('IP');
});
