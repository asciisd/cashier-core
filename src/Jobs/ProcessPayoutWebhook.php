<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Jobs;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A verified payout (withdrawal) callback.
 *
 * Kept apart from ProcessPaymentProviderWebhook on purpose: the deposit
 * pipeline would move a failed payout to Failed and, on success, reconcile
 * `amount` to the crypto deducted from our balance.
 */
class ProcessPayoutWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Re-queues while a send settles count as attempts, so allow more than
     * the deposit job does.
     */
    public int $tries = 10;

    /** @see ProcessPaymentProviderWebhook::$uniqueFor */
    public int $uniqueFor = 120;

    /** How long to wait for an in-flight send to settle before retrying. */
    private const SETTLE_DELAY_SECONDS = 30;

    /** @see ProcessPaymentProviderWebhook::$connectionName */
    public readonly ?string $connectionName;

    /**
     * @param  array<string, mixed>  $payload
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
        return 'payout:'.$this->driver.':'.sha1(json_encode($this->payload) ?: serialize($this->payload));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(ConnectionRegistry $registry, WithdrawalWorkflow $workflow, TransferClaim $claims): void
    {
        $provider = $registry->get($this->connectionName ?? $this->driver);

        $orderId = $provider instanceof ProvidesWebhookTransactionId
            ? $provider->extractWebhookTransactionId($this->payload)
            : null;

        if (! $provider instanceof SendsPayouts || $orderId === null || $orderId === '') {
            PaymentLogger::providerWebhookMissingTransactionId($this->driver);

            return;
        }

        $model = Cashier::transactionModel();

        $transaction = $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->where('provider', $this->driver)
            ->where('provider_transaction_id', $orderId)
            ->where('type', TransactionType::Withdrawal)
            ->first();

        if (! $transaction) {
            // Includes late callbacks for an earlier attempt's order id.
            PaymentLogger::providerWebhookTransactionNotFound($this->driver, $orderId);

            return;
        }

        // Heropayments can call back before sendPayout() has saved its result.
        // An unknown payout's claim is held on purpose and the callback is
        // exactly what resolves it, so only a live send is waited for.
        if ($transaction->payout_state !== PayoutState::Unknown && $claims->hasFreshClaim($transaction)) {
            $this->release(self::SETTLE_DELAY_SECONDS);

            return;
        }

        $workflow->applyPayoutUpdate($transaction, $provider->parsePayoutWebhook($this->payload), 'webhook');
    }
}
