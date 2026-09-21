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

    /**
     * A strict decimal-string pattern, stricter than is_numeric(). PHP 8.3+
     * bcmath requires a "well-formed numeric string" for bccomp(); is_numeric()
     * accepts whitespace-padding and scientific notation ("2.5e1"), both of
     * which crash bccomp() with an uncaught ValueError. Applied only after
     * trimming, so surrounding whitespace is not itself a rejection reason.
     */
    private const NUMERIC_PATTERN = '/^-?\d+(\.\d+)?$/';

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
        // complaint, so the guard has to live here. Trim first — whitespace
        // carries no meaning, and rejecting a stray space (an ordinary
        // config/.env typo) would turn a typo into an outage. Then require a
        // strict decimal pattern rather than is_numeric(): PHP 8.3+ bcmath
        // demands a "well-formed numeric string", which is stricter than
        // is_numeric() — " 100" and "2.5e1" both pass is_numeric() but crash
        // bccomp() below with an uncaught ValueError instead of the graceful
        // PaymentProcessingException this whole class exists to guarantee.
        $amount = trim($amount);

        if (! preg_match(self::NUMERIC_PATTERN, $amount) || (float) $amount <= 0) {
            throw new PaymentProcessingException('Digiblox withdrawal amount must be a positive number.');
        }

        // Fail CLOSED, not open. A missing key, null, '' or a non-numeric
        // string must never be treated as "no cap" — that is indistinguishable
        // from "unlimited" and this is the one guard bounding a first live run
        // against production credentials with no sandbox. Any of those is a
        // configuration error: refuse to send rather than guess.
        $capRaw = $this->config['withdrawal_max_amount'] ?? null;
        $cap = $capRaw === null ? '' : trim((string) $capRaw);

        if ($cap === '' || ! preg_match(self::NUMERIC_PATTERN, $cap)) {
            throw new PaymentProcessingException(
                'Digiblox withdrawals are enabled but no valid withdrawal_max_amount is configured. Refusing to send.',
            );
        }

        // A cap of zero blocks everything — it is not "no cap".
        if (bccomp($cap, '0', 18) <= 0) {
            throw new PaymentProcessingException(
                sprintf('Digiblox withdrawal cap is configured as %s, which blocks all withdrawals.', $cap),
            );
        }

        // Compare as strings via bcmath, not by casting to float — this is
        // money, and crypto assets carry up to 18 decimals.
        if (bccomp($amount, $cap, 18) > 0) {
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
        $status = $response->status();

        if ($status === 202) {
            $id = (string) ($response->json('id') ?? '');

            // The id is the only handle for polling status later. A 202 with
            // no id means the transfer may have been created but is now
            // untrackable — that is exactly as dangerous as a 5xx, so it gets
            // the same "do not retry" treatment, not a silent empty string.
            if ($id === '') {
                throw new PaymentProcessingException(
                    'Digiblox returned 202 Accepted with no transfer id. A withdrawal may have been created — '
                    .'do NOT retry this request. Poll transfer status (§6.2) before taking any further action.',
                );
            }

            return [
                'id' => $id,
                'status' => (string) ($response->json('status') ?? 'QUEUED'),
            ];
        }

        // The spec is explicit: on a 5xx, "the transfer may or may not have
        // been created — check its status before retrying." A caller that
        // catches broadly and retries risks a duplicate withdrawal, and there
        // is no idempotency key to save it. Make the ambiguity unmissable.
        if ($status >= 500) {
            throw new PaymentProcessingException(
                sprintf(
                    'Digiblox returned a server error (%d) for the withdrawal request. The transfer may or may '
                    .'not have been created — do NOT retry. Poll transfer status (§6.2) before taking any further '
                    .'action. Response: %s',
                    $status,
                    trim($response->body()),
                ),
            );
        }

        // A 4xx (400/401/403, per §6.1) is a clean rejection: Digiblox
        // validated the request before creating anything, so no transfer was
        // created and this is safe to correct and resend.
        throw new PaymentProcessingException(
            'Digiblox rejected the withdrawal; no transfer was created: '.trim($response->body()),
        );
    }

    /**
     * Poll a transfer.
     *
     * Note the path: creation posts to /gateway/api/v1/v3/transfers/centralized
     * but the status route has no /v3 segment. That asymmetry is in the API,
     * not a typo here.
     *
     * Because there is no idempotency key, this is also the only safe way to
     * resolve an inconclusive create: poll before you retry, never retry blind.
     *
     * @return array{status: string, mapped: \Asciisd\CashierCore\Enums\PaymentStatus, tx_hash: ?string, raw: array<string, mixed>}
     */
    public function status(string $transferId): array
    {
        $response = PspHttp::idempotent()
            ->withToken($this->client->authToken())
            ->acceptJson()
            ->get($this->baseUrl().'/gateway/api/v1/transfers/'.$transferId);

        if (! $response->successful()) {
            throw new PaymentProcessingException(
                'Digiblox transfer lookup failed: '.trim($response->body()),
            );
        }

        $transfer = (array) ($response->json('api_data.transfer') ?? []);
        $status = (string) ($transfer['status'] ?? '');

        return [
            'status' => $status,
            'mapped' => (new DigibloxAdapter)->mapTransferStatus($status),
            'tx_hash' => isset($transfer['tx_hash']) ? (string) $transfer['tx_hash'] : null,
            'raw' => $transfer,
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://app.digiblox.io'), '/');
    }
}
