<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HeropaymentWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'heropayment';

    public function __invoke(Request $request, ConnectionRegistry $registry, ReplayGuard $replayGuard): JsonResponse
    {
        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) $request->header('x-api-sign', '');

            /** @var HeropaymentProvider $provider */
            $provider = $registry->get(self::DRIVER);

            if (! $provider->verifyRawSignature($request->getContent(), $signature)) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $signature);

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, self::DRIVER)) {
                // Duplicate delivery — ACK so Heropayment stops retrying.
                return response()->json(['status' => 'ok']);
            }
        }

        $payload = $request->json()->all();

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);

        WebhookReceived::dispatch(self::DRIVER, self::DRIVER, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }
}
