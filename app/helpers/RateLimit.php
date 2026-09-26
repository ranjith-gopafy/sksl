<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Database-backed rate limiter (table: rate_limits).
 *
 * Counters live in MySQL, so they survive across sessions, browsers, and PHP
 * workers: an attacker cannot reset the counter by dropping the session cookie
 * (the weakness of the previous $_SESSION implementation, audit H4).
 *
 * Every protected action is limited on two axes — the targeted account
 * (email) and the client IP — so neither credential-stuffing one account from
 * many IPs nor spraying many accounts from one IP goes unthrottled.
 *
 * If the database is unavailable the limiter falls back to the session so the
 * site still degrades gracefully rather than either failing open or locking
 * everybody out.
 */
class RateLimit
{
    /** Rows older than this (and not locked) are purged opportunistically. */
    private const PURGE_AFTER_SECONDS = 86400;

    private static ?\PDO $db = null;
    private static bool $dbUnavailable = false;

    /**
     * Record an attempt and check if the key is still allowed.
     *
     * @param  string $key          Unique rate-limit key (use RateLimit::key())
     * @param  int    $maxAttempts  Max attempts inside the window before lockout
     * @param  int    $decaySeconds Window length and lockout duration in seconds
     * @return bool   true = request allowed, false = rate-limited
     */
    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $db = self::db();
        if ($db === null) {
            return self::sessionAttempt($key, $maxAttempts, $decaySeconds);
        }

