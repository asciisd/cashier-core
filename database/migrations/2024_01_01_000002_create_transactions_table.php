<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The engine's transaction table — the authoritative column reference for any
 * host adopting the package. Guarded with hasTable so hosts that already own a
 * transactions table (adopting v2 over an existing schema) are untouched;
 * their table must provide these columns.
 *
 * The unique (provider, provider_transaction_id) index is the webhook
 * correlation guarantee: a provider update can never resolve to two rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('trading_account_id')->nullable()->index();
            $table->unsignedBigInteger('transfer_transaction_id')->nullable();

            $table->string('provider');                                  // driver string
            $table->string('connection')->nullable()->index();           // named PSP account
            $table->string('provider_transaction_id')->nullable();
            $table->string('payment_processor')->nullable();

            $table->string('type');                                      // TransactionType
            $table->boolean('is_ftd')->default(false);
            $table->string('status');                                    // PaymentStatus

            $table->decimal('amount', 16, 2);
            $table->string('currency', 3);
            $table->decimal('conversion_rate', 16, 8)->nullable();

            // The PSP leg, when the provider cannot be sent the account's
            // currency. Null on every same-currency charge, which is almost
            // all of them.
            $table->char('charge_currency', 3)->nullable();
            // decimal(20,4), not (16,2): KWD, BHD, OMR and JOD carry three
            // minor units and a two-decimal column silently truncates them.
            $table->decimal('charge_amount', 20, 4)->nullable();
            $table->decimal('fees', 16, 2)->default(0);
            $table->decimal('vendor_fees', 16, 2)->default(0);
            $table->decimal('fixed_vendor_fees', 16, 2)->default(0);

            // Fee snapshot at charge time (FeeBreakdown)
            $table->decimal('charged_amount', 16, 2)->nullable();
            $table->decimal('requested_amount', 16, 2)->nullable();
            $table->decimal('settled_amount', 16, 2)->nullable();
            $table->decimal('psp_fee_amount', 16, 2)->nullable();
            $table->decimal('markup_amount', 16, 2)->nullable();
            $table->string('settlement_mode')->nullable();               // SettlementMode

            $table->string('description')->nullable();
            $table->text('metadata')->nullable();
            $table->text('provider_payload')->nullable();                // text: may hold encrypted JSON

            $table->string('withdrawal_method')->nullable();
            $table->text('withdrawal_details')->nullable();              // text: may hold encrypted JSON
            $table->string('withdrawal_reason')->nullable();

            $table->string('payment_method_type')->nullable();
            $table->string('payment_method_brand')->nullable();
            $table->string('payment_method_last_four', 4)->nullable();
            $table->string('payment_method_display_name')->nullable();

            $table->string('deposit_proof_path')->nullable();
            $table->string('mt5_ticket_number')->nullable()->index();    // ledger ticket
            $table->timestamp('executed_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('provider');
            $table->unique(['provider', 'provider_transaction_id'], 'transactions_provider_txid_unique');
            $table->index('status');
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cashier-core.database.tables.transactions', 'transactions'));
    }
};
