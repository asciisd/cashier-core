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
use Asciisd\CashierCore\Services\Webhooks\WebhookRelay;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class XoalaWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'xoala';

    public function __invoke(
        Request $request,
        ConnectionRegistry $registry,
        ReplayGuard $replayGuard,
        WebhookRelay $relay,
    ): JsonResponse {
        /*
         * The platform's request-format section specifies
         * application/x-www-form-urlencoded, while the notification samples are
         * JSON with nested `result`, `card` and `customer` objects. The docs
         * commit to neither, so both are read.
         */
        $payload = $request->isJson() ? $request->json()->all() : $request->post();

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) ($payload['checksum'] ?? '');

            $matchedConnection = $this->connectionThatSigned($payload, $registry);

            if ($matchedConnection === null) {
                /*
                 * Logged with a redacted payload: a wrong secure key, a
                 * signature over the long status instead of the short one, and
                 * a rotated key all look identical without it, and the payer's
                 * details are not needed to tell them apart.
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
                // Duplicate delivery — ACK so Xoala stops retrying, process nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        $relay->maybeRelay(self::DRIVER, $matchedConnection, $payload, $request);

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose Xoala account signed this callback, or null.
     *
     * Every account posts to this one URL and the payload names no merchant, so
     * each account's secure key is tried in turn — a match is itself the proof
     * of which account sent it.
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

            if ($provider->verifyWebhookSignature($payload, (string) ($payload['checksum'] ?? ''))) {
                return $connection;
            }
        }

        return null;
    }
}
