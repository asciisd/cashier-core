<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Baseline HTTP client shape for every PSP gateway call.
 *
 * A user-facing deposit request must never hang on a dead PSP: without an
 * explicit connect timeout, a DNS or TCP black hole holds the request for the
 * full default timeout. Charge-type POSTs deliberately do NOT retry — a
 * create-invoice call that timed out may have succeeded server-side, and
 * replaying it opens a second invoice. Reads opt into retry via idempotent().
 */
final class PspHttp
{
    public static function client(): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(15);
    }

    /**
     * For status lookups and other safely repeatable calls only.
     */
    public static function idempotent(): PendingRequest
    {
        return self::client()->retry(2, 250, throw: false);
    }
}
