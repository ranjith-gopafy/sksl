<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Flash;
use App\Helpers\Response;

/**
 * Customer Authentication Middleware
 *
 * Ensures the requesting user has an active customer session.
 */
class CustomerAuth
{
    public static function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $isApi = str_starts_with($uri, '/api')
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

            if ($isApi) {
                Response::unauthorized('Authentication required. Please log in.');
            }

            // Save intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? app_url('services');

            Flash::set('error', 'Please log in to continue.');
            header('Location: ' . app_url('login'));
            exit;
        }
    }
}
