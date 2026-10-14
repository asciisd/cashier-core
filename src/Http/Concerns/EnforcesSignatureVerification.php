<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Whether a webhook controller must verify the delivery's signature.
 *
 * `cashier-core.webhooks.verify_signature` exists for local development only
 * — a sandbox PSP that signs nothing, a tunnel replaying captured callbacks.
 * In production the flag is refused: verification always runs, and the
 * attempt to disable it is logged as critical so it is impossible to ship a
 * webhook endpoint that credits money on an unsigned POST without noticing.
 */
trait EnforcesSignatureVerification
{
    protected function signatureVerificationEnabled(string $driver): bool
    {
        $configured = (bool) config(
            'cashier-core.webhooks.verify_signature',
            config('transactions.webhooks.verify_signature', true),
        );

        if ($configured) {
            return true;
        }

        if (app()->isProduction()) {
            Log::critical('cashier-core: refusing to honor disabled webhook signature verification in production', [
                'driver' => $driver,
            ]);

            return true;
        }

        Log::warning('cashier-core: webhook signature verification is DISABLED', [
            'driver' => $driver,
        ]);

        return false;
    }
}
