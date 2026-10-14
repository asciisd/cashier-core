<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Services\Webhooks\WebhookRelay;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Digiblox deposit webhook.
 *
 * Digiblox signs nothing — authenticity rests entirely on a static header
 * registered with them and replayed on every delivery. That makes the header
 * this driver's only authenticity mechanism, so an unconfigured header must
 * never be treated as "unchecked": in production (or wherever verification is
 * enabled) a missing `webhook_header_name`/`webhook_header_value` is rejected
 * exactly like a mismatched one, rather than left open to any forged POST.
 * Deliveries are capped at three attempts with a ten-second total timeout, so
 * this ACKs before any business logic and leaves the work to the queue.
 */
class DigibloxWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'digiblox';

    public function __invoke(
        Request $request,
        ReplayGuard $replayGuard,
        WebhookRelay $relay,
    ): JsonResponse {
        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $config = (array) config('cashier-core.connections.'.self::DRIVER, []);

            $headerName = (string) ($config['webhook_header_name'] ?? '');
            $expected = (string) ($config['webhook_header_value'] ?? '');

            if ($headerName === '' || $expected === '') {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, '');

                WebhookRejected::dispatch(self::DRIVER, 'webhook header not configured', $request->ip());

                return response()->json(['error' => 'Invalid credentials'], 403);
            }

            $presented = (string) $request->header($headerName, '');

            if (! hash_equals($expected, $presented)) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $presented);

                WebhookRejected::dispatch(self::DRIVER, 'invalid header', $request->ip());

                return response()->json(['error' => 'Invalid credentials'], 403);
            }
        }

        $payload = $request->json()->all();

        // Key on tx_hash, never on external_transaction_id: one payment link
        // can legitimately take several payments, each its own delivery with
        // the same order id. Keying on the order would silently drop a second
        // genuine payment's delivery at this layer — this only guarantees the
        // second delivery reaches the queue, not that it is credited:
        // WebhookProcessor treats a callback on an already-Succeeded
        // transaction as an out-of-order update and discards it, so a second
        // payment on the same link still needs its own handling upstream of
        // this controller.
        $txHash = (string) ($payload['tx_hash'] ?? '');

        // A delivery without tx_hash is malformed, but it must still dedupe
        // on retry rather than reprocess forever: fall back to the raw body
        // as the dedupe material so the guard is always consulted.
        $dedupeMaterial = $txHash !== '' ? $txHash : $request->getContent();

        // Digiblox provides no signature, so that slot in the replay digest
        // is left empty; the digest still stays unique per tx_hash (or the
        // raw body, when there is no hash) via the raw-body argument.
        if (! $replayGuard->claim(self::DRIVER, $dedupeMaterial, '', self::DRIVER)) {
            // Duplicate delivery — ACK so Digiblox stops retrying.
            return response()->json(['status' => 'ok']);
        }

        $relay->maybeRelay(self::DRIVER, self::DRIVER, $payload, $request);

        // Queued, not processed inline: an unmatched order must still be
        // stored and answered 200. A non-2xx burns one of only three retries
        // and the delivery is then abandoned — we would lose the data.
        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);

        WebhookReceived::dispatch(self::DRIVER, self::DRIVER, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }
}
