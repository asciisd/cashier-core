<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Testing;

/**
 * A webhook delivery signed the way the driver's PSP would sign it.
 *
 * Post it with the format the driver actually uses on the wire:
 *
 *     $delivery = WebhookSimulator::make('aps', $payload);
 *
 *     $delivery->isJson()
 *         ? $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)
 *         : $this->post($delivery->uri, $delivery->payload, $delivery->headers);
 */
final readonly class SignedWebhook
{
    public function __construct(
        public string $uri,
        /** @var array<string, mixed> */
        public array $payload,
        /** @var array<string, string> */
        public array $headers,
        public string $format,
    ) {}

    public function isJson(): bool
    {
        return $this->format === 'json';
    }
}
