<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Myfatoorah;

/**
 * MyFatoorah webhook signatures.
 *
 * Nothing about this is the usual `hmac(rawBody, secret)`. MyFatoorah signs a
 * string it builds from a NAMED SUBSET of fields in a PRESCRIBED ORDER:
 *
 *   1. Take the fields for this event code, in the documented order.
 *   2. Join as `key=value,key2=value2` — no spaces, comma-separated. The keys
 *      are the dotted paths (`Invoice.Id`), not the JSON nesting.
 *   3. A null value becomes the empty string and the KEY STAYS.
 *   4. base64(hmac_sha256(string, secret)) — binary HMAC then base64, not hex
 *      — compared against the `MyFatoorah-Signature` header with hash_equals.
 *
 * Signing the raw body, the whole `Data` object, or the fields in payload
 * order all fail — and fail IDENTICALLY, so the error tells you nothing about
 * which mistake you made. See the myfatoorah skill, references/pitfalls.md
 * entries 3-4.
 *
 * Only PAYMENT_STATUS_CHANGED is implemented, because it is the only event
 * this driver acts on; every other event is ACKed and dropped by the
 * controller before any signature work happens. The builder itself is
 * event-agnostic, so adding an event later is a new SIGNED_FIELDS entry and
 * no new logic. The remaining six field lists are tabulated in the design
 * spec, docs/superpowers/specs/2026-08-19-myfatoorah-driver-design.md.
 */
final class MyfatoorahSignatureService
{
    public const PAYMENT_STATUS_CHANGED = 1;

    /**
     * Dotted paths into the webhook's `Data` object, in MyFatoorah's order.
     * The order is load-bearing: it is neither alphabetical nor payload order.
     *
     * @var array<int, list<string>>
     */
    private const SIGNED_FIELDS = [
        self::PAYMENT_STATUS_CHANGED => [
            'Invoice.Id',
            'Invoice.Status',
            'Transaction.Status',
            'Transaction.PaymentId',
            'Invoice.ExternalIdentifier',
        ],
    ];

    public function __construct(private readonly string $secret) {}

    public function supports(int $eventCode): bool
    {
        return isset(self::SIGNED_FIELDS[$eventCode]);
    }

    /**
     * The exact string MyFatoorah signed, built from the event's field list.
     *
     * @param  array<string, mixed>  $data  the webhook's `Data` object
     */
    public function canonical(int $eventCode, array $data): string
    {
        $pairs = [];

        foreach (self::SIGNED_FIELDS[$eventCode] ?? [] as $path) {
            $value = data_get($data, $path);

            // Null AND absent both become the empty string with the key kept.
            $pairs[] = $path.'='.($value === null ? '' : (string) $value);
        }

        return implode(',', $pairs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sign(int $eventCode, array $data): string
    {
        return base64_encode(
            hash_hmac('sha256', $this->canonical($eventCode, $data), $this->secret, true)
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function verify(int $eventCode, array $data, string $signature): bool
    {
        // Defence in depth, independent of whoever constructed this service.
        // hash_hmac with an empty key is a perfectly valid HMAC, and every
        // input to the canonical string is public, so under a blank secret an
        // attacker can compute a signature that verifies. Nothing verifies
        // under a blank key.
        if ($this->secret === '') {
            return false;
        }

        return hash_equals($this->sign($eventCode, $data), $signature);
    }
}
