<?php

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Drivers\Aps\ApsAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new ApsAdapter;
});

/**
 * A real completed APS deposit under `settlement_mode: added` (production txn
 * 688): a 100.00 order, APS's own 6.25 customer fee added at checkout, 106.25
 * debited, 100.00 settled.
 *
 * @return array<string, mixed>
 */
function apsCompletedDepositCallback(): array
{
    return [
        'payload' => [
            'id' => 'd540c361-0b03-4f14-902b-c69da1b98014',
            'transaction_id' => 'd540c361-0b03-4f14-902b-c69da1b98014',
            'external_id' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            'status' => 'completed',
            'fiscal_status' => 'done',
            'amount' => 100,
            'amount_in' => 106.25,
            'amount_out' => 100,
            'amount_fee' => 6.25,
            'amount_body' => 100,
            'customer_fee' => 6.25,
            'merchant_fee' => 0,
        ],
    ];
}

describe('mapStatus', function () {
    it('maps success statuses to Succeeded', function () {
        expect($this->adapter->mapStatus('done'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapStatus('completed'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps failure statuses to Failed', function () {
        expect($this->adapter->mapStatus('failed'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('error'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('expired'))->toBe(PaymentStatus::Failed);
    });

    it('maps canceled to Canceled', function () {
        expect($this->adapter->mapStatus('canceled'))->toBe(PaymentStatus::Canceled);
        expect($this->adapter->mapStatus('cancelled'))->toBe(PaymentStatus::Canceled);
    });

    it('maps pending_external (AML review) to Processing', function () {
        expect($this->adapter->mapStatus('pending_external'))->toBe(PaymentStatus::Processing);
    });

    it('maps in-flight and unknown statuses to Pending', function () {
        expect($this->adapter->mapStatus('pending'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('pending_sender'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('something_new'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus(null))->toBe(PaymentStatus::Pending);
    });

    /*
     * The five below arrive only on the callback path, where `status` carries
     * APS's PSP-transaction vocabulary rather than the sep31 one — except
     * `pending_transaction_info_update`, which is sep31. None had an arm, so
     * all five landed on `default => Pending`. See the aps-payments skill,
     * references/quirks.md entry 6.
     */

    it('maps payed to Processing — the customer paid but funds have not settled', function () {
        expect($this->adapter->mapStatus('payed'))->toBe(PaymentStatus::Processing);
    });

    it('maps refund_pending to Processing — the acquirer has not answered yet', function () {
        expect($this->adapter->mapStatus('refund_pending'))->toBe(PaymentStatus::Processing);
    });

    /*
     * Canceled is how this package already represents a refunded deposit:
     * WebhookProcessor lets a settled deposit move only to Canceled
     * ("refund/chargeback"), and both HeropaymentAdapter and JenapayAdapter
     * map their own `refunded` the same way.
     */
    it('maps refunded to Canceled', function () {
        expect($this->adapter->mapStatus('refunded'))->toBe(PaymentStatus::Canceled);
    });

    /*
     * A rejected refund leaves the payment standing, so the transaction is
     * still a settled one. Mapping it to Pending would have un-settled a
     * completed deposit had the out-of-order guard not been there to drop it.
     */
    it('maps refund_rejected to Succeeded — the payment still stands', function () {
        expect($this->adapter->mapStatus('refund_rejected'))->toBe(PaymentStatus::Succeeded);
    });

    /*
     * The docs are explicit: "An error occurred during transaction
     * initiation. This status should be interpreted as an error."
     */
    it('maps pending_transaction_info_update to Failed despite the pending_ prefix', function () {
        expect($this->adapter->mapStatus('pending_transaction_info_update'))->toBe(PaymentStatus::Failed);
    });
});

describe('fromProviderResponse', function () {
    it('builds a pending PaymentResult with the checkout url from `how`', function () {
        $result = $this->adapter->fromProviderResponse([
            'id' => '1a2d87dc-3a6e-48dd-be69-8ad6492cc8b4',
            'amount' => 1000,
            'amount_in' => 1000,
            'amount_out' => 975.5,
            'customer_fee' => 0,
            'how' => 'https://checkout.example.com/pay/abc',
        ]);

        expect($result)->toBeInstanceOf(PaymentResult::class)
            ->and($result->transactionId)->toBe('1a2d87dc-3a6e-48dd-be69-8ad6492cc8b4')
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->getRedirectUrl())->toBe('https://checkout.example.com/pay/abc')
            ->and($result->requiresAction())->toBeTrue()
            ->and($result->metadata['aps_amount_out'])->toBe(975.5);
    });
});

describe('fromProviderPayload', function () {
    /*
     * The retrieve path had the same inverted preference as the callback path.
     * Nothing compares this figure against the invoice today — sync omits the
     * amount from its update — but the two paths read the same payload and must
     * not disagree about which field the transaction was for.
     */
    it('reports the order amount, not the customer debit including APS fees', function () {
        $result = $this->adapter->fromProviderPayload(
            'd540c361-0b03-4f14-902b-c69da1b98014',
            apsCompletedDepositCallback(),
        );

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->amount)->toBe(100)
            ->and($result->metadata['aps_amount_in'])->toBe(106.25);
    });
});

describe('fromWebhook', function () {
    it('unwraps the nested payload key and maps a completed deposit', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'b829f009-afe0-45c2-9996-8941f80bcb0e',
                'sep31_status' => 'completed',
                'status' => 'done',
                'refunded' => false,
                'amount' => 500,
                'amount_in' => 500,
                'amount_out' => 475.5,
                'external_message' => 'APPROVED',
            ],
        ]);

        expect($update)->toBeInstanceOf(TransactionWebhookUpdate::class)
            ->and($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(500.0)
            ->and($update->metadata['aps_transaction_id'])->toBe('b829f009-afe0-45c2-9996-8941f80bcb0e')
            ->and($update->errorMessage)->toBeNull();
    });

    /*
     * The production callback for txn 688, verbatim. APS collected the 100.00
     * order, added its own 6.25 customer fee at checkout, debited the customer
     * 106.25 and settled 100.00 to us. `amount` is the order — the same basis
     * as `transactions.requested_amount`, which WebhookProcessor compares the
     * reported figure against. Reporting `amount_in` instead put every
     * `settlement_mode: added` deposit 6.25% outside the tolerance band and
     * held it for review.
     */
    it('reports the order amount, not the customer debit including APS fees', function () {
        $update = $this->adapter->fromWebhook(apsCompletedDepositCallback());

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(100.0)
            // The customer's debit and the merchant settlement stay reachable —
            // `aps_amount_out` is what the fee-drift check reads.
            ->and($update->metadata['aps_amount_in'])->toBe(106.25)
            ->and($update->metadata['aps_amount_out'])->toBe(100);
    });

    /*
     * No amount means no amount assertion (WebhookProcessor short-circuits on
     * null). Falling back to `amount_in` here would reinstate the bug on any
     * callback shape that omits the order figure.
     */
    it('reports no amount at all rather than falling back to the customer debit', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'tx-no-order-amount',
                'status' => 'done',
                'amount_in' => 106.25,
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBeNull();
    });

    it('handles a flat (non-nested) payload and failed status with message', function () {
        $update = $this->adapter->fromWebhook([
            'transaction_id' => 'tx-1',
            'status' => 'failed',
            'external_message' => 'DECLINED',
        ]);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('DECLINED');
    });

    /*
     * APS reports a downstream rejection as `canceled`, not `failed` — a
     * device restriction, a risk decline, an unsupported instrument all arrive
     * this way. Dropping the message there is what made a real desktop-only
     * Binance Pay rejection reach support as an unexplained "Canceled".
     */
    it('carries the provider message on a canceled callback', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => '19cd300d-3a31-426b-9f6d-0de9f45a0098',
                'status' => 'canceled',
                'external_status' => 'something_went_wrong',
                'external_message' => '512: Desktop devices are not supported. Please use a mobile device.',
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Canceled)
            ->and($update->errorMessage)->toBe('512: Desktop devices are not supported. Please use a mobile device.')
            ->and($update->metadata['aps_external_message'])->toBe('512: Desktop devices are not supported. Please use a mobile device.');
    });

    it('leaves errorMessage null while a transaction is still in flight', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'tx-inflight',
                'status' => 'pending',
                'external_message' => 'awaiting sender',
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Pending)
            ->and($update->errorMessage)->toBeNull();
    });

    it('falls back to sep31_status when fiscal status is absent', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'tx-2',
                'sep31_status' => 'pending_external',
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Processing);
    });
});

/*
 * The engine used to read the HOST app's `transactions.currency.default` here.
 * It happened to hold 'USD' in the app the code was extracted from, so the
 * coupling was invisible — any other host would have had its configured
 * currency silently ignored in favour of the literal default.
 */
it('takes the currency default from the package config, not a host config file', function () {
    config()->set('cashier-core.currency.default', 'EUR');
    config()->set('transactions.currency.default', 'JPY');

    $result = $this->adapter->fromProviderResponse(['id' => 'tx-3', 'amount' => 10]);

    expect($result->currency)->toBe('EUR');
});
