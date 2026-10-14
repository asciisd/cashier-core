<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * A validated withdrawal submission, as the workflow needs it.
 *
 * The host validates method-specific requirements (bank account on file,
 * approved wallet, balance) and prepares `details` before constructing this —
 * the workflow owns concurrency, persistence, the optional legacy debit, and
 * events, not the host's business rules.
 */
readonly class WithdrawalRequestData
{
    /**
     * @param  string  $driver  the driver string persisted to transactions.provider
     * @param  string  $method  host's withdrawal method slug (bank_transfer, crypto, …)
     * @param  int|string|null  $ledgerAccount  the ledger account to debit (stored in
     *                                          metadata as `ledger_account`)
     * @param  array<string, mixed>  $details  method details (encrypted at rest)
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public float $amount,
        public string $currency,
        public string $driver,
        public string $method,
        public int|string|null $ledgerAccount = null,
        public ?int $tradingAccountId = null,
        public array $details = [],
        public ?string $reason = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
