<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund records against engine transactions. Guarded with hasTable for hosts
 * that already own a refunds table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.refunds', 'refunds');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->string('provider_refund_id')->nullable()->index();
            $table->decimal('amount', 16, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('reason')->nullable();
            $table->text('metadata')->nullable();
            $table->text('provider_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cashier-core.database.tables.refunds', 'refunds'));
    }
};
