<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Canceled => 'Canceled',
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
}
