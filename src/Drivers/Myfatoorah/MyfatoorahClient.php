<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * The MyFatoorah V3 HTTP surface, and the one place that knows how many ways
 * MyFatoorah can say "no".
 *
 * The base URL is per country: api.myfatoorah.com serves Kuwait, Bahrain,
 * Oman and Jordan; Saudi Arabia, the UAE, Qatar and Egypt each have their
 * own; every country shares the apitest.myfatoorah.com sandbox. One API key
 * belongs to exactly one country and must be sent to that country's host, so
 * a merchant trading in two countries is two connections — the host is
 * configuration, never derived here.
 */
final class MyfatoorahClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Create a payment. Returns the `Data` object: `InvoiceId`, `PaymentId`,
     * `PaymentURL`, `PaymentCompleted`, `TransactionDetails`.
     *
     * `$idempotencyKey` is opt-in, holds for 250 minutes, and is the only
     * protection against a double charge on a retry — nothing turns it on for
     * you. See pitfalls.md entry 11.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post("{$this->baseUrl}/v3/payments", $payload);

        return $this->envelope($response);
    }

    /**
     * Fetch an invoice with its transaction array. Returns null when
     * MyFatoorah does not know it.
     *
     * Keyed by InvoiceId rather than PaymentId: PaymentId is null on every
     * redirect-flow create response, so it is not an id we reliably hold.
     * An unknown invoice comes back as a 200 with `"Message": "No invoices
     * match this InvoiceId"`, not a 404, which is why the envelope decides
     * this rather than the status code.
     *
     * @return array<string, mixed>|null
     */
    public function getInvoice(string $invoiceId): ?array
    {
        $response = $this->idempotentRequest()->get("{$this->baseUrl}/v3/invoices/{$invoiceId}");

        try {
            return $this->envelope($response);
        } catch (PaymentProcessingException $e) {
            PaymentLogger::providerTransactionLookupFailed(
                'myfatoorah',
                $invoiceId,
                $response->status(),
                Str::limit($response->body(), 500),
            );

            return null;
        }
    }

    /**
     * Unwrap MyFatoorah's response envelope, or throw with the best message
     * the body affords.
     *
     * `IsSuccess: false` arrives in at least five shapes and the Response
     * Model page documents only the first (pitfalls.md entry 6):
     *
     *   1. `ValidationErrors: [{Name, Error}]`  — the documented envelope
     *   2. `FieldsErrors: [{Name, Error}]`      — same thing, other key
     *   3. `Data.ErrorMessage`                  — a string, no error array
     *   4. `Message` + `MessageDetail` and NO `IsSuccess` field at all
     *   5. an HTML page rather than JSON — a 403 from Azure App Gateway
     *
     * Shape 4 is the dangerous one: a parser keyed off `IsSuccess` reads a
     * routing error as a success.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    private function envelope(Response $response): array
    {
        $body = $response->json();

        // Shape 5. json() is null for an HTML body.
        if (! is_array($body)) {
            throw new PaymentProcessingException(
                "MyFatoorah returned a non-JSON response ({$response->status()})."
            );
        }

        // Shape 4, checked before the value so an absent key is not read as false.
        if (! array_key_exists('IsSuccess', $body)) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        // Documented as a string ("true"/"false") but sent as a boolean in
        // every example. filter_var accepts both.
        if (filter_var($body['IsSuccess'], FILTER_VALIDATE_BOOLEAN) !== true) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        $data = $body['Data'] ?? null;

        if (! is_array($data)) {
            throw new PaymentProcessingException(self::errorMessage($body));
        }

        return $data;
    }

    /**
     * Assemble a message defensively: `ValidationErrors[].Error` may be an
     * empty string with the field name in `Name`, so a message built only
     * from `Error` values comes out blank.
     *
     * @param  array<string, mixed>  $body
     */
    private static function errorMessage(array $body): string
    {
        $errors = $body['ValidationErrors'] ?? $body['FieldsErrors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $parts = [];

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    $parts[] = trim((string) $error);

                    continue;
                }

                $parts[] = trim(trim((string) ($error['Name'] ?? '')).': '.trim((string) ($error['Error'] ?? '')), ': ');
            }

            $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

            if ($parts !== []) {
                return 'MyFatoorah rejected the request: '.implode('; ', $parts);
            }
        }

        $dataError = data_get($body, 'Data.ErrorMessage');

        if (is_string($dataError) && trim($dataError) !== '') {
            return 'MyFatoorah rejected the request: '.trim($dataError);
        }

        $message = trim(trim((string) ($body['Message'] ?? '')).' '.trim((string) ($body['MessageDetail'] ?? '')));

        return $message !== ''
            ? 'MyFatoorah rejected the request: '.$message
            : 'MyFatoorah rejected the request.';
    }

    private function request(): PendingRequest
    {
        return $this->withAuth(PspHttp::client());
    }

    /**
     * For invoice lookups only — safely repeatable, so retries are allowed.
     * The charge POST deliberately does not retry: a create call that timed
     * out may have succeeded, and replaying it opens a second invoice.
     */
    private function idempotentRequest(): PendingRequest
    {
        return $this->withAuth(PspHttp::idempotent());
    }

    private function withAuth(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->apiKey)->acceptJson();
    }
}
