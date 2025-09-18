<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->string('processor_refund_id');
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->string('currency', 3);
            $table->string('status');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->json('processor_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id']);
            $table->index(['processor_refund_id']);
            $table->index(['status']);
            $table->index(['processed_at']);
            $table->index(['created_at']);

            $table->foreign('transaction_id')
                  ->references('id')
                  ->on('cashier_transactions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_refunds');
    }
};
