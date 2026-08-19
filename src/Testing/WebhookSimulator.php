<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Testing;

use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Jenapay\JenapayHashService;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;
use Asciisd\CashierCore\Drivers\Payport\PayportSignatureService;
use Asciisd\CashierCore\Drivers\Sticpay\SticpaySignatureService;
use InvalidArgumentException;

/**
 * Builds webhook deliveries signed with a connection's configured secrets —
 * the package-side stand-in for a PSP actually calling back.
 *
 * The signatures come from the same services the controllers verify with
 * (Payport/Sticpay/Jenapay) or the same recipe the clients check
 * (APS/Heropayment HMAC over the raw body), so a delivery built here passes
 * verification exactly when a real one would.
 */
final class WebhookSimulator
{
    /**
     * @param  array<string, mixed>  $payload  the driver's callback body; for
     *         Sticpay, the flat callback parameters — the double-JSON envelope
     *         is built here
     * @param  string|null  $connection  the account that "sent" the callback;
     *         defaults to the connection named after the driver
     */
    public static function make(string $driver, array $payload, ?string $connection = null): SignedWebhook
    {
        $name = $connection ?? $driver;
        $config = Connections::get($name);

        if ($config === null) {
            throw new InvalidArgumentException(
                "Cannot simulate a '{$driver}' webhook: connection '{$name}' is not configured. Configure it or call Cashier::fakeConnection('{$name}') first."
            );
        }

        $uri = '/'.trim((string) config('cashier-core.routes.prefix', 'api/webhooks'), '/')."/{$driver}";

        return match ($driver) {
            'aps' => new SignedWebhook($uri, $payload, [
                'X-Signature' => hash_hmac(
                    'sha256',
                    (string) json_encode($payload),
                    (string) (($config['callback_secret'] ?? null) ?: ($config['app_secret'] ?? '')),
                ),
            ], 'json'),

            'heropayment' => new SignedWebhook($uri, $payload, [
                'x-api-sign' => hash_hmac(
                    'sha512',
                    (string) json_encode($payload),
                    (string) ($config['api_secret'] ?? ''),
                ),
            ], 'json'),

            'myfatoorah' => self::myfatoorah($uri, $payload, $config),

            'jenapay' => self::jenapay($uri, $payload, $config),
            'payport' => self::payport($uri, $payload, $config),
            'sticpay' => self::sticpay($uri, $payload, $config),

            default => throw new InvalidArgumentException(
                "WebhookSimulator has no signing recipe for driver '{$driver}'."
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function jenapay(string $uri, array $payload, array $config): SignedWebhook
    {
        $payload['hash'] = (new JenapayHashService((string) ($config['password'] ?? '')))->forCallback(
            (string) ($payload['id'] ?? ''),
            (string) ($payload['order_number'] ?? ''),
            (string) ($payload['order_amount'] ?? ''),
            (string) ($payload['order_currency'] ?? ''),
            (string) ($payload['order_description'] ?? ''),
        );

        return new SignedWebhook($uri, $payload, [], 'form');
    }

    /**
     * MyFatoorah signs a canonical field list, not the body, and carries the
     * version in a header the docs never mention — a controller that does not
     * read it cannot know which of the two signing rules applies.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function myfatoorah(string $uri, array $payload, array $config): SignedWebhook
    {
        $service = new MyfatoorahSignatureService((string) ($config['webhook_secret'] ?? ''));

        $signature = $service->sign(
            (int) data_get($payload, 'Event.Code', 0),
            (array) ($payload['Data'] ?? []),
        );

        return new SignedWebhook($uri, $payload, [
            'MyFatoorah-Signature' => $signature,
            'MyFatoorah-Webhook-Version' => 'v2',
        ], 'json');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function payport(string $uri, array $payload, array $config): SignedWebhook
    {
        unset($payload['signature']);

        $payload['signature'] = (new PayportSignatureService((string) ($config['api_key'] ?? '')))
            ->sign($payload);

        return new SignedWebhook($uri, $payload, [], 'form');
    }

    /**
     * The wire shape is one form field holding a double JSON-encoded envelope
     * around the signed flat parameters.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private static function sticpay(string $uri, array $payload, array $config): SignedWebhook
    {
        unset($payload['sign']);

        $signType = (string) ($config['sign_type'] ?? SticpaySignatureService::MD5);
        $service = new SticpaySignatureService((string) ($config['api_key'] ?? ''), $signType);

        $parameters = array_merge($payload, [
            'sign_type' => $signType,
            'sign' => $service->sign($payload, SticpaySignatureService::CALLBACK),
        ]);

        $body = ['callback' => json_encode([
            'type' => 'processing',
            'code' => -1,
            'message' => '',
            'parameters' => json_encode($parameters),
        ])];

        return new SignedWebhook($uri, $body, [], 'form');
    }
}
