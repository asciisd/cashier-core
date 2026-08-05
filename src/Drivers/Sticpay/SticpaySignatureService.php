<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Sticpay;

use InvalidArgumentException;

/**
 * Sticpay request and callback signatures.
 *
 * The digest is an HTTP-query-shaped string of the operation's required fields
 * **in the vendor's documented order**, with the API key appended last:
 *
 *     merchant_email=..&order_no=..&order_time=..&order_amount=..&order_currency=..&key=API_KEY
 *
 * then MD5 or SHA-256, lowercased. The order is part of the contract — this is
 * the one thing Sticpay does differently from {@see PayportSignatureService},
 * which sorts by key. Getting it wrong produces a valid-looking hash that
 * Sticpay rejects with error 809, so the orders live in constants and
 * {@see canonical()} throws rather than quietly signing a short string.
 *
 * Because the key is appended *last*, a length-extension attack does not apply
 * and supporting MD5 (Sticpay's default) is not a weakness here.
 */
final class SticpaySignatureService
{
    /** Deposit — §2-2. */
    public const PAY = ['merchant_email', 'order_no', 'order_time', 'order_amount', 'order_currency'];

    /** Transaction callback — §2-6 B. */
    public const CALLBACK = ['merchant_email', 'order_no', 'order_time', 'order_amount', 'order_currency', 'transaction_code', 'transaction_time'];

    /** Refund — §4.2. */
    public const REFUND_BY_TRANSACTION = ['merchant', 'transaction_code', 'request_datetime'];

    public const REFUND_BY_ORDER = ['merchant', 'order_id', 'request_datetime'];

    /**
     * Transaction detail / resend callback — §6.2.
     *
     * §5.2 documents the same lookup *without* `interface_version` while §6.2
     * includes it. The two sections disagree; requests are signed with this
     * form and fall back to the §5 form on error 809.
     * {@see DETAIL_BY_ORDER_LEGACY}
     */
    public const DETAIL_BY_ORDER = ['merchant', 'order_id', 'request_datetime', 'interface_version'];

    public const DETAIL_BY_TRANSACTION = ['merchant', 'transaction_code', 'request_datetime', 'interface_version'];

    /** The §5.2 reading of the same lookup, kept for the one-shot 809 retry. */
    public const DETAIL_BY_ORDER_LEGACY = ['merchant', 'order_id', 'request_datetime'];

    public const DETAIL_BY_TRANSACTION_LEGACY = ['merchant', 'transaction_code', 'request_datetime'];

    /** Withdraw — §3.2. `interface_version` is always last before the key. */
    public const WITHDRAW = ['merchant', 'customer', 'amount', 'currency_code', 'interface_version'];

    public const WITHDRAW_WITH_ORDER = ['merchant', 'customer', 'amount', 'currency_code', 'order_id', 'interface_version'];

    public const MD5 = 'MD5';

    public const SHA256 = 'SHA256';

    /**
     * @param  string  $signType  Must match the Encryption Type configured
     *                            merchant-side in Sticpay's mypage API settings. A mismatch is
     *                            rejected with error 809 on every request.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $signType = self::MD5,
    ) {}

    /**
     * The exact string that gets hashed, for a given operation.
     *
     * @param  array<string, mixed>  $params
     * @param  list<string>  $order
     *
     * @throws InvalidArgumentException when a field the operation requires is absent
     */
    public function canonical(array $params, array $order): string
    {
        $pairs = [];

        foreach ($order as $field) {
            $value = $params[$field] ?? null;

            if ($value === null || $value === '') {
                throw new InvalidArgumentException("Sticpay signature is missing required field [{$field}].");
            }

            $pairs[] = $field.'='.$value;
        }

        $pairs[] = 'key='.$this->apiKey;

        return implode('&', $pairs);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>  $order
     */
    public function sign(array $params, array $order, ?string $signType = null): string
    {
        return $this->hash($this->canonical($params, $order), $signType ?? $this->signType);
    }

    /**
     * Verify the `sign` field of a transaction callback.
     *
     * Tolerant of the hash type. §2-6 B states the callback digest is MD5, but
     * the callback carries its own `sign_type` field and §1 says the merchant
     * chooses the encryption type in mypage — the three statements only agree
     * if either type may arrive. The payload's own claim is tried first, then
     * the configured type, then the remaining one.
     *
     * @param  array<string, mixed>  $parameters  the flat callback parameters
     */
    public function verifyCallback(array $parameters): bool
    {
        $received = (string) ($parameters['sign'] ?? '');

        if ($received === '') {
            return false;
        }

        unset($parameters['sign']);

        try {
            $canonical = $this->canonical($parameters, self::CALLBACK);
        } catch (InvalidArgumentException) {
            // A callback missing a signed field cannot be verified, and an
            // unverifiable callback is a rejected one.
            return false;
        }

        foreach ($this->candidateSignTypes($parameters['sign_type'] ?? null) as $signType) {
            if (hash_equals($this->hash($canonical, $signType), $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The hash types to try, most-likely first and de-duplicated.
     *
     * @return list<string>
     */
    private function candidateSignTypes(mixed $claimed): array
    {
        $claimed = is_string($claimed) ? strtoupper(trim($claimed)) : null;

        $candidates = array_filter(
            [$claimed, strtoupper($this->signType), self::MD5, self::SHA256],
            fn (?string $type): bool => $type === self::MD5 || $type === self::SHA256,
        );

        return array_values(array_unique($candidates));
    }

    private function hash(string $canonical, string $signType): string
    {
        return strtoupper($signType) === self::SHA256
            ? strtolower(hash('sha256', $canonical))
            : strtolower(md5($canonical));
    }
}
