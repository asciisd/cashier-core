<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services;

use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;

/**
 * The payment method columns a provider snapshot actually knows.
 *
 * A snapshot is built on every provider response, including ones that arrive
 * before the customer has picked an instrument — a Heropayment invoice is
 * created with no `payCurrency`, and `PaymentMethodSnapshot::fromCardData()`
 * resolves an unrecognised brand string via `tryFrom() ?? Other`. In both cases
 * the snapshot reports `other`, meaning "no idea", not a payment method.
 *
 * `type` and `brand` are non-nullable enums, so filtering on `!== null` alone
 * never drops either of them: the sentinel would always win the merge and
 * replace the method the user actually selected with a value that carries less
 * information and resolves to no icon. Drop the sentinel so the selection
 * survives until a provider reports something real.
 */
final class PaymentMethodSnapshotAttributes
{
    /**
     * The "no idea" case of each enum-backed column.
     *
     * Both enums spell it `other`; keeping them mapped per column means a
     * future rename on one side cannot silently start matching the other.
     */
    private const SENTINELS = [
        'payment_method_type' => PaymentMethodType::Other,
        'payment_method_brand' => PaymentMethodBrand::Other,
    ];

    /**
     * @return array<string, string>
     */
    public static function known(?PaymentMethodSnapshot $snapshot): array
    {
        if (! $snapshot) {
            return [];
        }

        return array_filter(
            $snapshot->toArray(),
            fn ($value, string $column) => $value !== null && ! self::isUnknown($column, $value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private static function isUnknown(string $column, mixed $value): bool
    {
        return isset(self::SENTINELS[$column])
            && $value === self::SENTINELS[$column]->value;
    }
}
