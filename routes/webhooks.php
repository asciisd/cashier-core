<?php

declare(strict_types=1);

use Asciisd\CashierCore\Http\Controllers\Webhooks\ApsWebhookController;
use Asciisd\CashierCore\Http\Controllers\Webhooks\HeropaymentWebhookController;
use Asciisd\CashierCore\Http\Controllers\Webhooks\JenapayWebhookController;
use Asciisd\CashierCore\Http\Controllers\Webhooks\MyfatoorahWebhookController;
use Asciisd\CashierCore\Http\Controllers\Webhooks\PayportWebhookController;
use Asciisd\CashierCore\Http\Controllers\Webhooks\SticpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Webhook Routes
|--------------------------------------------------------------------------
|
| One POST endpoint per bundled driver. Prefix, middleware and name prefix
| are supplied by the service provider's route group from `cashier-core
| .routes.*` — nothing here carries its own, so a host overriding the group
| config reshapes every endpoint at once.
|
*/

Route::post('/aps', ApsWebhookController::class)->name('aps');
Route::post('/jenapay', JenapayWebhookController::class)->name('jenapay');
Route::post('/heropayment', HeropaymentWebhookController::class)->name('heropayment');
Route::post('/payport', PayportWebhookController::class)->name('payport');
Route::post('/sticpay', SticpayWebhookController::class)->name('sticpay');
Route::post('/myfatoorah', MyfatoorahWebhookController::class)->name('myfatoorah');
