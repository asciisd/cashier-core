<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    protected $signature = 'cashier:publish
        {--config : Publish the configuration file}
        {--migrations : Publish the migrations for customization}
        {--force : Overwrite any existing files}';

    protected $description = 'Publish cashier-core assets';

    public function handle(): int
    {
        $tags = array_keys(array_filter([
            'cashier-core-config' => $this->option('config'),
            'cashier-core-migrations' => $this->option('migrations'),
        ]));

        // No flag means everything — mirroring vendor:publish --provider.
        if ($tags === []) {
            $tags = ['cashier-core-config', 'cashier-core-migrations'];
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => (bool) $this->option('force'),
            ]);
        }

        return self::SUCCESS;
    }
}
