<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Guest Middleware
 *
 * Redirects already-logged-in customers away from login and registration pages.
 */
class GuestMiddleware
{
    public static function handle(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . app_url('services'));
            exit;
        }
    }
}
