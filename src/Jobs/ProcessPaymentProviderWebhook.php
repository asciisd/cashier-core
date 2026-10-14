<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Jobs;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentProviderWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A provider may need to reach its own API to resolve what a callback
     * means — Sticpay's transaction callback carries no status, so the outcome
     * comes from a lookup that can fail transiently. Parsing throws in that
     * case rather than guessing, so the job has to be retried; on the default
     * single attempt a momentary blip would strand a paid deposit as Pending.
     */
    public int $tries = 5;

    /**
     * PSPs redeliver a webhook until it is acknowledged, so identical copies
     * of the same callback can be dispatched seconds apart. The lock is held
     * for the job's lifetime (including retries), collapsing those copies.
     *
     * The id hashes the whole payload rather than the correlation id on
     * purpose: two *different* callbacks for the same transaction (a `pending`
     * followed by a `done`) are distinct status transitions and must both be
     * processed — keying on the transaction id would silently drop the second
     * while the first is still queued.
     */
    public int $uniqueFor = 120;

    /**
     * The payment connection whose signature verified this delivery.
     *
     * Named `connectionName` rather than `connection` because Queueable
     * already owns `$connection` for the queue backend — promoting the
     * constructor parameter under that name is a fatal trait property
     * conflict.
     */
    public readonly ?string $connectionName;

    /**
     * @param  string  $driver  the driver string persisted to transactions.provider
     * @param  array<string, mixed>  $payload
     * @param  string|null  $connection  the connection whose signature verified this
     *                                   delivery — the provider is resolved from it so
     *                                   multi-account drivers parse under the right
     *                                   credentials; null falls back to the
     *                                   driver-named connection
     */
    public function __construct(
        public readonly string $driver,
        public readonly array $payload,
        ?string $connection = null,
    ) {
        $this->connectionName = $connection;

        $this->onConnection(config('cashier-core.queue.connection'));
        $this->onQueue(config('cashier-core.queue.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return $this->driver.':'.sha1(json_encode($this->payload) ?: serialize($this->payload));
    }

    /**
     * Backoff between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(ConnectionRegistry $registry, WebhookProcessor $processor): void
    {
        $providerInstance = $registry->get($this->connectionName ?? $this->driver);

        $providerTransactionId = $providerInstance instanceof ProvidesWebhookTransactionId
            ? $providerInstance->extractWebhookTransactionId($this->payload)
            : null;

        $update = $providerInstance->parseWebhook($this->payload);

        $processor->process($this->driver, $providerTransactionId, $update);
    }
}
