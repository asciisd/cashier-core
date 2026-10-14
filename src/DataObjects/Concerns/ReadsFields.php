<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects\Concerns;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Strict readers for host-supplied arrays. A wrong type is an error, never
 * coerced: these values are sent to a PSP, and a silently mangled field pays
 * the wrong person or fails compliance. `$path` prefixes the key in messages
 * (`address.` → "address.city is required").
 */
trait ReadsFields
{
    /** @param  array<string, mixed>  $data */
    private static function requiredString(array $data, string $key, string $path = ''): string
    {
        return self::optionalString($data, $key, $path)
            ?? throw new InvalidArgumentException("{$path}{$key} is required.");
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalString(array $data, string $key, string $path = ''): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        // Phone numbers and postal codes often arrive as ints from a form.
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("{$path}{$key} must be a string.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param  array<string, mixed>  $data */
    private static function requiredCountry(array $data, string $key, string $path = ''): string
    {
        return self::optionalCountry($data, $key, $path)
            ?? throw new InvalidArgumentException("{$path}{$key} is required.");
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalCountry(array $data, string $key, string $path = ''): ?string
    {
        $value = self::optionalString($data, $key, $path);

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);

        if (! preg_match('/^[A-Z]{3}$/', $value)) {
            throw new InvalidArgumentException("{$path}{$key} must be a three-letter ISO country code.");
        }

        return $value;
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalDate(array $data, string $key, string $path = ''): ?string
    {
        $value = self::optionalString($data, $key, $path);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$path}{$key} must be a Y-m-d date.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function optionalArray(array $data, string $key, string $path = ''): ?array
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$path}{$key} must be an object.");
        }

        return $value;
    }
}
