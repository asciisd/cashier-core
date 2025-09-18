<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case RequiresAction = 'requires_action';
    case RequiresCapture = 'requires_capture';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresPaymentMethod = 'requires_payment_method';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Canceled => 'Canceled',
            self::RequiresAction => 'Requires Action',
            self::RequiresCapture => 'Requires Capture',
            self::RequiresConfirmation => 'Requires Confirmation',
            self::RequiresPaymentMethod => 'Requires Payment Method',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Failed,
            self::Canceled,
        ]);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public function requiresAction(): bool
    {
        return in_array($this, [
            self::RequiresAction,
            self::RequiresCapture,
            self::RequiresConfirmation,
            self::RequiresPaymentMethod,
        ]);
    }
}
