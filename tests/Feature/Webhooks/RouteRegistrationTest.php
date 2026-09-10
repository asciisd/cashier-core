<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('registers every webhook route under the configured prefix and name prefix', function () {
    foreach (['aps', 'jenapay', 'heropayment', 'payport', 'sticpay', 'myfatoorah', 'xoala'] as $driver) {
        expect(Route::has("cashier.webhooks.{$driver}"))->toBeTrue();

        $route = Route::getRoutes()->getByName("cashier.webhooks.{$driver}");

        expect($route->uri())->toBe("api/webhooks/{$driver}")
            ->and($route->methods())->toContain('POST')
            ->and($route->gatherMiddleware())->toContain('api', 'throttle:cashier-webhooks');
    }
});

it('registers a default cashier-webhooks rate limiter', function () {
    expect(RateLimiter::limiter('cashier-webhooks'))->not->toBeNull();
});
