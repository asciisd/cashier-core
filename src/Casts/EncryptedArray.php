<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * `encrypted:array` that tolerates rows written before encryption was enabled.
 *
 * Laravel's built-in `encrypted:array` is all-or-nothing: the moment the cast
 * is applied, every pre-existing cleartext row throws `DecryptException` on
 * read. A host adopting the engine on top of years of plaintext payloads would
 * therefore have to backfill and flip in the same instant — impossible across
 * a rolling deploy, and unrecoverable if the backfill dies half way.
 *
 * So reads accept both shapes and writes obey the flag:
 *
 * - a raw value that is valid JSON is a legacy cleartext row → decoded as-is;
 * - anything else is ciphertext → decrypted, then decoded.
 *
 * The fallback is keyed on "looks like JSON", not on "decryption failed", so a
 * genuine key-rotation break still surfaces as a `DecryptException` instead of
 * being silently swallowed.
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, array<array-key, mixed>|null>
 */
class EncryptedArray implements CastsAttributes
{
    /**
     * @param  string  $flag  Key under `cashier-core.security` that decides whether writes encrypt.
     */
    public function __construct(
        private readonly string $flag = 'encrypt_provider_payload',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = json_decode(Crypt::decryptString((string) $value), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $json = json_encode($value);

        return [$key => $this->encrypts() ? Crypt::encryptString($json) : $json];
    }

    /**
     * Read at call time, not construction time — hosts flip this per environment
     * and the test suite flips it per test.
     */
    private function encrypts(): bool
    {
        return (bool) config("cashier-core.security.{$this->flag}", true);
    }
}
