<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Http\Controllers\Webhooks;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Drivers\Sticpay\SticpayCallbackPayload;
use Asciisd\CashierCore\Drivers\Sticpay\SticpayProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Http\Concerns\EnforcesSignatureVerification;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Services\Webhooks\ReplayGuard;
use Asciisd\CashierCore\Support\PayloadRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SticpayWebhookController extends Controller
{
    use EnforcesSignatureVerification;

    private const DRIVER = 'sticpay';

    public function __invoke(Request $request, ConnectionRegistry $registry, ReplayGuard $replayGuard): Response
    {
        // Sticpay posts callbacks as application/x-www-form-urlencoded, with
        // everything wrapped in a double JSON-encoded `callback` field.
        $payload = SticpayCallbackPayload::fromInput(
            $request->isJson() ? $request->json()->all() : $request->post()
        );

        $matchedConnection = null;

        if ($this->signatureVerificationEnabled(self::DRIVER)) {
            $matchedConnection = $this->connectionThatSigned($payload->parameters, $registry);

            if ($matchedConnection === null) {
                /*
                 * A rejected callback logged without its payload is
                 * undiagnosable — a key mismatch, a wrong `sign_type` and a
                 * changed signing scheme all look identical. Neither the
                 * payer's details nor our merchant address are needed to tell
                 * them apart, so they are redacted rather than logged.
                 */
                PaymentLogger::providerWebhookSignatureInvalid(
                    self::DRIVER,
                    $payload->signature(),
                    (new PayloadRedactor)->redact($payload->parameters),
                );

                WebhookRejected::dispatch(self::DRIVER, 'invalid signature', $request->ip());

                /*
                 * 403 rather than "OK", so Sticpay keeps retrying. A key rotation
                 * or a connection that was briefly misconfigured then heals
                 * itself; an attacker gains nothing by being retried at.
                 */
                return $this->text('INVALID SIGNATURE', 403);
            }

            if (! $replayGuard->claim(self::DRIVER, $request->getContent(), $payload->signature(), $matchedConnection)) {
                // Duplicate delivery — the same literal "OK" Sticpay expects,
                // nothing dispatched.
                return $this->text('OK');
            }
        }

        if ($this->wrongEnvironment($payload, $registry)) {
            PaymentLogger::providerCallbackEnvironmentMismatch(
                self::DRIVER,
                $payload->orderNo(),
                $this->configuredEnvironment($registry) ?? SticpayProvider::LIVE,
                $payload->interfaceVersion(),
            );

            // "OK" so Sticpay stops retrying a callback we will never act on.
            // This is only an early exit — the authoritative guard is in
            // SticpayProvider::parseWebhook(), which also covers admin sync.
            return $this->text('OK');
        }

        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload->toArray(), $matchedConnection);

        WebhookReceived::dispatch(self::DRIVER, $matchedConnection, (new PayloadRedactor)->redact($payload->toArray()));

        /*
         * The plain text "OK", not JSON — the only webhook in the package that
         * answers this way. Sticpay reads the body literally and keeps
         * redelivering until the merchant-side retry_count is exhausted if it
         * reads anything else.
         */
        return $this->text('OK');
    }

    /**
     * The connection whose Sticpay account signed this callback, or null when
     * none did.
     *
     * A matching signature is itself the proof of which account sent it, so no
     * payload field is trusted to pick a key.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function connectionThatSigned(array $parameters, ConnectionRegistry $registry): ?string
    {
        foreach ($this->connections($registry) as $connection => $provider) {
            if ($provider->verifyWebhookSignature($parameters, (string) ($parameters['sign'] ?? ''))) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * Whether the callback's environment disagrees with every configured
     * account's.
     *
     * Sticpay serves sandbox and live from one endpoint, distinguished only by
     * `interface_version`, and the vendor docs are explicit that a sandbox
     * callback must not credit real funds.
     */
    private function wrongEnvironment(SticpayCallbackPayload $payload, ConnectionRegistry $registry): bool
    {
        $received = $payload->interfaceVersion();

        if ($received === null) {
            return false;
        }

        foreach ($this->connections($registry) as $provider) {
            if ($provider->interfaceVersion() === $received) {
                return false;
            }
        }

        return true;
    }

    private function configuredEnvironment(ConnectionRegistry $registry): ?string
    {
        foreach ($this->connections($registry) as $provider) {
            return $provider->interfaceVersion();
        }

        return null;
    }

    /**
     * Every Sticpay account whose credentials are filled in, keyed by
     * connection name.
     *
     * Skipping the ones that are not keeps the configured accounts usable —
     * a half-provisioned second merchant must not block the first.
     *
     * @return iterable<string, SticpayProvider>
     */
    private function connections(ConnectionRegistry $registry): iterable
    {
        foreach (Connections::forDriver(self::DRIVER) as $connection) {
            try {
                $provider = $registry->get($connection);
            } catch (PaymentProcessingException|ProcessorNotFoundException) {
                continue;
            }

            if ($provider instanceof SticpayProvider) {
                yield $connection => $provider;
            }
        }
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
