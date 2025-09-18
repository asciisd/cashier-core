<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

use Exception;

class ProcessorNotFoundException extends Exception
{
    public function __construct(string $message = 'Payment processor not found', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
