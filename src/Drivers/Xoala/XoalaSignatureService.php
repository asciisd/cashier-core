<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

/**
 * Every MD5 checksum Xoala asks for, in one place.
 *
 * Xoala (a white-label of the Paymentz platform) authenticates each request and
 * each callback with an MD5 over pipe-joined values ending in the merchant's
 * secure key. The compositions differ per operation and are NOT interchangeable:
 *
 *   checkout : memberId|totype|amount|merchantTransactionId|merchantRedirectUrl|secureKey
 *   callback : paymentId|merchantTransactionId|amount|status|secureKey
 *   inquiry  : memberId|secureKey|<id>
 *
 * The secure key is the last field of the first two and the SECOND field of the
 * third — a detail worth re-reading before "tidying" the argument order.
 */
final class XoalaSignatureService
{
    public function __construct(
        private readonly string $memberId,
        private readonly string $secureKey,
    ) {}

    /**
     * The checksum accompanying a Standard Checkout form POST.
     *
     * `$amount` is a pre-formatted string, never a float: see amount().
     */
    public function forCheckout(
        string $totype,
        string $amount,
        string $merchantTransactionId,
        string $merchantRedirectUrl,
    ): string {
        return $this->hash([
            $this->memberId,
            $totype,
            $amount,
            $merchantTransactionId,
            $merchantRedirectUrl,
            $this->secureKey,
        ]);
    }

    /**
     * The checksum on a notification callback or a redirect-back POST.
     *
     * `$status` is the SHORT status — Y, N, P, 3D or C. The documentation's
     * worked example is `77251|011E1D8A5C034|156.00|N|<secret>`, and a callback
     * carries the short value in `transactionStatus` alongside a long `status`
     * such as `capturesuccess`. Signing the long one silently fails every
     * callback.
     */
    public function forCallback(
        string $paymentId,
        string $merchantTransactionId,
        string $amount,
        string $status,
    ): string {
        return $this->hash([
            $paymentId,
            $merchantTransactionId,
            $amount,
            $status,
            $this->secureKey,
        ]);
    }

    /**
     * Whether this payload's `checksum` was produced by our secure key.
     *
     * The amount is taken as the RAW STRING Xoala sent. Re-formatting it to
     * make a digest match would be forging agreement with ourselves — the whole
     * point of the check is that their bytes and ours agree.
     *
     * `transactionStatus` is preferred over `status` because a notification
     * carries both and only the former is short; a redirect-back POST carries
     * the short value under `status` and no `transactionStatus` at all.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $received = strtolower(trim((string) ($payload['checksum'] ?? '')));

        if ($received === '') {
            return false;
        }

        $status = (string) ($payload['transactionStatus'] ?? $payload['status'] ?? '');

        $expected = $this->forCallback(
            (string) ($payload['paymentId'] ?? ''),
            (string) ($payload['merchantTransactionId'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            $status,
        );

        return hash_equals($expected, $received);
    }

    /**
     * The checksum for a backoffice inquiry.
     *
     * Documented as `memberId|secureKey|paymentId`, but an `idType=MID` lookup
     * sends no paymentId — so we sign the id actually being sent, which is our
     * own merchantTransactionId. See the spec's "Assumptions to confirm".
     */
    public function forInquiry(string $id): string
    {
        return $this->hash([$this->memberId, $this->secureKey, $id]);
    }

    /**
     * The amount format every Xoala checksum is computed over: `N11
     * [0-9]{1,8}\.[0-9]{2}`.
     *
     * Lives here rather than on the provider because the FORMAT is a property
     * of the signature, not of the request — `50` and `50.00` hash differently,
     * so one helper has to serve the field set, the digest and the bridge alike.
     */
    public static function amount(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<string>  $parts
     */
    private function hash(array $parts): string
    {
        return md5(implode('|', $parts));
    }
}
