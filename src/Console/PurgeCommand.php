<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Data-retention enforcement (PCI DSS 3.2.1/3.3): PSP payloads are needed for
 * reconciliation and dispute windows, not forever. `provider_payload` is
 * cleared on transactions past the retention window — the transaction row and
 * its financial columns stay — and expired replay-guard rows are deleted.
 */
class PurgeCommand extends Command
{
    protected $signature = 'cashier:purge
        {--dry-run : Report what would be purged without changing anything}
        {--chunk=500 : Rows per update chunk}';

    protected $description = 'Enforce the configured retention on provider payloads and webhook events';

    public function handle(): int
    {
        $payloadDays = (int) config('cashier-core.security.retention.provider_payload_days', 180);
        $eventDays = (int) config('cashier-core.security.retention.webhook_events_days', 30);
        $dryRun = (bool) $this->option('dry-run');

        $model = Cashier::transactionModel();

        $payloadQuery = $model::query()
            ->withoutGlobalScopes()
            ->whereNotNull('provider_payload')
            ->where('created_at', '<', now()->subDays($payloadDays));

        $eventQuery = WebhookEvent::query()
            ->where('received_at', '<', now()->subDays($eventDays));

        if ($dryRun) {
            $this->components->info(sprintf(
                'Would clear provider_payload on %d transaction(s) older than %d days and delete %d webhook event(s) older than %d days.',
                $payloadQuery->count(),
                $payloadDays,
                $eventQuery->count(),
                $eventDays,
            ));

            return self::SUCCESS;
        }

        $payloadsCleared = 0;

        // Chunked by id so a large backlog cannot hold one long transaction;
        // update goes through Eloquent per chunk, not per row.
        $payloadQuery->clone()
            ->select(['id'])
            ->chunkById((int) $this->option('chunk'), function ($transactions) use ($model, &$payloadsCleared) {
                $payloadsCleared += $model::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $transactions->pluck('id'))
                    ->update(['provider_payload' => null]);
            });

        $eventsDeleted = $eventQuery->delete();

        PaymentLogger::retentionPurged($payloadsCleared, $eventsDeleted, $payloadDays, $eventDays);

        $this->components->info(sprintf(
            'Cleared provider_payload on %d transaction(s); deleted %d webhook event(s).',
            $payloadsCleared,
            $eventsDeleted,
        ));

        return self::SUCCESS;
    }
}
