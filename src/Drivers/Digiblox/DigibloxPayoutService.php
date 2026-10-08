<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Digiblox;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Illuminate\Http\Client\ConnectionException;

/**
 * Crypto withdrawal payouts over Digiblox's Centralized Transfer API.
 *
 * Three API facts shape it. There is no withdrawal webhook, so the host polls
 * lookup(). A transfer can only be looked up by Digiblox's own id, so a send
 * whose outcome is unknown cannot be found by our order id — lookup() throws
 * rather than return null, and an admin resolves it by hand. And there is no
 * balance endpoint, so preflight checks only what can be checked locally;
 * "Insufficent funds" arrives as a 400 that creates nothing.
 *
 * The amount is sent as-is in the payout asset's units, which is why every
 * `payout_assets` entry names the fiat it is pegged to and a withdrawal in any
 * other currency is refused.
 */
final class DigibloxPayoutService implements SendsPayouts
{
    private const FAILED = ['FAILED', 'REJECTED', 'DROPPED', 'EXPIRED'];

    private DigibloxTransferService $transfers;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config, ?DigibloxTransferService $transfers = null)
    {
        $this->transfers = $transfers ?? new DigibloxTransferService($config);
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        $this->assetFor($request);
        $this->transfers->assertSendable($request->amount, $request->payoutAddress);

        return new PayoutPreflight(
            ok: true,
            message: 'Digiblox has no balance endpoint; funds are checked when the transfer is created.',
        );
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        $asset = $this->assetFor($request);

        $created = $this->transfers->create(
            $request->amount,
            $request->payoutAddress,
            $asset['network'],
            $asset['asset'],
            $request->externalOrderId,
        );

        return new PayoutReceipt(
            reference: $created['id'],
            rawStatus: $created['status'],
            state: PayoutState::Sent,
            payload: $created,
        );
    }

    public function lookup(string $externalOrderId, ?string $reference = null): ?PayoutReceipt
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            throw new PayoutOutcomeUnknownException(
                "Digiblox cannot look a transfer up by order id {$externalOrderId}. "
                .'Link the Digiblox transfer id, or confirm the payout was never sent.',
            );
        }

        try {
            $transfer = $this->transfers->status($reference);
        } catch (ConnectionException|PaymentProcessingException $e) {
            throw new PayoutOutcomeUnknownException("Digiblox transfer lookup failed for {$reference}: {$e->getMessage()}", previous: $e);
        }

        $raw = $transfer['raw'];
        $note = $raw['user_note'] ?? null;

        // Every transfer we create carries the order id as its note. A
        // different note means the id belongs to another payout.
        if (is_string($note) && $note !== '' && $note !== $externalOrderId) {
            throw new PayoutOutcomeUnknownException(
                "Digiblox transfer {$reference} belongs to order {$note}, not {$externalOrderId}.",
            );
        }

        $status = strtoupper($transfer['status']);

        $state = match (true) {
            $status === 'CONFIRMED' => PayoutState::Paid,
            in_array($status, self::FAILED, true) => PayoutState::Failed,
            default => PayoutState::Sent,
        };

        $error = null;

        if ($state === PayoutState::Failed) {
            $detail = $raw['error_log'] ?? null;
            $error = "Digiblox transfer {$status}".($detail === null ? '' : ': '.(is_string($detail) ? $detail : json_encode($detail)));
        }

        return new PayoutReceipt(
            reference: $reference,
            rawStatus: $transfer['status'],
            state: $state,
            payload: array_merge($raw, ['outcomeHash' => $transfer['tx_hash']]),
            error: $error,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        throw new PaymentProcessingException('Digiblox sends no withdrawal callbacks; payouts are polled.');
    }

    /**
     * @return array{asset: string, network: string, peg: string}
     */
    private function assetFor(PayoutRequest $request): array
    {
        $ticker = strtolower($request->payoutCurrency);
        $entry = $this->config['payout_assets'][$ticker] ?? null;

        if (! is_array($entry) || empty($entry['asset']) || empty($entry['network']) || empty($entry['peg'])) {
            throw new PaymentProcessingException("Digiblox has no payout asset configured for {$ticker}.");
        }

        if (strtoupper((string) $entry['peg']) !== strtoupper($request->currency)) {
            throw new PaymentProcessingException(sprintf(
                'Digiblox sends the amount in %s, which is pegged to %s; a %s withdrawal cannot be paid with it.',
                $entry['asset'],
                strtoupper((string) $entry['peg']),
                strtoupper($request->currency),
            ));
        }

        return [
            'asset' => strtoupper((string) $entry['asset']),
            'network' => strtoupper((string) $entry['network']),
            'peg' => strtoupper((string) $entry['peg']),
        ];
    }
}
