<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
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

class PayportWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'payport';

    public function __invoke(Request $request, ConnectionRegistry $registry, ReplayGuard $replayGuard): JsonResponse
    {
        // Payport posts callbacks as application/x-www-form-urlencoded.
        $payload = $request->isJson() ? $request->json()->all() : $request->post();

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) ($payload['signature'] ?? '');

            $matchedConnection = $this->connectionThatSigned($payload, $registry);

            if ($matchedConnection === null) {
                /*
                 * A rejected callback logged without its payload is
                 * undiagnosable — a key mismatch, a stale invoice signed by a
                 * rotated key, and a changed signing scheme all look identical.
                 * The payer's details are not needed to tell them apart, so
                 * they are redacted rather than logged.
                 */
                PaymentLogger::providerWebhookSignatureInvalid(
                    self::DRIVER,
                    $signature,
                    (new PayloadRedactor)->redact($payload),
                );

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, $matchedConnection)) {
                // Duplicate delivery — ACK so Payport stops retrying, process nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose Payport account signed this callback, or null when
     * none did.
     *
     * The SAR and EGP processors are two connections over one merchant account,
     * and both post here. The payload's `merchant_id` is not trusted to pick a
     * key — a matching signature is itself the proof of which account sent it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function connectionThatSigned(array $payload, ConnectionRegistry $registry): ?string
    {
        foreach (Connections::forDriver(self::DRIVER) as $connection) {
            try {
                $provider = $registry->get($connection);
            } catch (PaymentProcessingException|ProcessorNotFoundException) {
                // An account whose credentials are not filled in yet. Skipping
                // keeps the accounts that are configured verifiable.
                continue;
            }

            if ($provider->verifyWebhookSignature($payload, (string) ($payload['signature'] ?? ''))) {
                return $connection;
            }
        }

        return null;
    }
}
