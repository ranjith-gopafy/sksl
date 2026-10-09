<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Flash;
use App\Helpers\Response;

/**
 * Admin Authentication Middleware
 *
 * Ensures the requesting user has an active admin session.
 */
class AdminAuth
{
    public static function handle(): void
    {
        // A session is only as good as the account behind it: an admin who was set to inactive
        // (or deleted) must lose access on their very next request, not when the cookie expires.
        if (!empty($_SESSION['admin_id'])) {
            $stmt = getDb()->prepare('SELECT status FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['admin_id']]);
            if (($stmt->fetchColumn() ?: '') !== 'active') {
                foreach (['admin_id', 'admin_name', 'admin_email', 'admin_auth_at'] as $key) {
                    unset($_SESSION[$key]);
                }
            }
        }

        if (empty($_SESSION['admin_id'])) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $isApi = str_contains($uri, '/api');

            if ($isApi) {
                Response::unauthorized('Admin authentication required.');
            }

            Flash::set('error', 'Admin login required.');
            header('Location: ' . app_url('admin/login'));
            exit;
        }
    }
}
