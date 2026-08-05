<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Support\NullLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * The doctor: everything that must be true before this install can move
 * money, checked in one place. FAILs exit non-zero for CI; WARNs report
 * postures that are legal in development and dangerous in production.
 */
class CheckCommand extends Command
{
    protected $signature = 'cashier:check';

    protected $description = 'Validate the cashier-core configuration, connections and security posture';

    private bool $failed = false;

    public function handle(ConnectionRegistry $registry): int
    {
        $this->checkConnections($registry);
        $this->checkDefaultConnection();
        $this->checkSignatureVerification();
        $this->checkEncryption();
        $this->checkUniqueIndex();
        $this->checkLedger();
        $this->checkCustomerModel();
        $this->checkQueue();

        $this->newLine();

        if ($this->failed) {
            $this->components->error('cashier:check found problems that must be fixed before taking payments.');

            return self::FAILURE;
        }

        $this->components->info('cashier:check passed.');

        return self::SUCCESS;
    }

    private function checkConnections(ConnectionRegistry $registry): void
    {
        $names = Connections::names();

        if ($names === []) {
            $this->postureWarn('No connections configured — define at least one in cashier-core.connections.');

            return;
        }

        foreach ($names as $name) {
            try {
                $provider = $registry->get($name);
                $driver = Connections::driverFor($name);

                $this->pass("Connection [{$name}] resolves to ".$provider::class." (driver: {$driver})");
            } catch (\Throwable $e) {
                $this->checkFail("Connection [{$name}] does not resolve: {$e->getMessage()}");
            }
        }
    }

    private function checkDefaultConnection(): void
    {
        $default = (string) (config('cashier-core.default_connection') ?? '');

        if ($default === '' || ! Connections::exists($default)) {
            $this->checkFail("Default connection [{$default}] is not a configured connection.");

            return;
        }

        $this->pass("Default connection [{$default}] exists.");
    }

    private function checkSignatureVerification(): void
    {
        if (config('cashier-core.webhooks.verify_signature', true)) {
            $this->pass('Webhook signature verification is enabled.');

            return;
        }

        // In production the controllers refuse to honor the flag, so this is a
        // posture warning outside production and a failure inside it.
        if (app()->environment('production')) {
            $this->checkFail('Webhook signature verification is disabled in production — the controllers will refuse every delivery.');
        } else {
            $this->postureWarn('Webhook signature verification is disabled (development only — production enforces it regardless).');
        }
    }

    private function checkEncryption(): void
    {
        foreach (['encrypt_provider_payload', 'encrypt_withdrawal_details'] as $flag) {
            if (config("cashier-core.security.{$flag}", true)) {
                $this->pass("security.{$flag} is on.");
            } else {
                $this->postureWarn("security.{$flag} is off — PSP payload PII will be stored in cleartext (PCI DSS 3.4/3.5).");
            }
        }
    }

    private function checkUniqueIndex(): void
    {
        $model = Cashier::transactionModel();
        $table = (new $model)->getTable();

        try {
            if (! Schema::hasTable($table)) {
                $this->checkFail("Transactions table [{$table}] does not exist — run the migrations.");

                return;
            }

            $unique = collect(Schema::getIndexes($table))->first(
                fn (array $index) => ($index['unique'] ?? false)
                    && array_map('strtolower', $index['columns']) === ['provider', 'provider_transaction_id'],
            );

            $unique === null
                ? $this->checkFail("Transactions table [{$table}] has no unique (provider, provider_transaction_id) index — duplicate webhook correlations are possible.")
                : $this->pass('Unique (provider, provider_transaction_id) index present.');
        } catch (\Throwable $e) {
            $this->postureWarn("Could not inspect indexes on [{$table}]: {$e->getMessage()}");
        }
    }

    private function checkLedger(): void
    {
        $ledger = app(FundsLedger::class);

        if ($ledger instanceof NullLedger) {
            $this->postureWarn('FundsLedger is the NullLedger — every credit/debit will be refused. Bind your ledger to move real funds.');
        } else {
            $this->pass('FundsLedger bound to '.$ledger::class.'.');
        }
    }

    private function checkCustomerModel(): void
    {
        $customer = Cashier::customerModel();

        if ($customer === null) {
            $this->postureWarn('cashier-core.models.customer is not set.');

            return;
        }

        if (! is_a($customer, CustomerContract::class, true)) {
            $this->checkFail("Customer model [{$customer}] does not implement ".CustomerContract::class.'.');

            return;
        }

        $this->pass("Customer model [{$customer}] implements CustomerContract.");
    }

    private function checkQueue(): void
    {
        $queue = (string) config('cashier-core.queue.queue', 'payments');

        $this->pass("Webhook jobs run on the [{$queue}] queue — ensure a dedicated worker consumes it.");

        if (config('queue.default') === 'sync' && app()->environment('production')) {
            $this->postureWarn('queue.default is sync in production — webhook processing will block the PSP request.');
        }
    }

    private function pass(string $message): void
    {
        $this->components->twoColumnDetail($message, '<fg=green>PASS</>');
    }

    private function postureWarn(string $message): void
    {
        $this->components->twoColumnDetail($message, '<fg=yellow>WARN</>');
    }

    private function checkFail(string $message): void
    {
        $this->failed = true;

        $this->components->twoColumnDetail($message, '<fg=red>FAIL</>');
    }
}
