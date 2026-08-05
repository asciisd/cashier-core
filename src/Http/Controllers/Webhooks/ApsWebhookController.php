<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Aps\ApsProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApsWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'aps';

    public function __invoke(Request $request, ConnectionRegistry $registry, ReplayGuard $replayGuard): JsonResponse
    {
        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) $request->header('X-Signature', '');

            $matchedConnection = $this->connectionThatSigned($request->getContent(), $signature, $registry);

            if ($matchedConnection === null) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $signature);

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, $matchedConnection)) {
                // Duplicate delivery — ACK so APS stops retrying, process nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        $payload = $request->json()->all();

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose APS account signed this callback, or null when none
     * did.
     *
     * Every account posts to this one URL and the payload carries no merchant
     * identifier, so each account's callback secret is tried in turn — a match
     * is itself the proof of which account sent it. The matched connection
     * rides along to the job so the payload is parsed under the credentials
     * that signed it.
     */
    private function connectionThatSigned(
        string $rawBody,
        string $signature,
        ConnectionRegistry $registry
    ): ?string {
        foreach (Connections::forDriver(self::DRIVER) as $connection) {
            try {
                $provider = $registry->get($connection);
            } catch (PaymentProcessingException|ProcessorNotFoundException) {
                // An account whose credentials are not filled in yet. Skipping
                // keeps the accounts that are configured verifiable.
                continue;
            }

            if ($provider instanceof ApsProvider && $provider->verifyRawSignature($rawBody, $signature)) {
                return $connection;
            }
        }

        return null;
    }
}
