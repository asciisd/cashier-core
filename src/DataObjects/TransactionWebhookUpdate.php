<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\PaymentStatus;

/**
 * Data Transfer Object for updating transactions from webhook responses
 *
 * This provides a standardized way for payment processors to communicate
 * transaction updates to the core package.
 */
readonly class TransactionWebhookUpdate
{
    public function __construct(
        public PaymentStatus $status,
        public array $processorResponse,
        public ?PaymentMethodSnapshot $paymentMethodSnapshot = null,
        public ?array $metadata = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $description = null,
        public array $additionalAttributes = [],
    ) {}

    /**
     * Convert to array for database update
     */
    public function toUpdateArray(): array
    {
        $data = [
            'status' => $this->status,
            'processor_response' => $this->processorResponse,
        ];

        // Add payment method snapshot if provided
        if ($this->paymentMethodSnapshot) {
            $data = array_merge($data, $this->paymentMethodSnapshot->toArray());
        }

        // Add optional fields if provided
        if ($this->errorCode !== null) {
            $data['error_code'] = $this->errorCode;
        }

        if ($this->errorMessage !== null) {
            $data['error_message'] = $this->errorMessage;
        }

        if ($this->amount !== null) {
            $data['amount'] = $this->amount;
        }

        if ($this->currency !== null) {
            $data['currency'] = $this->currency;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        // Add timestamp based on status
        if ($this->status === PaymentStatus::Succeeded) {
            $data['processed_at'] = now();
        }

        if ($this->status === PaymentStatus::Failed) {
            $data['failed_at'] = now();
        }

        // Merge any additional driver-specific attributes
        $data = array_merge($data, $this->additionalAttributes);

        return $data;
    }

    /**
     * Get metadata with webhook timestamp
     */
    public function getMetadataWithTimestamp(): array
    {
        return array_merge($this->metadata ?? [], [
            'webhook_updated_at' => now()->toISOString(),
        ]);
    }
}