        try {
            // Single atomic upsert: keep counting inside the window, restart the
            // window when it has expired, and never touch a row that is locked.
            $stmt = $db->prepare(
                "INSERT INTO rate_limits (rl_key, attempts, window_start, locked_until)
                 VALUES (:k, 1, NOW(), NULL)
                 ON DUPLICATE KEY UPDATE
                    attempts = IF(locked_until IS NOT NULL AND locked_until > NOW(),
                                  attempts,
                                  IF(window_start <= NOW() - INTERVAL :decay1 SECOND, 1, attempts + 1)),
                    window_start = IF(locked_until IS NOT NULL AND locked_until > NOW(),
                                  window_start,
                                  IF(window_start <= NOW() - INTERVAL :decay2 SECOND, NOW(), window_start)),
                    locked_until = IF(locked_until IS NOT NULL AND locked_until > NOW(), locked_until, NULL)"
            );
            $stmt->execute([':k' => $key, ':decay1' => $decaySeconds, ':decay2' => $decaySeconds]);

            $row = self::fetch($db, $key);
            if ($row === null) {
                return true;
            }
            if ($row['lock_remaining'] > 0) {
                return false;
            }
            if ((int) $row['attempts'] > $maxAttempts) {
                $lock = $db->prepare(
                    'UPDATE rate_limits SET locked_until = NOW() + INTERVAL :decay SECOND WHERE rl_key = :k'
                );
                $lock->execute([':decay' => $decaySeconds, ':k' => $key]);
                return false;
            }

            self::maybePurge($db);
            return true;
        } catch (\PDOException $e) {
            error_log('RateLimit: database error, falling back to session limiter: ' . $e->getMessage());
            self::$dbUnavailable = true;
            return self::sessionAttempt($key, $maxAttempts, $decaySeconds);
        }
    }

    /**
     * Clear rate-limit state for a key (e.g. after a successful login).
     */
    public static function clear(string $key): void
    {
        unset($_SESSION['_rl_attempts_' . $key], $_SESSION['_rl_lockout_' . $key]);

        $db = self::db();
        if ($db === null) {
            return;
        }
        try {
            $stmt = $db->prepare('DELETE FROM rate_limits WHERE rl_key = ?');
            $stmt->execute([$key]);
        } catch (\PDOException $e) {
            error_log('RateLimit: clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Remaining lockout seconds. Returns 0 if not locked.
     */
    public static function remainingSeconds(string $key): int
    {
        $db = self::db();
        if ($db !== null) {
            try {
                $row = self::fetch($db, $key);
                return $row === null ? 0 : max(0, (int) $row['lock_remaining']);
            } catch (\PDOException $e) {
                // fall through to session
            }
        }
        $lockoutKey = '_rl_lockout_' . $key;
        if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
            return (int) ceil($_SESSION[$lockoutKey] - time());
        }
        return 0;
    }

    /**
     * Is the key currently locked out (without incrementing the counter)?
     */
    public static function isLocked(string $key): bool
    {
        return self::remainingSeconds($key) > 0;
    }

    /**
     * Build a namespaced key: scope + hashed identifier (email, IP, ...).
     */
    public static function key(string $scope, string $identifier): string
    {
        return $scope . ':' . hash('sha256', strtolower(trim($identifier)));
    }

    /**
     * Client IP for per-IP limits. Only REMOTE_ADDR is trusted: X-Forwarded-For
     * is attacker-controlled unless a trusted proxy strips it.
     */
    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Convenience: check an action against both its account key and the IP key.
     * Returns the number of seconds to wait, or 0 when allowed.
     */
    public static function throttle(string $scope, string $identifier, int $maxPerIdentifier, int $maxPerIp, int $decaySeconds): int
    {
        $ipKey = self::key($scope . '_ip', self::clientIp());
        $idKey = self::key($scope, $identifier);

        $ipOk = self::attempt($ipKey, $maxPerIp, $decaySeconds);
        $idOk = self::attempt($idKey, $maxPerIdentifier, $decaySeconds);

        if ($ipOk && $idOk) {
            return 0;
        }
        return max(1, self::remainingSeconds($ipKey), self::remainingSeconds($idKey));
    }

    /**
     * Read-only check for a scope: seconds the caller must still wait, or 0.
     * Use before verifying credentials; pair with recordFailure() afterwards.
     */
    public static function retryAfter(string $scope, string $identifier): int
    {
        return max(
            self::remainingSeconds(self::key($scope . '_ip', self::clientIp())),
            self::remainingSeconds(self::key($scope, $identifier))
        );
    }

    /**
     * Count a failed attempt (wrong password / wrong OTP). The account locks
     * once it reaches $maxPerIdentifier failures, the IP once it reaches
     * $maxPerIp, for $decaySeconds. Successful attempts are never counted, so
     * legitimate traffic from a shared IP is not penalised.
     * Returns the lockout seconds now in force (0 if still allowed).
     */
    public static function recordFailure(string $scope, string $identifier, int $maxPerIdentifier, int $maxPerIp, int $decaySeconds): int
    {
        $ipKey = self::key($scope . '_ip', self::clientIp());
        $idKey = self::key($scope, $identifier);

        // attempt() locks when count > max, so pass max-1 to lock *at* max.
        $ipOk = self::attempt($ipKey, max(0, $maxPerIp - 1), $decaySeconds);
        $idOk = self::attempt($idKey, max(0, $maxPerIdentifier - 1), $decaySeconds);

        if ($ipOk && $idOk) {
            return 0;
        }
        return max(1, self::remainingSeconds($ipKey), self::remainingSeconds($idKey));
    }

    /** Test/ops helper: remove every counter for a scope prefix. */
    public static function clearScope(string $scope): void
    {
        $db = self::db();
        if ($db === null) {
            return;
        }
        try {
            $stmt = $db->prepare('DELETE FROM rate_limits WHERE rl_key LIKE ?');
            $stmt->execute([str_replace(['%', '_'], ['\\%', '\\_'], $scope) . ':%']);
        } catch (\PDOException $e) {
            error_log('RateLimit: clearScope failed: ' . $e->getMessage());
        }
    }

    // ── internals ────────────────────────────────────────────────────────────

    private static function db(): ?\PDO
    {
        if (self::$dbUnavailable) {
            return null;
        }
        if (self::$db === null) {
            try {
                self::$db = getDb();
            } catch (\Throwable $e) {
                error_log('RateLimit: database unavailable: ' . $e->getMessage());
                self::$dbUnavailable = true;
                return null;
            }
        }
        return self::$db;
    }

    /** @return array{attempts: int, lock_remaining: int}|null */
    private static function fetch(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare(
            'SELECT attempts,
                    GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), locked_until), 0)) AS lock_remaining
             FROM rate_limits WHERE rl_key = ?'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? ['attempts' => (int) $row['attempts'], 'lock_remaining' => (int) $row['lock_remaining']] : null;
    }

    private static function maybePurge(\PDO $db): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }
        $db->prepare(
            'DELETE FROM rate_limits
             WHERE window_start < NOW() - INTERVAL :age SECOND
               AND (locked_until IS NULL OR locked_until < NOW())'
        )->execute([':age' => self::PURGE_AFTER_SECONDS]);
    }

    /** Legacy session implementation, used only when the database is down. */
    private static function sessionAttempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        $attemptsKey = '_rl_attempts_' . $key;
        $lockoutKey  = '_rl_lockout_' . $key;

        if (isset($_SESSION[$lockoutKey])) {
            if ($_SESSION[$lockoutKey] > time()) {
                return false;
            }
            unset($_SESSION[$lockoutKey], $_SESSION[$attemptsKey]);
        }

        if (!isset($_SESSION[$attemptsKey]) || (time() - ($_SESSION[$attemptsKey]['window_start'] ?? 0)) >= $decaySeconds) {
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
}
