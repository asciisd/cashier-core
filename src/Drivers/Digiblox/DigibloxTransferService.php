<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;

/**
 * Centralized Transfer — crypto withdrawals.
 *
 * Three facts shape this class. The endpoint has no idempotency key, so a
 * second POST after the first advances past QUEUED creates an independent
 * second withdrawal. There is no sandbox, so the first live run moves real
 * money. And the API validates neither the amount format nor the destination
 * address — malformed input is accepted, not rejected. Everything below is a
 * consequence of one of those three.
 */
class DigibloxTransferService
{
    /**
     * Fixed reporting values. The spec is explicit that these are used for
     * internal fiat-value reporting, are not re-derived by the platform, and
     * must be sent exactly as given — never computed.
     */
    private const INITIAL_RATE = '1.00';

    private const INITIAL_RATE_CURRENCY_ID = 'bkE0RmNjbEhCUmc9';

    private const MAX_NOTE_LENGTH = 255;

    private DigibloxClient $client;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $this->client = DigibloxClient::fromConfig($config);
    }

    /**
     * @return array{id: string, status: string}
     */
    public function create(
        string $amount,
        string $toAddress,
        string $network,
        string $asset,
        ?string $note = null,
    ): array {
        if (! (bool) ($this->config['withdrawals_enabled'] ?? false)) {
            throw new PaymentProcessingException(
                'Digiblox withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // The API accepts non-numeric and malformed amount strings without
        // complaint, so the guard has to live here.
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new PaymentProcessingException('Digiblox withdrawal amount must be a positive number.');
        }

        $cap = (float) ($this->config['withdrawal_max_amount'] ?? 0);

        if ($cap > 0 && (float) $amount > $cap) {
            throw new PaymentProcessingException(
                sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap),
            );
        }

        // Likewise: the API performs no address format or checksum check.
        if (trim($toAddress) === '') {
            throw new PaymentProcessingException('Digiblox withdrawal requires a destination address.');
        }

        $body = array_filter([
            'amount' => $amount,
            'toAddress' => trim($toAddress),
            'network' => strtoupper($network),
            'asset' => strtoupper($asset),
            'initial_rate' => self::INITIAL_RATE,
            'initial_rate_currency_id' => self::INITIAL_RATE_CURRENCY_ID,
            // The one free-text field on the API, and the only way to carry our
            // own reference into Digiblox's treasury exports.
            'user_note' => $note !== null ? substr($note, 0, self::MAX_NOTE_LENGTH) : null,
        ], fn ($value) => $value !== null);

        $response = PspHttp::client()
            ->withToken($this->client->authToken())
            ->acceptJson()
            ->post($this->baseUrl().'/gateway/api/v1/v3/transfers/centralized', $body);

        // 202 Accepted, not 200.
        if ($response->status() !== 202) {
            throw new PaymentProcessingException(
                'Digiblox rejected the withdrawal: '.trim($response->body()),
            );
        }

        return [
            'id' => (string) ($response->json('id') ?? ''),
            'status' => (string) ($response->json('status') ?? 'QUEUED'),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://app.digiblox.io'), '/');
    }
}
