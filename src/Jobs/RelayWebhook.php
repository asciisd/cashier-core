<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Jobs;

use Asciisd\CashierCore\Logging\PaymentLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Relay a verified provider callback verbatim to a third party that also needs
 * to see it.
 *
 * Queued rather than inline: the PSP reads our response to decide whether
 * delivery succeeded, so a slow or dead relay host must never sit in front of
 * the acknowledgement and push the callback into a retry cycle.
 *
 * The raw request body is relayed byte-for-byte under its original
 * `Content-Type` rather than a re-encoded array. The recipient was the
 * callback's original destination and parses whatever the PSP sends — and for
 * signed payloads a re-encode is not merely untidy but fatal, since key order
 * and escaping are what the signature covers.
 */
class RelayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function __construct(
        public readonly string $driver,
        public readonly string $url,
        public readonly string $body,
        public readonly string $contentType,
    ) {
        $this->onQueue(config('cashier-core.queue.queue', 'payments'));
    }

    public function handle(): void
    {
        try {
            $response = Http::withBody($this->body, $this->contentType)
                ->timeout(15)
                ->connectTimeout(5)
                ->post($this->url)
                ->throw();
        } catch (HttpClientException $e) {
            // A connection timeout is a ConnectionException — a sibling of
            // RequestException, not a subclass — so it carries no response.
            $status = $e instanceof RequestException ? $e->response?->status() : null;

            PaymentLogger::providerWebhookRelayFailed($this->driver, $this->url, $status, $e->getMessage());

            throw $e;
        }

        PaymentLogger::providerWebhookRelayed($this->driver, $this->url, $response->status());
    }
}
