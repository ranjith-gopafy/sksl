<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CSRF Token Manager
 *
 * Session-based token. Generated once per session and validated on
 * all state-changing requests (POST, PUT, DELETE).
 * Uses hash_equals() to prevent timing-attack comparisons.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate and return the CSRF token for this session.
     * Creates a new one if not yet set.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify that the given token matches the session token.
     * Constant-time comparison prevents timing attacks.
     */
    public static function verify(string $token): bool
    {
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if ($sessionToken === '' || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    /**
     * Return an HTML hidden input field containing the CSRF token.
     * Use inside every POST form.
     */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }
}
