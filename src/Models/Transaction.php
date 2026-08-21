<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Models;

use Asciisd\CashierCore\Casts\EncryptedArray;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\SettlementMode;
use Asciisd\CashierCore\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Base transaction model for the payment engine.
 *
 * Host applications extend this with their own concerns (user scoping, CRM
 * sync, admin panel traits) and point `cashier-core.models.transaction` at the
 * subclass. The engine reads and writes only the columns in the package
 * migration stub; anything else belongs to the host.
 *
 * `provider` is deliberately a plain string here — hosts may cast it to their
 * own display enum in the subclass. `mt5_ticket_number` keeps its historical
 * name; read it as "ledger ticket".
 */
class Transaction extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const SHORT_REFERENCE_LENGTH = 8;

    protected $fillable = [
        'user_id', 'trading_account_id', 'transfer_transaction_id',
        'provider', 'connection', 'provider_transaction_id', 'payment_processor', 'type', 'is_ftd', 'status',
        'amount', 'currency', 'conversion_rate', 'charge_currency', 'charge_amount',
        'fees', 'vendor_fees', 'fixed_vendor_fees',
        'charged_amount', 'requested_amount', 'settled_amount',
        'psp_fee_amount', 'markup_amount', 'settlement_mode',
        'description', 'metadata', 'provider_payload',
        'withdrawal_method', 'withdrawal_details', 'withdrawal_reason',
        'payment_method_type', 'payment_method_brand', 'payment_method_last_four', 'payment_method_display_name',
        'deposit_proof_path', 'mt5_ticket_number', 'executed_at',
        'error_code', 'error_message', 'processed_at', 'failed_at',
    ];

    protected $hidden = [
        'provider_payload',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('cashier-core.database.tables.transactions', 'transactions');
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['reference'];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'type' => TransactionType::class,
            'settlement_mode' => SettlementMode::class,
            'is_ftd' => 'boolean',
            'amount' => 'decimal:2',
            'conversion_rate' => 'decimal:8',
            'charge_amount' => 'decimal:4',
            'fees' => 'decimal:2',
            'vendor_fees' => 'decimal:2',
            'fixed_vendor_fees' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'psp_fee_amount' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'metadata' => 'array',
            'provider_payload' => $this->encryptedCast('encrypt_provider_payload'),
            'withdrawal_details' => $this->encryptedCast('encrypt_withdrawal_details'),
            'executed_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Global scopes the webhook/sync pipeline may bypass when correlating a
     * transaction without an authenticated user (host tenant scopes, team
     * scopes). NEVER include SoftDeletes here — a deleted transaction must not
     * change status or move funds.
     *
     * @return list<class-string|string>
     */
    public static function cashierBypassedScopes(): array
    {
        return [];
    }

    public function shortReference(): string
    {
        return Str::upper(mb_substr((string) $this->reference, -self::SHORT_REFERENCE_LENGTH));
    }

    /**
     * The standardized ledger comment for this transaction.
     *
     * For transfers, pass the counterpart account to produce "transfer from/to
     * {account}". Deposits auto-detect first-time (FTD) vs subsequent (DEP).
     * Capped at 32 characters (MT5's comment limit — the historical baseline).
     */
    public function mt5Comment(?int $counterpartLogin = null): string
    {
        if ($this->type->isTransfer() && $counterpartLogin) {
            return mb_substr("{$this->type->mt5Prefix()} {$counterpartLogin}", 0, 32);
        }

        $prefix = $this->isFirstDeposit() ? 'FTD' : $this->type->mt5Prefix();

        return mb_substr("{$prefix} #{$this->reference}", 0, 32);
    }

    public function mt5CancelComment(): string
    {
        return mb_substr("CXL #{$this->reference}", 0, 32);
    }

    public function isCancelable(): bool
    {
        return $this->type === TransactionType::Withdrawal
            && in_array($this->status, [PaymentStatus::Pending, PaymentStatus::Processing], true);
    }

    /**
     * Determine if this is the customer's first successful deposit.
     */
    public function isFirstDeposit(): bool
    {
        if ($this->type !== TransactionType::Deposit) {
            return false;
        }

        return ! static::query()
            ->withoutGlobalScopes(static::cashierBypassedScopes())
            ->where('user_id', $this->user_id)
            ->where('type', TransactionType::Deposit)
            ->where('status', PaymentStatus::Succeeded)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    public function hasFeeSnapshot(): bool
    {
        return $this->psp_fee_amount !== null || $this->markup_amount !== null;
    }

    public function hasPaymentMethodSnapshot(): bool
    {
        return $this->payment_method_type !== null
            || $this->payment_method_brand !== null
            || $this->payment_method_last_four !== null
            || $this->payment_method_display_name !== null;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function isCanceled(): bool
    {
        return $this->status->isCanceled();
    }

    /**
     * Encryption at rest for payloads that may carry PSP-supplied PII (PCI DSS
     * 3.4/3.5). The cast reads the flag per write and accepts legacy cleartext
     * on read, so a host can flip encryption on before `cashier:encrypt-historical`
     * has finished rewriting its back catalogue.
     */
    private function encryptedCast(string $flag): string
    {
        return EncryptedArray::class.':'.$flag;
    }
}
