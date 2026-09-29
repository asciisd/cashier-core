<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PSP side of a withdrawal payout. `payout_state` is indexed so ops can
 * list payouts that failed or whose outcome is unknown; `status` stays the
 * customer-facing lifecycle.
 *
 * Guarded like the create migration: hosts that own their transactions table
 * and already added these columns are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'payout_state')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->string('payout_state')->nullable()->index();          // PayoutState
            $table->string('payout_reference')->nullable();               // PSP payment id
        });
    }

    public function down(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasColumn($table, 'payout_state')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropIndex(['payout_state']);
            $table->dropColumn(['payout_state', 'payout_reference']);
        });
    }
};
