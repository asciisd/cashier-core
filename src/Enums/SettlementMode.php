<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

use Asciisd\CashierCore\Fees\FeeCalculator;

/**
 * How a PSP takes its fee, which decides what amount we must send it so that
 * `A + markup` actually lands in our account.
 *
 * This is a contract fact per PSP account, not a preference — getting it wrong
 * does not fail loudly, it just quietly under- or over-collects on every
 * deposit. {@see FeeCalculator}
 */
enum SettlementMode: string
{
    /**
     * The PSP appends its fee to the customer's charge at its own checkout, so
     * we request `A + markup` and the customer is debited more than that.
     */
    case Added = 'added';

    /**
     * The PSP nets its fee out of what it settles to us, so the request has to
     * be grossed up for `A + markup` to survive the deduction.
     */
    case Deducted = 'deducted';

    /**
     * The PSP settles in full and bills us separately, so we collect its fee
     * from the customer and hold it against the invoice.
     */
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Added at checkout (customer pays the fee to the PSP)',
            self::Deducted => 'Deducted from settlement (PSP keeps its fee)',
            self::Invoiced => 'Invoiced separately (PSP bills us later)',
        };
    }

    /**
     * Whether the PSP adds its own fee on top of what we request.
     *
     * The one mode where we must NOT gross up: doing so would charge the
     * customer the fee twice.
     */
    public function pspAddsAtCheckout(): bool
    {
        return $this === self::Added;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $mode): array => $carry + [$mode->value => $mode->label()],
            [],
        );
    }
}
