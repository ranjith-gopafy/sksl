<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Flash Messages
 *
 * Stores messages in the session that survive exactly one request.
 * Usage:
 *   Set before redirect: Flash::set('success', 'Account created.');
 *   Get in view:         $msg = Flash::get('success');
 */
class Flash
{
    private const SESSION_KEY = '_flash';

    public static function set(string $key, string $message): void
    {
        $_SESSION[self::SESSION_KEY][$key] = $message;
    }

    /** Retrieve and remove a flash message. Returns null if not set. */
    public static function get(string $key): ?string
    {
        $message = $_SESSION[self::SESSION_KEY][$key] ?? null;
        unset($_SESSION[self::SESSION_KEY][$key]);
        return $message;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[self::SESSION_KEY][$key]);
    }
}
