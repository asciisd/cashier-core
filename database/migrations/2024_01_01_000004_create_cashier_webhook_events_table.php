<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay guard: one row per accepted webhook delivery. The digest covers
 * (driver, signature, raw body); its uniqueness is what makes a captured
 * callback worthless to replay. Rows expire via `cashier:purge`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.webhook_events', 'cashier_webhook_events');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('driver');
            $table->string('connection')->nullable();
            $table->string('digest', 64);
            $table->timestamp('received_at');

            $table->unique(['driver', 'digest']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cashier-core.database.tables.webhook_events', 'cashier_webhook_events'));
    }
};
