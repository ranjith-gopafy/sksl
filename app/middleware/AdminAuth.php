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
