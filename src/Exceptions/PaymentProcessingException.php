<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

use Exception;

class PaymentProcessingException extends Exception
{
    public function __construct(
        string $message = 'Payment processing failed',
        int $code = 0,
        ?Exception $previous = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $processorResponse = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getProcessorResponse(): ?string
    {
        return $this->processorResponse;
    }
}
