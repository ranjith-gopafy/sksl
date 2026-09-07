<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * JSON Response Helper
 *
 * All methods terminate execution (never return).
 * Use for API endpoints that return JSON.
 */
class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, array $data = [], int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, array $errors = [], int $status = 400): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): never
    {
        self::json(['success' => false, 'message' => $message], 401);
    }

    public static function forbidden(string $message = 'Forbidden.'): never
    {
        self::json(['success' => false, 'message' => $message], 403);
    }

    public static function notFound(string $message = 'Not found.'): never
    {
        self::json(['success' => false, 'message' => $message], 404);
    }
}
