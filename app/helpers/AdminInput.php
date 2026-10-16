<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Strict parsers for admin form fields. Each returns the normalised value, or
 * null when the input is not acceptable, so controllers never write raw POST
 * strings into TIME / DATE / ENUM columns.
 */
final class AdminInput
{
    /** "HH:MM" or "HH:MM:SS" → "HH:MM:SS". */
    public static function time(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            return null;
        }
        return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
    }

    /** Real calendar date "YYYY-MM-DD" or null. */
    public static function date(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return $value;
    }

    /** Weekday 0 (Sunday) – 6 (Saturday) or null. */
    public static function weekday(mixed $value): ?int
    {
        if ($value === null || $value === '' || !preg_match('/^[0-6]$/', (string) $value)) {
            return null;
        }
        return (int) $value;
    }

    /** Value if it is one of the allowed options, otherwise null. */
    public static function oneOf(string $value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? $value : null;
    }
}
