<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\RefundStatus;

readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public string $refundId,
        public string $originalTransactionId,
        public RefundStatus $status,
        public int $amount,
        public string $currency,
        public ?string $message = null,
        public ?array $metadata = null,
        public ?string $processorResponse = null,
        public ?string $errorCode = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'refund_id' => $this->refundId,
            'original_transaction_id' => $this->originalTransactionId,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'processor_response' => $this->processorResponse,
            'error_code' => $this->errorCode,
        ];
    }
}
