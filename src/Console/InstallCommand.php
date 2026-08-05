<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'cashier:install
        {--migrate : Run the database migrations after publishing}';

    protected $description = 'Publish the cashier-core configuration and print the integration checklist';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'cashier-core-config']);

        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        $this->components->info('cashier-core installed.');

        $this->components->bulletList([
            'Define your named connections in config/cashier-core.php (one per PSP account).',
            'Implement '.\Asciisd\CashierCore\Contracts\CustomerContract::class.' on your user model and set cashier-core.models.customer.',
            'Bind '.\Asciisd\CashierCore\Contracts\FundsLedger::class.' to move real funds — the default NullLedger refuses every movement.',
            'Bind '.\Asciisd\CashierCore\Contracts\ResolvesFundingAccount::class.' if deposits target accounts by reference.',
            'Listen for the package events (DepositSucceeded, WithdrawalRequested, ...) — the package sends no mail itself.',
            'Run a dedicated queue worker for the "'.config('cashier-core.queue.queue', 'payments').'" queue.',
            'Run `php artisan cashier:check` to validate the setup.',
        ]);

        return self::SUCCESS;
    }
}
