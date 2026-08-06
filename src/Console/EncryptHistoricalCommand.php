<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Console;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\PayloadSanitizer;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * One-off backfill for hosts that carried cleartext payloads into the engine.
 *
 * `provider_payload` and `withdrawal_details` are encrypted at rest (PCI DSS
 * 3.4/3.5), but a host adopting the engine on top of an existing table has
 * years of rows written before the cast existed. This rewrites them in place:
 * decode, sanitize (payloads only), encrypt, write back.
 *
 * Safe to interrupt and safe to re-run — a row that is already ciphertext is
 * detected and skipped, so the work resumes where it stopped. Rows are read in
 * key order in chunks and written through the query builder, so no observer
 * fires, `updated_at` does not move, and no single long transaction is held.
 *
 * Encryption follows the config flags: a column whose flag is off is reported
 * and left alone. Run it after the flags are on — `EncryptedArray` reads both
 * shapes, so a half-finished backfill serves traffic correctly throughout.
 */
class EncryptHistoricalCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Columns this command owns, mapped to the security flag that governs them.
     */
    private const COLUMNS = [
        'provider_payload' => 'encrypt_provider_payload',
        'withdrawal_details' => 'encrypt_withdrawal_details',
    ];

    protected $signature = 'cashier:encrypt-historical
        {--dry-run : Report what would be rewritten without changing anything}
        {--chunk=200 : Rows per pass}
        {--column=* : Limit to specific columns (provider_payload, withdrawal_details)}
        {--skip-sanitize : Encrypt payloads verbatim instead of running them through the storage sanitizer}
        {--force : Skip the production confirmation}';

    protected $description = 'Encrypt (and sanitize) transaction payloads written before encryption was enabled';

    public function handle(PayloadSanitizer $sanitizer): int
    {
        $columns = $this->resolveColumns();

        if ($columns === []) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sanitize = ! (bool) $this->option('skip-sanitize');

        if (! $dryRun && ! $this->confirmToProceed('Rewriting historical payment payloads')) {
            return self::FAILURE;
        }

        $model = Cashier::transactionModel();
        $chunk = max(1, (int) $this->option('chunk'));
        $totals = [];

        foreach ($columns as $column) {
            $counters = ['rewritten' => 0, 'already' => 0, 'failed' => 0];

            $model::query()
                ->withoutGlobalScopes()
                ->whereNotNull($column)
                ->select(['id', 'provider', $column])
                ->chunkById($chunk, function ($rows) use ($column, $model, $sanitizer, $sanitize, $dryRun, &$counters) {
                    foreach ($rows as $row) {
                        $this->rewriteRow($model, $row, $column, $sanitizer, $sanitize, $dryRun, $counters);
                    }
                });

            $totals[$column] = $counters;
        }

        $this->report($totals, $dryRun);

        if (! $dryRun) {
            PaymentLogger::historicalPayloadsEncrypted($totals, $sanitize);
        }

        return array_sum(array_column($totals, 'failed')) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array{rewritten: int, already: int, failed: int}  $counters
     */
    private function rewriteRow(
        string $model,
        object $row,
        string $column,
        PayloadSanitizer $sanitizer,
        bool $sanitize,
        bool $dryRun,
        array &$counters,
    ): void {
        // Read past the cast: this must work identically whether the flag is on
        // (cast returns decrypted arrays) or off (cast returns decoded JSON).
        $raw = $row->getRawOriginal($column);

        if ($raw === null || $raw === '') {
            return;
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            // Not JSON: either already ciphertext (the resume case) or damaged.
            try {
                Crypt::decryptString((string) $raw);
                $counters['already']++;
            } catch (DecryptException) {
                $counters['failed']++;
                $this->components->warn("Transaction [{$row->id}] {$column} is neither JSON nor decryptable — left untouched.");
            }

            return;
        }

        if ($sanitize && $column === 'provider_payload') {
            $decoded = $sanitizer->forStorage((string) $row->provider, $decoded);
        }

        if ($dryRun) {
            $counters['rewritten']++;

            return;
        }

        try {
            $model::query()
                ->withoutGlobalScopes()
                ->whereKey($row->id)
                ->toBase()
                ->update([$column => Crypt::encryptString(json_encode($decoded))]);

            $counters['rewritten']++;
        } catch (Throwable $e) {
            $counters['failed']++;
            $this->components->warn("Transaction [{$row->id}] {$column} failed: {$e->getMessage()}");
        }
    }

    /**
     * @return list<string>
     */
    private function resolveColumns(): array
    {
        $requested = (array) $this->option('column');
        $unknown = array_diff($requested, array_keys(self::COLUMNS));

        if ($unknown !== []) {
            $this->components->error('Unknown column(s): '.implode(', ', $unknown).'. Known: '.implode(', ', array_keys(self::COLUMNS)).'.');

            return [];
        }

        $columns = [];

        foreach (self::COLUMNS as $column => $flag) {
            if ($requested !== [] && ! in_array($column, $requested, true)) {
                continue;
            }

            if (! config("cashier-core.security.{$flag}", true)) {
                $this->components->warn("security.{$flag} is off — skipping {$column}. Turn the flag on first, then re-run.");

                continue;
            }

            $columns[] = $column;
        }

        if ($columns === []) {
            $this->components->error('No columns to process — both encryption flags are off.');
        }

        return $columns;
    }

    /**
     * @param  array<string, array{rewritten: int, already: int, failed: int}>  $totals
     */
    private function report(array $totals, bool $dryRun): void
    {
        $verb = $dryRun ? 'would encrypt' : 'encrypted';

        foreach ($totals as $column => $counters) {
            $this->components->info(sprintf(
                '%s: %s %d row(s); %d already encrypted; %d failed.',
                $column,
                $verb,
                $counters['rewritten'],
                $counters['already'],
                $counters['failed'],
            ));
        }

        if ($dryRun) {
            $this->components->warn('Dry run — nothing was written.');
        }
    }
}
