<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentClient;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Illuminate\Console\Command;

/**
 * Recover Heropayment repeat payments the webhook pipeline never recorded.
 *
 * Before repeat payments had transactions of their own, a second payment to a
 * customer's static deposit address was applied to the original transaction
 * and dropped. This lists every deposit on the account, finds the repeat
 * payments, and applies each one through the same pipeline a callback takes —
 * so a recovered payment is created, settled and credited exactly as a live
 * one would be. Re-running is safe: an applied payment is ignored as a
 * duplicate.
 */
class HeropaymentRepeatsCommand extends Command
{
    protected $signature = 'cashier:heropayment-repeats
        {--connection=heropayment : The Heropayment connection to read}
        {--since= : Only payments created on or after this date (YYYY-MM-DD)}
        {--dry-run : List the repeat payments without recording anything}';

    protected $description = 'Find Heropayment repeat payments that have no transaction and record them';

    private const PAGE_SIZE = 100;

    public function handle(ConnectionRegistry $registry, WebhookProcessor $processor): int
    {
        $connection = (string) $this->option('connection');
        $config = Connections::get($connection);

        if (($config['driver'] ?? null) !== 'heropayment') {
            $this->components->error("Connection [{$connection}] is not a Heropayment connection.");

            return self::FAILURE;
        }

        /** @var HeropaymentProvider $provider */
        $provider = $registry->get($connection);
        $client = HeropaymentClient::fromConfig($config);
        $dryRun = (bool) $this->option('dry-run');
        $model = Cashier::transactionModel();

        $rows = [];
        $offset = 0;

        do {
            $page = $client->listPayments([
                'transactionType' => 'deposit',
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'dateFrom' => $this->option('since'),
            ]);

            if ($page === null) {
                $this->components->error('Heropayment refused the payment list request.');

                return self::FAILURE;
            }

            foreach ($page['items'] as $payment) {
                $orderId = $provider->extractWebhookTransactionId($payment);

                if ($orderId === null) {
                    continue;
                }

                $original = $model::query()
                    ->withoutGlobalScopes($model::cashierBypassedScopes())
                    ->where('provider', 'heropayment')
                    ->where('provider_transaction_id', $orderId)
                    ->first();

                if (! $original instanceof Transaction) {
                    continue;
                }

                $repeatId = $provider->repeatPaymentId($payment, $original);

                if ($repeatId === null) {
                    continue;
                }

                $existing = $model::query()
                    ->withoutGlobalScopes($model::cashierBypassedScopes())
                    ->where('provider', 'heropayment')
                    ->where('provider_transaction_id', $repeatId)
                    ->first();

                $rows[] = [
                    $repeatId,
                    (string) ($payment['status'] ?? ''),
                    (string) ($payment['paidAmount'] ?? ''),
                    (string) ($payment['payCurrency'] ?? ''),
                    (string) ($payment['createdAt'] ?? ''),
                    "#{$original->id}",
                    $existing ? "#{$existing->id} {$existing->status->value}" : 'none',
                ];

                if (! $dryRun) {
                    $processor->process(
                        'heropayment',
                        $orderId,
                        $provider->parseWebhook($payment),
                        fn (Transaction $original): ?string => $provider->repeatPaymentId($payment, $original),
                        'sync',
                    );
                }
            }

            $offset += self::PAGE_SIZE;
        } while (count($page['items']) === self::PAGE_SIZE);

        if ($rows === []) {
            $this->components->info('No repeat payments found.');

            return self::SUCCESS;
        }

        $this->table(['Repeat payment', 'Status', 'Paid', 'Currency', 'Created', 'Original', 'Transaction before run'], $rows);

        $this->components->info($dryRun
            ? sprintf('%d repeat payment(s) found. Nothing was recorded (--dry-run).', count($rows))
            : sprintf('%d repeat payment(s) applied.', count($rows)));

        return self::SUCCESS;
    }
}
