<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services;

use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    /**
     * Update transaction from webhook using standardized DTO
     *
     * @param Transaction $transaction The transaction to update
     * @param TransactionWebhookUpdate $webhookUpdate Standardized webhook data from driver
     * @return Transaction Updated transaction instance
     */
    public function updateFromWebhook(
        Transaction $transaction,
        TransactionWebhookUpdate $webhookUpdate
    ): Transaction {
        // Get base update data from DTO
        $updateData = $webhookUpdate->toUpdateArray();

        // Check if we should update payment method fields
        if ($webhookUpdate->paymentMethodSnapshot) {
            $shouldUpdatePaymentMethod =
                empty($transaction->payment_method_type) ||
                empty($transaction->payment_method_brand) ||
                empty($transaction->payment_method_last_four) ||
                empty($transaction->payment_method_display_name);

            // If payment method is already set, remove it from update data
            if (! $shouldUpdatePaymentMethod) {
                unset(
                    $updateData['payment_method_type'],
                    $updateData['payment_method_brand'],
                    $updateData['payment_method_last_four'],
                    $updateData['payment_method_display_name']
                );
            }
        }

        // Merge metadata if provided
        if ($webhookUpdate->metadata !== null) {
            $existingMetadata = $transaction->metadata ?? [];
            $updateData['metadata'] = array_merge(
                $existingMetadata,
                $webhookUpdate->getMetadataWithTimestamp()
            );
        }

        // Perform the update
        $transaction->update($updateData);

        // Log the update
        if (config('cashier-core.logging.enabled', true)) {
            Log::info('Transaction updated from webhook via DTO', [
                'transaction_id' => $transaction->id,
                'processor_transaction_id' => $transaction->processor_transaction_id,
                'processor_name' => $transaction->processor_name,
                'status' => $webhookUpdate->status->value,
                'updated_fields' => array_keys($updateData),
            ]);
        }

        return $transaction->fresh();
    }

    /**
     * Find transaction by processor transaction ID
     */
    public function findByProcessorTransactionId(string $processorTransactionId, string $processorName): ?Transaction
    {
        return Transaction::where('processor_transaction_id', $processorTransactionId)
            ->where('processor_name', $processorName)
            ->first();
    }
}


