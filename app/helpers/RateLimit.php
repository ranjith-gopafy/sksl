<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Session-based Rate Limiter
 *
 * Simple, works without Redis/DB. Sufficient for customer-facing endpoints.
 * Admin OTP rate-limiting (stricter) is handled in AdminOtpService using DB.
 */
class RateLimit
{
    /**
     * Record an attempt and check if the key is still allowed.
     *
     * @param  string $key          Unique rate-limit key (e.g. 'login')
     * @param  int    $maxAttempts  Max attempts before lockout
     * @param  int    $decaySeconds Window and lockout duration in seconds
     * @return bool   true = request allowed, false = rate-limited
     */
    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $attemptsKey = '_rl_attempts_' . $key;
        $lockoutKey  = '_rl_lockout_'  . $key;

        // Check for active lockout
        if (isset($_SESSION[$lockoutKey])) {
            if ($_SESSION[$lockoutKey] > time()) {
                return false; // Still locked
            }
            // Lockout expired — clear state
            unset($_SESSION[$lockoutKey], $_SESSION[$attemptsKey]);
        }

        // Initialize or reset window
        if (
            !isset($_SESSION[$attemptsKey]) ||
            (time() - ($_SESSION[$attemptsKey]['window_start'] ?? 0)) >= $decaySeconds
        ) {
            $_SESSION[$attemptsKey] = ['count' => 0, 'window_start' => time()];
        }

        $_SESSION[$attemptsKey]['count']++;

        if ($_SESSION[$attemptsKey]['count'] > $maxAttempts) {
            $_SESSION[$lockoutKey] = time() + $decaySeconds;
            unset($_SESSION[$attemptsKey]);
            return false;
        }

        return true;
    }

    /**
     * Clear rate-limit state for a key.
     * Call after a successful login to reset the counter.
     */
    public static function clear(string $key): void
    {
        unset(
            $_SESSION['_rl_attempts_' . $key],
            $_SESSION['_rl_lockout_'  . $key]
        );
    }

    /**
     * Get remaining lockout seconds. Returns 0 if not locked.
     */
    public static function remainingSeconds(string $key): int
    {
        $lockoutKey = '_rl_lockout_' . $key;
        if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
            return (int) ceil($_SESSION[$lockoutKey] - time());
        }
        return 0;
    }

    /**
     * Check if a key is currently locked out (without incrementing counter).
     */
    public static function isLocked(string $key): bool
    {
        $lockoutKey = '_rl_lockout_' . $key;
        return isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time();
    }
}
