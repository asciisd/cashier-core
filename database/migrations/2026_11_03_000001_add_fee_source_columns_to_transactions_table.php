<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a deposit's PSP fee was final at checkout or settled against the
 * PSP's reported figure, and — for the latter — the estimate it was charged
 * at, kept beside the settled `psp_fee_amount` for reconciliation.
 *
 * Guarded like the create migration: hosts that own their transactions table
 * and already added these columns are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'fee_source')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->string('fee_source')->nullable();                     // FeeSource
            $table->decimal('estimated_psp_fee_amount', 16, 2)->nullable();
        });
    }

    public function down(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasColumn($table, 'fee_source')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn(['fee_source', 'estimated_psp_fee_amount']);
        });
    }
};
