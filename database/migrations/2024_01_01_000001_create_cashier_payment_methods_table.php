<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->foreignId('user_id');
            $table->string('processor_name');
            $table->string('processor_payment_method_id');
            $table->string('type');
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id'], 'cashier_pm_user_idx');
            $table->index(['processor_name', 'processor_payment_method_id'], 'cashier_pm_processor_idx');
            $table->index(['user_type', 'user_id', 'is_default'], 'cashier_pm_user_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_payment_methods');
    }
};
