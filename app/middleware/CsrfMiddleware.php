<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Csrf;
use App\Helpers\Flash;
use App\Helpers\Response;

/**
 * CSRF Protection Middleware
 *
 * Enforces valid CSRF tokens on all state-changing requests (POST, PUT, DELETE, PATCH).
 * Webhooks with signature verification (e.g. Razorpay webhook) are excluded.
 */
class CsrfMiddleware
{
    /**
     * Paths excluded from CSRF verification (external webhooks).
     * @var string[]
     */
    private const EXEMPT_PATHS = [
        '/api/payment/webhook',
    ];

    public static function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        // Check if path is exempt
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($uri === $exempt) {
                return;
            }
        }

        // Retrieve token from form field or HTTP header
        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!is_string($token) || !Csrf::verify($token)) {
            $isApi = str_starts_with($uri, '/api')
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

            if ($isApi) {
                Response::forbidden('Invalid or expired CSRF token. Please refresh and try again.');
            }

            Flash::set('error', 'Your session or security token expired. Please try again.');
            // Only bounce back to a page on this site — never to an arbitrary Referer.
            header('Location: ' . safe_return_url($_SERVER['HTTP_REFERER'] ?? null, ''));
            exit;
        }
    }
}
