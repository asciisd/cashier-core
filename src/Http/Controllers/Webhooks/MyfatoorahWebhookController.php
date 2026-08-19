<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahProvider;
use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;
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
use Illuminate\Support\Facades\Log;

class MyfatoorahWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'myfatoorah';

    /**
     * The only webhook version this driver accepts. V1 signs the whole `Data`
     * object with its keys sorted case-insensitively; V2 signs a fixed
     * per-event field list. The rules are incompatible, and a webhook
     * configured fresh in the portal is V2.
     */
    private const WEBHOOK_VERSION = 'v2';

    public function __invoke(
        Request $request,
        ConnectionRegistry $registry,
        ReplayGuard $replayGuard,
    ): JsonResponse {
        $payload = $request->json()->all();

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            // The header MyFatoorah does not document. Its own library reads
            // it and refuses without it. The version cannot be inferred from
            // the payload shape — V1 (`EventType` + `Event` + `Data`) and V2
            // (`Event.Code` + `Event.Name` + `Data`) are close enough to
            // confuse, and guessing wrong produces a signature failure that
            // says nothing about why.
            $version = strtolower((string) $request->header('MyFatoorah-Webhook-Version', ''));

            if ($version !== self::WEBHOOK_VERSION) {
                Log::critical('cashier-core: refusing a MyFatoorah webhook of an unsupported version', [
                    'driver' => self::DRIVER,
                    'version' => $version !== '' ? $version : '(absent)',
                ]);

                WebhookRejected::dispatch(self::DRIVER, 'unsupported webhook version', $request->ip());

                return response()->json(['error' => 'Unsupported webhook version'], 403);
            }
        }

        $eventCode = (int) data_get($payload, 'Event.Code', 0);

        // Refund, deposit, supplier, recurring, dispute and supplier-update
        // events all land here. ACK so MyFatoorah stops retrying — a non-200
        // for a transient reason can lose an event permanently, and
        // GetWebhooks is the only way back — but dispatch nothing.
        //
        // This happens BEFORE verification deliberately: verifying an event we
        // then discard would mean carrying six more field lists to reach the
        // same outcome, and an unverified payload that is never acted on
        // cannot do harm.
        if ($eventCode !== MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED) {
            Log::info('cashier-core: ignoring an unhandled MyFatoorah webhook event', [
                'driver' => self::DRIVER,
                'event_code' => $eventCode,
                'event_name' => data_get($payload, 'Event.Name'),
            ]);

            return response()->json(['status' => 'ok']);
        }

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $signature = (string) $request->header('MyFatoorah-Signature', '');

            $matchedConnection = $this->connectionThatSigned($payload, $signature, $registry);

            if ($matchedConnection === null) {
                PaymentLogger::providerWebhookSignatureInvalid(self::DRIVER, $signature);

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, $matchedConnection)) {
                // Duplicate delivery, and MyFatoorah says these are not rare —
                // some methods send several webhooks for one transaction and
                // MyFatoorah forwards them all. ACK so it stops, process
                // nothing.
                return response()->json(['status' => 'ok']);
            }
        }

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The connection whose MyFatoorah account signed this delivery, or null
     * when none did.
     *
     * Every account posts to this one URL and the payload names no merchant,
     * so each account's webhook secret is tried in turn — a match is itself
     * the proof of which account sent it. The docs never say whether one
     * account with several webhook endpoints gets one secret or several;
     * this is correct either way, and is required regardless the moment a
     * second country's connection exists.
     *
     * @param  array<string, mixed>  $payload
     */
    private function connectionThatSigned(
        array $payload,
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

            if ($provider instanceof MyfatoorahProvider && $provider->verifyWebhookSignature($payload, $signature)) {
                return $connection;
            }
        }

        return null;
    }
}
