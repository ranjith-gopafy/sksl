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

    /**
     * Send the JSON answer, close the connection, then run $after (slow work such as email over SMTP).
     */
    public static function jsonThen(array $data, callable $after, int $status = 200): never
    {
        ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo $body;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        $after();
        exit;
    }

    /**
     * Redirect first, then run $after (slow work such as email over SMTP).
     */
    public static function redirectThen(string $url, callable $after): never
    {
        ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $url);
        header('Content-Length: 1');
        header('Connection: close');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo ' ';
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        $after();
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
