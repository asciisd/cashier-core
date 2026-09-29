<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

/**
 * The PSP validated and refused the payout — nothing was created, so it is
 * safe to correct the input and send again.
 */
class PayoutRejectedException extends PaymentProcessingException {}
