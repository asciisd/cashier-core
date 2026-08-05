<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class JenapayWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'jenapay';

    /**
     * The Akurateco platform behind Jenapay does not read the status code to
     * decide whether a callback was delivered — it reads the response *body*,
     * which must be the literal string `OK` on success or `ERROR` otherwise.
     * A JSON body (even with a 200) reads as a failed delivery and puts the
     * callback into the platform's retry cycle. Same class of plain-text
     * response contract as KNET's `REDIRECT=` line.
     */
    private const ACK = 'OK';

    private const NACK = 'ERROR';

    public function __invoke(Request $request, ConnectionRegistry $registry, ReplayGuard $replayGuard): Response
    {
        $payload = $request->isJson() ? $request->json()->all() : $request->post();

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $provider = $registry->get(self::DRIVER);

            $signature = (string) ($payload['hash'] ?? '');

            if (! $provider->verifyWebhookSignature($payload, $signature)) {
                // Log the field names, not the payload: they are what a hash
                // mismatch turns on (`id` vs `payment_id`, flat vs nested
                // order fields) and they carry no card or customer data.
                PaymentLogger::providerWebhookSignatureInvalid(
                    self::DRIVER,
                    $payload['hash'] ?? null,
                    ['payload_keys' => array_keys($payload)],
                );

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                return response(self::NACK, 403)->header('Content-Type', 'text/plain');
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $signature, self::DRIVER)) {
                // Duplicate delivery — the platform needs its ACK, nothing runs.
                return response(self::ACK)->header('Content-Type', 'text/plain');
            }
        }

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);

        WebhookReceived::dispatch(self::DRIVER, self::DRIVER, (new PayloadRedactor)->redact($payload));

        return response(self::ACK)->header('Content-Type', 'text/plain');
    }
}
