<?php

declare(strict_types=1);

use Asciisd\CashierCore\Support\WebhookRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

it('resolves the package route under the default name prefix', function () {
    expect(WebhookRoute::url('heropayment'))->toBe(route('cashier.webhooks.heropayment'));
});

it('prefers the route under a host-customised name prefix', function () {
    config()->set('cashier-core.routes.name_prefix', 'psp.');

    // A distinct path: the package route is still registered under the
    // default prefix, so a shared path would pass whichever name resolved.
    Route::post('psp/callbacks/heropayment', fn () => null)->name('psp.heropayment');
    Route::getRoutes()->refreshNameLookups();

    expect(WebhookRoute::url('heropayment'))->toBe(route('psp.heropayment'));
});

it('falls back to the legacy webhooks. name hosts registered themselves', function () {
    app('router')->setRoutes(new RouteCollection);
    Route::post('legacy/aps', fn () => null)->name('webhooks.aps');
    Route::getRoutes()->refreshNameLookups();

    expect(WebhookRoute::url('aps'))->toBe(route('webhooks.aps'));
});

it('returns null when no webhook route is registered for the driver', function () {
    app('router')->setRoutes(new RouteCollection);

    expect(WebhookRoute::url('heropayment'))->toBeNull();
});
