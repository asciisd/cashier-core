<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * Proof that a ledger movement happened, as returned by the host's
 * {@see \Asciisd\CashierCore\Contracts\FundsLedger} implementation.
 */
readonly class LedgerTicket
{
    public function __construct(
        public string $ticket,
        public ?string $comment = null,
    ) {}

    /**
     * @return array{ticket: string, comment: ?string}
     */
    public function toArray(): array
    {
        return [
            'ticket' => $this->ticket,
            'comment' => $this->comment,
        ];
    }
}
