<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\PaymentStatus;

readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $message = null,
        public ?array $metadata = null,
        public ?string $processorResponse = null,
        public ?string $errorCode = null,
        public ?PaymentMethodSnapshot $paymentMethodSnapshot = null,
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
        $result = [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'processor_response' => $this->processorResponse,
            'error_code' => $this->errorCode,
        ];

        if ($this->paymentMethodSnapshot) {
            $result = array_merge($result, $this->paymentMethodSnapshot->toArray());
        }

        return $result;
    }
}
