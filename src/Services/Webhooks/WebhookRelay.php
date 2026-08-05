<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services\Webhooks;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Jobs\RelayWebhook;
use Illuminate\Http\Request;

/**
 * Relay callbacks that settle somebody else's deposits.
 *
 * When this application takes over a PSP callback URL that already belonged to
 * a third party — an aggregator that still routes deposits through the same
 * merchant account, say — that party can no longer see the outcome of the
 * deposits it opens. Configure `cashier-core.webhooks.relay.{driver}` with its
 * URL and every verified callback that matches no local transaction is
 * relayed there verbatim.
 *
 * Two properties make this safe to expose on a public endpoint:
 *
 * - Relaying happens only *after* the signature verifies, so the endpoint
 *   cannot be used to pump arbitrary payloads at the third party on our behalf.
 * - The decision is "we hold no transaction under this id", not "the payload
 *   claims it is theirs". An aggregator opens the downstream session under an
 *   order number it generates itself, so its callbacks carry an id we have
 *   never stored and could never match a local row on. Nothing in the payload
 *   is trusted to route it.
 */
class WebhookRelay
{
    public function __construct(private readonly ConnectionRegistry $registry) {}

    /**
     * Relay this delivery if it settles no transaction of ours and the driver
     * has a relay URL configured. A no-op otherwise.
     *
     * @param  array<string, mixed>  $payload  Parsed only to correlate; the raw
     *                                         request body is what gets relayed.
     */
    public function maybeRelay(string $driver, ?string $connection, array $payload, Request $request): void
    {
        $url = config("cashier-core.webhooks.relay.{$driver}");

        if (! is_string($url) || $url === '') {
            return;
        }

        if ($this->isOwnTransaction($driver, $this->transactionId($connection ?? $driver, $payload))) {
            return;
        }

        RelayWebhook::dispatch(
            $driver,
            $url,
            $request->getContent(),
            (string) $request->header('Content-Type', 'application/json'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transactionId(string $connection, array $payload): ?string
    {
        try {
            $provider = $this->registry->get($connection);
        } catch (PaymentProcessingException|ProcessorNotFoundException) {
            return null;
        }

        return $provider instanceof ProvidesWebhookTransactionId
            ? $provider->extractWebhookTransactionId($payload)
            : null;
    }

    /**
     * Whether this callback settles a deposit we opened with the provider
     * ourselves.
     *
     * Soft-deleted rows count as ours: a transaction the customer deleted is
     * still one we opened, and relaying its callback onward would report our
     * deposit as the third party's. This is the opposite of the processing
     * path, where SoftDeletes deliberately stays in force so a deleted
     * transaction cannot move funds.
     */
    private function isOwnTransaction(string $driver, ?string $providerTransactionId): bool
    {
        if ($providerTransactionId === null || $providerTransactionId === '') {
            // Unidentifiable deliveries are relayed. A callback we cannot
            // correlate is by definition not one we can settle, and the
            // recipient can simply ignore what is not theirs — whereas
            // swallowing it loses the only copy of somebody's payment result.
            return false;
        }

        $model = Cashier::transactionModel();

        return $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->withTrashed()
            ->where('provider', $driver)
            ->where('provider_transaction_id', $providerTransactionId)
            ->exists();
    }
}
