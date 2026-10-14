<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of admin money actions (PCI DSS 10.2.1): actor, action,
 * transaction, before/after status, context, source IP. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.admin_actions', 'cashier_admin_actions');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('actor_id');
            $table->string('actor_guard')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('transaction_id')->index();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cashier-core.database.tables.admin_actions', 'cashier_admin_actions'));
    }
};
