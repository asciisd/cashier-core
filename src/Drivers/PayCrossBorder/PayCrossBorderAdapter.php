<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\Enums\PayoutState;

/**
 * PayCrossBorder payout statuses (responseCodes.md) → PayoutState.
 *
 * `statusName` is the signal; the short `transactionStatus` code is read only
 * when the name is missing, and only `IP` is documented. "Technical Failure"
 * is deliberately Unknown: the docs do not say whether funds left, and Failed
 * releases the withdrawal for a refund or a resend.
 */
final class PayCrossBorderAdapter
{
    private const SENT = ['in process', 'payout processing'];

    private const SENT_CODES = ['IP'];

    private const PAID = ['payout pass'];

    private const FAILED = ['payout fail', 'reverse'];

    public function mapPayoutStatus(?string $statusName, ?string $shortCode = null): PayoutState
    {
        $name = strtolower(trim((string) $statusName));

        if ($name === '') {
            return in_array(strtoupper(trim((string) $shortCode)), self::SENT_CODES, true)
                ? PayoutState::Sent
                : PayoutState::Unknown;
        }

        return match (true) {
            in_array($name, self::SENT, true) => PayoutState::Sent,
            in_array($name, self::PAID, true) => PayoutState::Paid,
            in_array($name, self::FAILED, true) => PayoutState::Failed,
            default => PayoutState::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $row  one `transactionInfoResponse` row
     */
    public function payoutReceipt(array $row): PayoutReceipt
    {
        $name = trim((string) ($row['statusName'] ?? ''));
        $code = trim((string) ($row['transactionStatus'] ?? ''));
        $state = $this->mapPayoutStatus($name, $code);

        $error = null;

        if ($state === PayoutState::Failed) {
            $detail = trim((string) ($row['vendorResultDescription'] ?? $row['resultDescription'] ?? ''));
            $error = "PayCrossBorder {$name}".($detail === '' ? '' : ": {$detail}");
        }

        return new PayoutReceipt(
            reference: (string) ($row['transId'] ?? ''),
            rawStatus: $name !== '' ? $name : $code,
            state: $state,
            payload: $row,
            error: $error,
        );
    }
}
