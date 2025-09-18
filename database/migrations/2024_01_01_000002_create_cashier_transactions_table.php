<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('processor_name');
            $table->string('processor_transaction_id');
            $table->string('payable_type');
            $table->foreignId('payable_id')->constrained('users', 'id');
            $table->foreignId('payment_method_id');
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->string('currency', 3);
            $table->string('status');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('processor_response')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['processor_name', 'processor_transaction_id'], 'cashier_transactions_processor_idx');
            $table->index(['status']);
            $table->index(['currency']);
            $table->index(['processed_at']);
            $table->index(['created_at']);

            $table->foreign('payment_method_id')
                  ->references('id')
                  ->on('cashier_payment_methods')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_transactions');
    }
};
