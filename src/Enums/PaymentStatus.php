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

    /**
     * A webhook reported success but its amount or currency deviated from the
     * invoice beyond tolerance. Held for human review: no ledger credit, no
     * invoice mail, until resolved via sync or an audited resolution.
     */
    case OnHold = 'on_hold';

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
            self::OnHold => 'On Hold',
            self::RequiresAction => 'Requires Action',
            self::RequiresCapture => 'Requires Capture',
            self::RequiresConfirmation => 'Requires Confirmation',
            self::RequiresPaymentMethod => 'Requires Payment Method',
        };
    }

    public function isOnHold(): bool
    {
        return $this === self::OnHold;
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

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Canceled]);
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
