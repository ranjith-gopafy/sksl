<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Csrf;
use App\Helpers\Flash;
use App\Helpers\Response;

/**
 * Customer Authentication Middleware
 *
 * Ensures the requesting user has an active customer session AND that the
 * session is still valid: the account must exist and be active, and the
 * session must have been opened after the account's last password change
 * (a stolen session dies the moment the owner resets their password).
 */
class CustomerAuth
{
    public static function handle(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $reason = self::staleReason((int) $_SESSION['user_id']);
            if ($reason === null) {
                return;
            }
            self::clearCustomerSession();
            self::deny($reason, true);
        }

        self::deny('Please log in to continue.', false);
    }

    /**
     * Same check without the redirect: true when the customer session is live.
     * Used by views/pages that only want to know whether to show account links.
     */
    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        if (self::staleReason((int) $_SESSION['user_id']) !== null) {
            self::clearCustomerSession();
            return false;
        }
        return true;
    }

    /**
     * Why the current session must be discarded, or null if it is fine.
     */
    private static function staleReason(int $userId): ?string
    {
        // One indexed primary-key lookup per call; deliberately not cached so a
        // password change made earlier in the same request is seen immediately.
        $stmt = getDb()->prepare('SELECT status, password_changed_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        $reason = null;
        if (!$row) {
            $reason = 'Your account could not be found. Please sign in again.';
        } elseif (($row['status'] ?? 'active') !== 'active') {
            $reason = 'Your account has been deactivated. Please contact SKSL.';
        } elseif (!empty($row['password_changed_at'])) {
            $changedAt = strtotime((string) $row['password_changed_at']) ?: 0;
            $authAt    = (int) ($_SESSION['auth_at'] ?? 0);
            if ($authAt < $changedAt) {
                $reason = 'Your password was changed. Please sign in again with the new password.';
            }
        }

        return $reason;
    }

    public static function clearCustomerSession(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            $_SESSION['user_mobile'],
            $_SESSION['auth_at'],
            $_SESSION['intended_url']
        );
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            Csrf::rotate();
        }
    }

    private static function deny(string $message, bool $sessionEnded): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $isApi = str_contains($uri, '/api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($isApi) {
            Response::unauthorized($sessionEnded ? $message : 'Authentication required. Please log in.');
        }

        // Remember where the visitor was heading (path + query only — validated
        // again by safe_return_url() when the login succeeds).
        if (!$sessionEnded && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended_url'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        }

        Flash::set('error', $message);
        header('Location: ' . app_url('login'));
        exit;
    }
}
