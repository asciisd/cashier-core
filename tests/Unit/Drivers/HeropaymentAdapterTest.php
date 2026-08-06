<?php

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new HeropaymentAdapter;
});

describe('mapStatus', function () {
    it('maps finished and sending to Succeeded (per Heropayment deposit guidance)', function () {
        expect($this->adapter->mapStatus('finished'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapStatus('sending'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps waiting and confirming to Pending', function () {
        expect($this->adapter->mapStatus('waiting'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('confirming'))->toBe(PaymentStatus::Pending);
    });

    it('maps exchanging and hold to Processing', function () {
        expect($this->adapter->mapStatus('exchanging'))->toBe(PaymentStatus::Processing);
        expect($this->adapter->mapStatus('hold'))->toBe(PaymentStatus::Processing);
    });

    it('maps failed and expired to Failed', function () {
        expect($this->adapter->mapStatus('failed'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('expired'))->toBe(PaymentStatus::Failed);
    });

    it('maps refunded to Canceled', function () {
        expect($this->adapter->mapStatus('refunded'))->toBe(PaymentStatus::Canceled);
    });
});

describe('fromProviderResponse', function () {
    it('builds a pending PaymentResult with the invoice url', function () {
        $result = $this->adapter->fromProviderResponse([
            'id' => '55043fdf-bf9a-49d7-8021-33434d65112',
            'customerId' => '123',
            'priceAmount' => '34',
            'priceCurrency' => 'usd',
            'externalOrderId' => 'DEP-64536456',
            'invoiceUrl' => 'https://pay.heropayments.io/pay/55043fdf',
        ]);

        expect($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->getRedirectUrl())->toBe('https://pay.heropayments.io/pay/55043fdf')
            ->and($result->currency)->toBe('USD')
            ->and($result->amount)->toBe(34);
    });
});

describe('fromWebhook', function () {
    it('maps a finished deposit callback', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-1',
            'externalOrderId' => 'DEP-1',
            'status' => 'finished',
            'priceAmount' => '100',
            'priceCurrency' => 'usd',
            'payCurrency' => 'usdttrc20',
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(100.0)
            ->and($update->currency)->toBe('USD')
            ->and($update->metadata['heropayment_payment_id'])->toBe('pay-1')
            ->and($update->paymentMethodSnapshot->displayName)->toBe('Crypto (USDTTRC20)');
    });

    it('flags hold callbacks as requiring attention', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-1',
            'externalOrderId' => 'DEP-1',
            'status' => 'hold',
        ]);

        expect($update->status)->toBe(PaymentStatus::Processing)
            ->and($update->metadata['requires_attention'])->toBeTrue();
    });

    it('maps expired callbacks to Failed with a message', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-1',
            'externalOrderId' => 'DEP-1',
            'status' => 'expired',
        ]);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('Heropayment status: expired');
    });
});

describe('crypto payment method snapshot', function () {
    /**
     * The snapshot drives the logo shown on the transaction. Reporting crypto as
     * a DigitalWallet of brand Other made the UI fall back to a wallet glyph —
     * and previously to the PayPal logo — for a USDT deposit.
     */
    it('resolves the coin brand from the ticker, ignoring the network suffix', function (string $ticker, PaymentMethodBrand $brand) {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-1',
            'externalOrderId' => 'DEP-1',
            'status' => 'finished',
            'payCurrency' => $ticker,
        ]);

        expect($update->paymentMethodSnapshot->brand)->toBe($brand)
            ->and($update->paymentMethodSnapshot->type)->toBe(PaymentMethodType::Cryptocurrency);
    })->with([
        // Every ticker enabled on the heropayment connection.
        ['usdttrc20', PaymentMethodBrand::USDT],
        ['usdt20', PaymentMethodBrand::USDT],
        ['usdtbsc', PaymentMethodBrand::USDT],
        ['usdc', PaymentMethodBrand::USDC],
        ['usdcbsc', PaymentMethodBrand::USDC],
        ['btc', PaymentMethodBrand::Bitcoin],
        ['eth', PaymentMethodBrand::Ethereum],
    ]);

    it('stays Other for a coin we hold no mark for, rather than guessing', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-1',
            'externalOrderId' => 'DEP-1',
            'status' => 'finished',
            'payCurrency' => 'doge',
        ]);

        expect($update->paymentMethodSnapshot->brand)->toBe(PaymentMethodBrand::Other);
    });

    it('reports crypto as Cryptocurrency even before a coin is chosen', function () {
        $result = $this->adapter->fromProviderResponse([
            'id' => 'inv-1',
            'priceAmount' => '10',
            'priceCurrency' => 'usd',
            'invoiceUrl' => 'https://pay.heropayments.io/pay/inv-1',
        ]);

        expect($result->paymentMethodSnapshot->type)->toBe(PaymentMethodType::Cryptocurrency)
            ->and($result->paymentMethodSnapshot->brand)->toBe(PaymentMethodBrand::Other)
            ->and($result->paymentMethodSnapshot->displayName)->toBe('Crypto');
    });
});
