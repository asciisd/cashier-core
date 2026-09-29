<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Illuminate\Support\Facades\Route;

/**
 * This package's webhook endpoint for a driver — the fallback callback URL a
 * charge sends when its connection has no explicit `webhook_url`.
 *
 * The route's NAME is host-configurable: `routes.name_prefix` defaults to
 * `cashier.webhooks.`, but an application that took over a set of callback
 * URLs already registered with its PSPs commonly sets it to `webhooks.`.
 * Hardcoding either prefix means the fallback silently never fires in half of
 * all installs — and a missing callback URL is invisible: the PSP simply has
 * nowhere to report the payment, and it sits pending until someone syncs it.
 *
 * So the configured prefix is asked first, then both known conventions. A
 * host that disabled the package routes and registered its own under another
 * name gets null and should set `webhook_url` on the connection explicitly.
 */
final class WebhookRoute
{
    public static function url(string $driver): ?string
    {
        $configured = (string) config('cashier-core.routes.name_prefix', 'cashier.webhooks.');

        $candidates = array_unique([
            $configured.$driver,
            'cashier.webhooks.'.$driver,
            'webhooks.'.$driver,
        ]);

        foreach ($candidates as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }
}
