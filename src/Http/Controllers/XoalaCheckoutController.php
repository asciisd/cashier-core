<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

    public function __invoke(Request $request, string $transaction, ConnectionRegistry $registry): View
    {
        $model = Cashier::transactionModel();

        // The link is opened in the customer's browser, with no session on
        // this route, so a host's owner scope would hide the row and 404 every
        // checkout. The signature is the authorisation here, not the session.
        $record = $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
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

        $form = $provider->checkoutForm($record, $this->customerFor($record), $request->ip());

        return view('cashier-core::xoala.checkout', [
            'action' => $form['action'],
            'fields' => $form['fields'],
        ]);
    }

    /**
     * The customer the deposit belongs to, so the hosted page can be
     * prefilled. A missing model or customer only means Xoala asks for the
     * details itself — it must never stop the customer reaching the page.
     */
    private function customerFor(object $record): ?CustomerContract
    {
        $customerModel = Cashier::customerModel();

        if (! is_string($customerModel) || ! class_exists($customerModel) || empty($record->user_id)) {
            return null;
        }

        $customer = $customerModel::query()->find($record->user_id);

        return $customer instanceof CustomerContract ? $customer : null;
    }
}
