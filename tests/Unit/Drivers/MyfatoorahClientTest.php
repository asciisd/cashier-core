<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahClient;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = new MyfatoorahClient('https://apitest.myfatoorah.com', 'test-api-key');
});

describe('createPayment', function () {
    it('posts to /v3/payments with bearer auth and the idempotency key', function () {
        Http::fake([
            '*/v3/payments' => Http::response([
                'IsSuccess' => true,
                'Message' => '',
                'ValidationErrors' => null,
                'Data' => [
                    'InvoiceId' => '6309730',
                    'PaymentId' => null,
                    'PaymentURL' => 'https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae',
                    'PaymentCompleted' => false,
                    'TransactionDetails' => null,
                ],
            ]),
        ]);

        $data = $this->client->createPayment(['Order' => ['Amount' => 10.0]], 'DEP-01KZQX');

        expect($data['InvoiceId'])->toBe('6309730')
            ->and($data['PaymentURL'])->toBe('https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://apitest.myfatoorah.com/v3/payments'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request->hasHeader('Idempotency-Key', 'DEP-01KZQX')
                && $request['Order']['Amount'] === 10.0;
        });
    });

    /*
     * The five shapes an `IsSuccess: false` arrives in. Only the first is
     * documented in the Response Model page — see pitfalls.md entry 6.
     */

    it('shape 1: raises the documented ValidationErrors envelope', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Message' => 'Invalid data',
            'ValidationErrors' => [['Name' => 'InvoiceValue', 'Error' => 'must be greater than 0']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'InvoiceValue: must be greater than 0');
    });

    it('shape 2: raises FieldsErrors, the same array under another key', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'FieldsErrors' => [['Name' => 'PaymentMethod', 'Error' => 'is not enabled']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'PaymentMethod: is not enabled');
    });

    it('shape 2b: falls back to the field name when Error is an empty string', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'ValidationErrors' => [['Name' => 'CustomerEmail', 'Error' => '']],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'CustomerEmail');
    });

    it('shape 3: raises Data.ErrorMessage when there is no error array', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Data' => ['ErrorMessage' => 'The payment method is not available'],
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'The payment method is not available');
    });

    /*
     * The dangerous one: no `IsSuccess` field at all. A parser that keys off
     * IsSuccess reads this routing error as a success.
     */
    it('shape 4: raises a Message/MessageDetail body carrying no IsSuccess field', function () {
        Http::fake(['*' => Http::response([
            'Message' => 'No HTTP resource was found that matches the request URI.',
            'MessageDetail' => 'No route data was found.',
        ])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'No HTTP resource was found');
    });

    it('shape 5: raises an HTML 403 that is not JSON at all', function () {
        Http::fake(['*' => Http::response('<html><body>403 Forbidden</body></html>', 403)]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class, 'non-JSON');
    });

    it('accepts IsSuccess as the string "true" as well as the boolean', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => 'true',
            'Data' => ['InvoiceId' => '1', 'PaymentURL' => 'https://pay.test'],
        ])]);

        expect($this->client->createPayment([], 'k')['InvoiceId'])->toBe('1');
    });

    it('raises when the envelope succeeds but carries no Data object', function () {
        Http::fake(['*' => Http::response(['IsSuccess' => true, 'Message' => 'ok', 'Data' => null])]);

        expect(fn () => $this->client->createPayment([], 'k'))
            ->toThrow(PaymentProcessingException::class);
    });
});

describe('getInvoice', function () {
    it('gets /v3/invoices/{id} and returns the Data object', function () {
        Http::fake(['*/v3/invoices/6551972' => Http::response([
            'IsSuccess' => true,
            'Message' => 'Invoice Retrieved Successfully',
            'Data' => [
                'Invoice' => ['Id' => '6551972', 'Status' => 'PAID'],
                'Transactions' => [['Id' => '77235', 'Status' => 'SUCCESS']],
            ],
        ])]);

        $data = $this->client->getInvoice('6551972');

        expect($data['Invoice']['Status'])->toBe('PAID');

        Http::assertSent(fn ($request) => $request->url() === 'https://apitest.myfatoorah.com/v3/invoices/6551972'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    });

    /*
     * MyFatoorah answers an unknown invoice with a 200 and a Message, not a
     * 404. retrieve() must get null so PaymentService reports "not found at
     * provider" rather than throwing mid-sync.
     */
    it('returns null when MyFatoorah does not know the invoice', function () {
        Http::fake(['*' => Http::response([
            'IsSuccess' => false,
            'Message' => 'No invoices match this InvoiceId',
        ])]);

        expect($this->client->getInvoice('404404'))->toBeNull();
    });

    it('returns null on an HTTP failure rather than throwing', function () {
        Http::fake(['*' => Http::response('gateway down', 502)]);

        expect($this->client->getInvoice('6551972'))->toBeNull();
    });
});
