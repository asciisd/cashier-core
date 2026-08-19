<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new MyfatoorahAdapter;
});

/**
 * The PAYMENT_STATUS_CHANGED sample event from references/webhooks.md, with
 * the amounts kept as MyFatoorah sends them: strings, and with the base and
 * display figures deliberately different so the adapter cannot pass by
 * reading the wrong one.
 *
 * @return array<string, mixed>
 */
function myfatoorahWebhook(string $transactionStatus = 'SUCCESS', string $invoiceStatus = 'PAID'): array
{
    return [
        'Event' => [
            'Code' => 1,
            'Name' => 'PAYMENT_STATUS_CHANGED',
            'CountryIsoCode' => 'KWT',
            'CreationDate' => '2026-01-04T08:15:00.9500000Z',
            'Reference' => 'WH-626519',
        ],
        'Data' => [
            'Invoice' => [
                'Id' => '6409988',
                'Status' => $invoiceStatus,
                'Reference' => '2026000073',
                'ExternalIdentifier' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            ],
            'Transaction' => [
                'Id' => '86781',
                'Status' => $transactionStatus,
                'PaymentMethod' => 'VISA/MASTER',
                'PaymentId' => '07076409988323998875',
                'Error' => ['Code' => '', 'Message' => ''],
                'Card' => [
                    'Number' => '512345xxxxxx0008',
                    'Brand' => 'Mastercard',
                    'ExpiryMonth' => '12',
                    'ExpiryYear' => '36',
                    'FundingMethod' => 'credit',
                ],
            ],
            'Amount' => [
                'BaseCurrency' => 'KWD',
                'ValueInBaseCurrency' => '30.75',
                'ServiceCharge' => '0.02',
                'ReceivableAmount' => '30.50',
                'DisplayCurrency' => 'KWD',
                'ValueInDisplayCurrency' => '100.00',
                'PayCurrency' => 'KWD',
                'ValueInPayCurrency' => '100.00',
            ],
        ],
    ];
}

describe('mapStatus', function () {
    it('maps the V3 transaction vocabulary', function () {
        expect($this->adapter->mapStatus('SUCCESS'))->toBe(PaymentStatus::Succeeded)
            ->and($this->adapter->mapStatus('FAILED'))->toBe(PaymentStatus::Failed)
            ->and($this->adapter->mapStatus('CANCELED'))->toBe(PaymentStatus::Canceled)
            ->and($this->adapter->mapStatus('INPROGRESS'))->toBe(PaymentStatus::Processing)
            ->and($this->adapter->mapStatus('AUTHORIZE'))->toBe(PaymentStatus::RequiresCapture);
    });

    it('maps the V3 invoice vocabulary', function () {
        expect($this->adapter->mapStatus('PAID'))->toBe(PaymentStatus::Succeeded)
            ->and($this->adapter->mapStatus('PENDING'))->toBe(PaymentStatus::Pending);
    });

    it('maps unknown and null to Pending', function () {
        expect($this->adapter->mapStatus('SOMETHING_NEW'))->toBe(PaymentStatus::Pending)
            ->and($this->adapter->mapStatus(null))->toBe(PaymentStatus::Pending);
    });

    /*
     * The single most important assertion in this file. V2 spells success
     * `Succss` with one `e` and V3 spells it `SUCCESS`; MyFatoorah's own
     * library compares against the misspelling literally. This driver is V3
     * only, so `Succss` must NOT be honoured — accepting it would mean the
     * two vocabularies had been merged into one table, which is exactly the
     * mistake that starts dropping payments the day either side is
     * corrected. See pitfalls.md entries 1-2.
     */
    it('does not honour the V2 spelling Succss', function () {
        expect($this->adapter->mapStatus('Succss'))->toBe(PaymentStatus::Pending);
    });

    it('is case sensitive, so the lowercase V2 casing does not match', function () {
        expect($this->adapter->mapStatus('success'))->toBe(PaymentStatus::Pending)
            ->and($this->adapter->mapStatus('Paid'))->toBe(PaymentStatus::Pending);
    });
});

