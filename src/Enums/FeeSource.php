<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

/**
 * Where a deposit's PSP fee comes from: the configured estimate, or the figure
 * the PSP reports once the payment settles.
 *
 * Independent of {@see SettlementMode}, which says who bears the fee. A
 * reported fee only ever lowers what the customer pays: the configured
 * estimate is charged at checkout and is the ceiling, and any part of it the
 * PSP did not take is credited back on top of the deposit.
 */
enum FeeSource: string
{
    /** The configured fee is final. */
    case Configured = 'configured';

    /**
     * The configured fee is an estimate, settled against the fee the PSP
     * reports in its success callback.
     */
    case Reported = 'reported';

    public function label(): string
    {
        return match ($this) {
            self::Configured => 'Configured (fee is final at checkout)',
            self::Reported => 'Reported by the PSP (configured fee is the maximum)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $source): array => $carry + [$source->value => $source->label()],
            [],
        );
    }
}
