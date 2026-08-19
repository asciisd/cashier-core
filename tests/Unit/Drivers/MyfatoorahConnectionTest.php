<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Testing\WebhookSimulator;

it('registers myfatoorah as a bundled driver', function () {
    expect(config('cashier-core.drivers.myfatoorah'))->toBe(MyfatoorahProvider::class);
});

it('resolves a faked connection to a provider', function () {
    Cashier::fakeConnection('myfatoorah');

    $provider = app(ConnectionRegistry::class)->get('myfatoorah');

    expect($provider)->toBeInstanceOf(MyfatoorahProvider::class)
        ->and($provider->getName())->toBe('myfatoorah');
});

/*
 * MyFatoorah issues one API key per country and each key must reach that
 * country's host, so a merchant trading in two countries is two connections
 * on one driver — the same shape a second APS account has.
 */
it('supports a second country as a second connection on the same driver', function () {
    Cashier::fakeConnection('myfatoorah');
    Cashier::fakeConnection('myfatoorah_sau', [
        'base_url' => 'https://apisa.myfatoorah.com',
        'api_key' => 'sau-key',
        'webhook_secret' => 'sau-secret',
        'currency' => 'SAR',
    ]);

    expect(Connections::forDriver('myfatoorah'))->toBe(['myfatoorah', 'myfatoorah_sau'])
        ->and(Connections::driverFor('myfatoorah_sau'))->toBe('myfatoorah');

    expect(app(ConnectionRegistry::class)->get('myfatoorah_sau'))->toBeInstanceOf(MyfatoorahProvider::class);
});

describe('WebhookSimulator', function () {
    it('signs a delivery the provider verifies', function () {
        Cashier::fakeConnection('myfatoorah');

        $payload = [
            'Event' => ['Code' => 1, 'Name' => 'PAYMENT_STATUS_CHANGED', 'Reference' => 'WH-1'],
            'Data' => [
                'Invoice' => ['Id' => '6409988', 'Status' => 'PAID', 'ExternalIdentifier' => 'DEP-1'],
                'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'PID-1'],
            ],
        ];

        $delivery = WebhookSimulator::make('myfatoorah', $payload);

        expect($delivery->uri)->toBe('/api/webhooks/myfatoorah')
            ->and($delivery->isJson())->toBeTrue()
            ->and($delivery->headers['MyFatoorah-Webhook-Version'])->toBe('v2');

        $provider = app(ConnectionRegistry::class)->get('myfatoorah');

        expect($provider->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeTrue();
    });

    it('signs with the named connection secret, not the default one', function () {
        Cashier::fakeConnection('myfatoorah');
        Cashier::fakeConnection('myfatoorah_sau', [
            'base_url' => 'https://apisa.myfatoorah.com',
            'api_key' => 'sau-key',
            'webhook_secret' => 'sau-secret',
            'currency' => 'SAR',
        ]);

        $payload = [
            'Event' => ['Code' => 1],
            'Data' => [
                'Invoice' => ['Id' => '1', 'Status' => 'PAID', 'ExternalIdentifier' => ''],
                'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'P'],
            ],
        ];

        $delivery = WebhookSimulator::make('myfatoorah', $payload, connection: 'myfatoorah_sau');
        $registry = app(ConnectionRegistry::class);

        expect($registry->get('myfatoorah_sau')->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeTrue()
            ->and($registry->get('myfatoorah')->verifyWebhookSignature($delivery->payload, $delivery->headers['MyFatoorah-Signature']))->toBeFalse();
    });
});