describe('fromWebhook', function () {
    it('maps a successful payment event', function () {
        $update = $this->adapter->fromWebhook(myfatoorahWebhook());

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->currency)->toBe('KWD')
            ->and($update->errorMessage)->toBeNull();
    });

    /*
     * The amount basis. ValueInDisplayCurrency is what we asked MyFatoorah to
     * collect — the same basis as transactions.requested_amount, which
     * WebhookProcessor compares this against. ValueInBaseCurrency is the
     * figure converted to the account's base currency; reporting it would put
     * deposits outside the tolerance band and hold every one for review. This
     * is the same class of bug the APS adapter documents for amount_in.
     */
    it('reports the display-currency amount, never the base-currency one', function () {
        $update = $this->adapter->fromWebhook(myfatoorahWebhook());

        expect($update->amount)->toBe(100.0)
            ->and($update->amount)->not->toBe(30.75);
    });

    it('leaves the amount null when the display value is absent', function () {
        $payload = myfatoorahWebhook();
        unset($payload['Data']['Amount']['ValueInDisplayCurrency']);

        expect($this->adapter->fromWebhook($payload)->amount)->toBeNull();
    });

    it('maps a failed payment event and carries the error message', function () {
        $payload = myfatoorahWebhook('FAILED', 'PENDING');
        $payload['Data']['Transaction']['Error'] = ['Code' => '1201', 'Message' => 'Insufficient funds'];

        $update = $this->adapter->fromWebhook($payload);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('Insufficient funds')
            ->and($update->errorCode)->toBe('1201');
    });

    /*
     * WebhookProcessor writes error_message on Canceled as well as Failed.
     * Restricting the message to Failed leaves the customer and support
     * staring at a bare "Canceled" with the reason unread in metadata.
     */
    it('carries the error message on Canceled too', function () {
        $payload = myfatoorahWebhook('CANCELED', 'CANCELED');
        $payload['Data']['Transaction']['Error'] = ['Code' => '', 'Message' => 'Cancelled by customer'];

        $update = $this->adapter->fromWebhook($payload);

        expect($update->status)->toBe(PaymentStatus::Canceled)
            ->and($update->errorMessage)->toBe('Cancelled by customer');
    });

    it('does not invent an error message when the Error object is empty', function () {
        expect($this->adapter->fromWebhook(myfatoorahWebhook('FAILED'))->errorMessage)->toBeNull();
    });

    it('records the correlation and reconciliation fields in metadata', function () {
        $metadata = $this->adapter->fromWebhook(myfatoorahWebhook())->metadata;

        expect($metadata['myfatoorah_invoice_id'])->toBe('6409988')
            ->and($metadata['myfatoorah_payment_id'])->toBe('07076409988323998875')
            ->and($metadata['myfatoorah_transaction_status'])->toBe('SUCCESS')
            ->and($metadata['myfatoorah_invoice_status'])->toBe('PAID')
            ->and($metadata['myfatoorah_receivable_amount'])->toBe('30.50')
            ->and($metadata['myfatoorah_event_reference'])->toBe('WH-626519');
    });

    it('keeps the whole body as the processor response', function () {
        expect($this->adapter->fromWebhook(myfatoorahWebhook())->processorResponse)
            ->toBe(myfatoorahWebhook());
    });

    it('builds a card snapshot from the transaction card', function () {
        $snapshot = $this->adapter->fromWebhook(myfatoorahWebhook())->paymentMethodSnapshot;

        expect($snapshot)->not->toBeNull()
            ->and($snapshot->brand)->toBe(PaymentMethodBrand::Mastercard)
            ->and($snapshot->lastFour)->toBe('0008');
    });

    it('maps a KNET payment to the Knet brand as a debit card', function () {
        $payload = myfatoorahWebhook();
        $payload['Data']['Transaction']['PaymentMethod'] = 'KNET';
        $payload['Data']['Transaction']['Card'] = ['Number' => '', 'Brand' => 'KNET'];

        $snapshot = $this->adapter->fromWebhook($payload)->paymentMethodSnapshot;

        expect($snapshot->brand)->toBe(PaymentMethodBrand::Knet)
            ->and($snapshot->type)->toBe(PaymentMethodType::DebitCard)
            // Not "KNET •••• " — there is no card number on a KNET payment.
            ->and($snapshot->displayName)->toBe('KNET');
    });

    it('omits the snapshot when there is no card object', function () {
        $payload = myfatoorahWebhook();
        unset($payload['Data']['Transaction']['Card']);

        expect($this->adapter->fromWebhook($payload)->paymentMethodSnapshot)->toBeNull();
    });
});

