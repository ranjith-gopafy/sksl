<?php

/**
 * SKSL Application Bootstrap
 *
 * Loaded by public/index.php before routing.
 * Responsibilities:
 *  - Load .env
 *  - Configure error handling
 *  - Set timezone
 *  - Configure session
 *  - Load application configuration
 *  - Create PDO database connection (available via getDb())
 *  - Register global view helper functions
 */

declare(strict_types=1);

// ─── Autoload (Composer PSR-4 + vendor packages) ──────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

// ─── Load .env ────────────────────────────────────────────────────────────
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ─── Load config ──────────────────────────────────────────────────────────
$appConfig = require __DIR__ . '/config/app.php';
$dbConfig  = require __DIR__ . '/config/database.php';

// ─── Environment-based error reporting ────────────────────────────────────
if ($appConfig['debug'] === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ─── Timezone ─────────────────────────────────────────────────────────────
date_default_timezone_set($appConfig['timezone']);

// ─── Session configuration ────────────────────────────────────────────────
// Must be called before session_start().
$secure   = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
$sameSite = 'Strict';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
    session_start();
}

// ─── Database connection ───────────────────────────────────────────────────
// Returns a shared PDO instance. Store in $db for use across the app.
function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/config/database.php';
        $dsn    = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            // Never expose connection details in the response
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            // In production this would render a safe error page; for now, die safely
            die(json_encode([
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again shortly.',
            ]));
        }
    }

    return $pdo;
}

// ─── Global app config accessor ───────────────────────────────────────────
function config(string $key, mixed $default = null): mixed
{
    static $all = null;

    if ($all === null) {
        $all = [
            'app'      => require __DIR__ . '/config/app.php',
            'database' => require __DIR__ . '/config/database.php',
            'mail'     => require __DIR__ . '/config/mail.php',
            'razorpay' => require __DIR__ . '/config/razorpay.php',
        ];
    }

    $parts  = explode('.', $key);
    $value  = $all;

    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $default;
        }
    }

    return $value;
}

// ─── Global view helpers ───────────────────────────────────────────────────

/**
 * Escape a value for safe HTML output (XSS prevention).
 * Use on ALL user-supplied values before echoing into HTML.
 */
function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Return the full public URL for a static asset.
 * Example: asset('css/app.css') → http://localhost/sksl/public/css/app.css
 */
function asset(string $path): string
{
    return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
}

/**
 * Return the full URL for an application route path.
 * Example: app_url('login') → http://localhost/sksl/public/login
 */
function app_url(string $path = ''): string
{
    return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
}
