<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands a customer off to Xoala's hosted Standard Checkout.
 *
 * Xoala's checkout is entered by a browser form POST, but the engine's charge
 * contract carries a `redirect_url`. This page is the join: `charge()` returns
 * a signed link here, and this renders the signed field set as a form that
 * submits itself.
 *
 * The URL is signed and expiring (the `signed` middleware enforces both), so
 * the page cannot be reached by guessing a transaction id — and even a valid
 * link only renders while the deposit is still payable.
 */
class XoalaCheckoutController extends Controller
{
    private const DRIVER = 'xoala';

    public function __invoke(string $transaction, ConnectionRegistry $registry): View
    {
        $model = Cashier::transactionModel();

        $record = $model::query()
            ->where('provider', self::DRIVER)
            ->where('provider_transaction_id', $transaction)
            ->first();

        abort_if($record === null, Response::HTTP_NOT_FOUND);

        // A settled, failed or cancelled deposit must not be re-presentable as
        // a payable form: paying it again would open a second charge against a
        // transaction row that can only record one.
        abort_if(
            $record->status !== PaymentStatus::Pending,
            Response::HTTP_CONFLICT,
            'This payment is no longer awaiting payment.',
        );

        try {
            // The connection the charge was taken through, so a second Xoala
            // account signs with its own secure key.
            $provider = $registry->get($record->connection ?: self::DRIVER);
        } catch (PaymentProcessingException|ProcessorNotFoundException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_unless($provider instanceof XoalaProvider, Response::HTTP_NOT_FOUND);

        $form = $provider->checkoutForm($record);

        return view('cashier-core::xoala.checkout', [
            'action' => $form['action'],
            'fields' => $form['fields'],
        ]);
    }
}