describe('fromProviderResponse', function () {
    it('maps a redirect-flow create response to a pending result carrying the payment URL', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6309730',
            'PaymentId' => null,
            'PaymentURL' => 'https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae',
            'PaymentCompleted' => false,
            'TransactionDetails' => null,
            'amount' => 100.0,
            'currency' => 'KWD',
        ]);

        expect($result->success)->toBeTrue()
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->transactionId)->toBe('6309730')
            ->and($result->currency)->toBe('KWD')
            ->and($result->getRedirectUrl())->toBe('https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae')
            ->and($result->requiresAction())->toBeTrue();
    });

    /*
     * The correlation key is InvoiceId, not PaymentId: PaymentId is null on
     * every redirect-flow create response, so it cannot be what
     * provider_transaction_id is set from.
     */
    it('uses InvoiceId as the transaction id even when PaymentId is present', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322611',
            'PaymentId' => '07076322611317711671',
            'PaymentURL' => 'https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076322611317711671',
            'PaymentCompleted' => false,
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->transactionId)->toBe('6322611');
    });

    it('maps the non-3DS completed case straight to Succeeded', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322611',
            'PaymentId' => '07076322611317711671',
            'PaymentURL' => 'https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076322611317711671',
            'PaymentCompleted' => true,
            'TransactionDetails' => [
                'Invoice' => ['Id' => '6322611', 'Status' => 'PAID'],
                'Transaction' => [
                    'Status' => 'SUCCESS',
                    'PaymentId' => '07076322611317711671',
                    'Card' => ['Number' => '512345xxxxxx0008', 'Brand' => 'Mastercard'],
                ],
            ],
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->success)->toBeTrue()
            ->and($result->paymentMethodSnapshot?->brand)->toBe(PaymentMethodBrand::Mastercard);
    });

    it('reports a completed-but-failed create as unsuccessful', function () {
        $result = $this->adapter->fromProviderResponse([
            'InvoiceId' => '6322612',
            'PaymentURL' => 'https://demo.MyFatoorah.com/result',
            'PaymentCompleted' => true,
            'TransactionDetails' => ['Transaction' => ['Status' => 'FAILED', 'Error' => ['Message' => 'Do not honour']]],
            'amount' => 10.0,
            'currency' => 'KWD',
        ]);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->success)->toBeFalse()
            ->and($result->message)->toBe('Do not honour');
    });
});

describe('fromProviderPayload', function () {
    /*
     * An invoice holds an ARRAY of transactions, one per attempt, and
     * Invoice.Status does not track them. Scan for any SUCCESS first: if one
     * exists the invoice is paid whatever the other entries say. See
     * pitfalls.md entry 7.
     */
    it('finds a success among earlier failures whatever their order', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
            'Transactions' => [
                ['Id' => '1', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:00:00Z', 'Error' => ['Message' => 'Declined']],
                ['Id' => '2', 'Status' => 'SUCCESS', 'TransactionDate' => '2026-03-01T08:03:54Z', 'PaymentId' => 'PID-2'],
                ['Id' => '3', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:05:00Z', 'Error' => ['Message' => 'Declined']],
            ],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->transactionId)->toBe('6551972')
            ->and($result->metadata['myfatoorah_payment_id'])->toBe('PID-2');
    });

    it('falls back to the latest attempt by TransactionDate when none succeeded', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PENDING'],
            'Transactions' => [
                ['Id' => '1', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:00:00Z', 'Error' => ['Message' => 'First decline']],
                ['Id' => '2', 'Status' => 'FAILED', 'TransactionDate' => '2026-03-01T08:09:00Z', 'Error' => ['Message' => 'Last decline']],
            ],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->message)->toBe('Last decline');
    });

    it('falls back to the invoice status when there are no transactions at all', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PENDING'],
            'Transactions' => [],
            'Amount' => ['DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '10'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Pending);
    });

    it('reads the display-currency amount and currency', function () {
        $result = $this->adapter->fromProviderPayload('6551972', [
            'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
            'Transactions' => [['Id' => '1', 'Status' => 'SUCCESS', 'TransactionDate' => '2026-03-01T08:03:54Z']],
            'Amount' => ['BaseCurrency' => 'KWD', 'ValueInBaseCurrency' => '30.75', 'DisplayCurrency' => 'KWD', 'ValueInDisplayCurrency' => '100'],
        ]);

        expect($result->amount)->toBe(100)
            ->and($result->currency)->toBe('KWD');
    });
});

describe('getProviderName', function () {
    it('is myfatoorah', function () {
        expect($this->adapter->getProviderName())->toBe('myfatoorah');
    });
});
