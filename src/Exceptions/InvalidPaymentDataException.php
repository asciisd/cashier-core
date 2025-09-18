<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

use Exception;

class InvalidPaymentDataException extends Exception
{
    public function __construct(string $message = 'Invalid payment data', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
