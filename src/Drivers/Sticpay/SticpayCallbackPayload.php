<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Sticpay;

/**
 * A decoded Sticpay callback envelope.
 *
 * Sticpay posts every notification — the success/failure/cancel redirects
 * (§2-3 – 2-5) and the transaction callback (§2-6) — as a single form field
 * named `callback` holding a JSON string, whose `message` and `parameters`
 * sub-fields are *themselves* JSON strings:
 *
 *     callback={"type":"payment","code":-1,"message":"","parameters":"{\"order_no\":\"DEP-1\",...}"}
 *
 * Decoding happens here and nowhere else, so the signature service, the
 * adapter and the transaction-id extractor all read the same flat array. The
 * job is dispatched {@see toArray()}, which is already flat — nothing
 * downstream ever sees the envelope.
 *
 * Partially-decoded shapes are accepted too: some Sticpay deployments send the
 * sub-fields as real JSON objects rather than strings, and the resend-callback
 * endpoint replays whichever form was originally delivered.
 */
final readonly class SticpayCallbackPayload
{
    /**
     * Envelope fields, re-attached to the flat array under reserved names.
     *
     * The `callback_` prefix cannot collide with a documented Sticpay
     * parameter — none of them start with it.
     */
    private const RESERVED_PREFIX = 'callback_';

    /**
     * @param  string  $type  "payment" or "processing". §2-6 documents
     *                        "processing" in its field table and "payment" in its worked example;
     *                        both are accepted because the value is not load-bearing.
     * @param  int|null  $code  -1 callback, 0 success, 1 cancelled, 800 invalid parameter, …
     * @param  list<array{code: int|null, message: string}>  $messages
     * @param  array<string, mixed>  $parameters  flat; canonical from here on
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public string $type,
        public ?int $code,
        public array $messages,
        public array $parameters,
        public array $raw,
    ) {}

    /**
     * Decode a POST body.
     *
     * Accepts `{callback: "<json>"}`, `{callback: [...]}`, and an already-flat
     * parameters array (which is what a hand-built test payload or a future
     * Sticpay change to plain form fields would look like).
     *
     * Never throws: a malformed envelope yields empty parameters, which then
     * fails signature verification and is rejected there rather than blowing up
     * the request.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $envelope = self::decode($input['callback'] ?? null);

        // No envelope at all — treat the body itself as the parameters.
        if ($envelope === null) {
            $flat = array_filter($input, fn (string $key): bool => $key !== 'callback', ARRAY_FILTER_USE_KEY);

            return new self('payment', null, [], self::normalizeParameters($flat), $input);
        }

        $code = isset($envelope['code']) && is_numeric($envelope['code']) ? (int) $envelope['code'] : null;

        return new self(
            type: is_string($envelope['type'] ?? null) ? $envelope['type'] : 'payment',
            code: $code,
            messages: self::normalizeMessages($envelope['message'] ?? null, $code),
            parameters: self::normalizeParameters(self::decode($envelope['parameters'] ?? null) ?? []),
            raw: $input,
        );
    }

    /**
     * The flat payload handed to the queue, the provider and the adapter.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->parameters + [
            self::RESERVED_PREFIX.'type' => $this->type,
            self::RESERVED_PREFIX.'code' => $this->code,
            self::RESERVED_PREFIX.'message' => $this->messages,
        ];
    }

    public function signature(): string
    {
        return (string) ($this->parameters['sign'] ?? '');
    }

    public function orderNo(): ?string
    {
        $orderNo = $this->parameters['order_no'] ?? $this->parameters['order_id'] ?? null;

        return $orderNo === null || $orderNo === '' ? null : (string) $orderNo;
    }

    public function interfaceVersion(): ?string
    {
        $version = $this->parameters['interface_version'] ?? null;

        return $version === null || $version === '' ? null : (string) $version;
    }

    /**
     * The first human-readable message, for error reporting.
     */
    public function firstMessage(): ?string
    {
        return $this->messages[0]['message'] ?? null;
    }

    /**
     * A value that may be a JSON string, an array already, or absent.
     *
     * @return array<string, mixed>|null
     */
    private static function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    /**
     * §2-3 and §2-5 send `message` as a plain string ("Success", "Cancel");
     * §2-4 sends a list of `{code, message}` objects. Both become a list.
     *
     * @return list<array{code: int|null, message: string}>
     */
    private static function normalizeMessages(mixed $message, ?int $envelopeCode): array
    {
        if (is_string($message)) {
            $decoded = self::decode($message);
            $message = $decoded ?? ($message === '' ? null : $message);
        }

        if ($message === null) {
            return [];
        }

        if (is_string($message)) {
            return [['code' => $envelopeCode, 'message' => $message]];
        }

        if (! is_array($message)) {
            return [];
        }

        // A single {code, message} object rather than a list of them.
        if (isset($message['message'])) {
            $message = [$message];
        }

        $messages = [];

        foreach ($message as $entry) {
            if (is_string($entry)) {
                $messages[] = ['code' => $envelopeCode, 'message' => $entry];

                continue;
            }

            if (is_array($entry) && isset($entry['message'])) {
                $messages[] = [
                    'code' => isset($entry['code']) && is_numeric($entry['code']) ? (int) $entry['code'] : null,
                    'message' => (string) $entry['message'],
                ];
            }
        }

        return $messages;
    }

    /**
     * String-keyed scalars only, with the reserved prefix stripped out so a
     * crafted payload cannot forge the envelope fields {@see toArray()} adds.
     *
     * @param  array<mixed, mixed>  $parameters
     * @return array<string, mixed>
     */
    private static function normalizeParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $value) {
            if (! is_string($key) || str_starts_with($key, self::RESERVED_PREFIX)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
