<?php

declare(strict_types=1);

use Asciisd\CashierCore\Http\Controllers\XoalaCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Hosted-Checkout Bridges
|--------------------------------------------------------------------------
|
| Signed GET pages that hand a customer off to a PSP whose hosted checkout is
| entered by a form POST rather than a URL. Prefix, middleware and name prefix
| come from `cashier-core.routes.checkout.*` via the service provider's group.
|
*/

Route::get('/xoala/checkout/{transaction}', XoalaCheckoutController::class)->name('xoala');
