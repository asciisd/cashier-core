<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

/**
 * The send timed out, errored server-side, or came back without an id: the
 * payout may exist. Look it up before any resend.
 */
class PayoutOutcomeUnknownException extends PaymentProcessingException {}
