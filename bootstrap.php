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

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

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
$isProduction   = ($appConfig['env'] ?? 'production') === 'production';
$requestIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);
// In production the session cookie is ALWAYS Secure — SESSION_SECURE=false can
// only relax it for local/testing environments (audit H7).
$secure   = $isProduction ? true : filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
$sameSite = 'Strict';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
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

/**
 * Per-request nonce for inline <script> tags. Every inline script in the
 * views must carry nonce="<?= csp_nonce() ?>" or the browser will block it.
 */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}

// ─── HTTP Security Headers ────────────────────────────────────────────────
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    // The legacy XSS auditor is removed from modern browsers and caused
    // vulnerabilities of its own; explicitly disable it and rely on CSP.
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(self "https://api.razorpay.com" "https://checkout.razorpay.com")');

    if ($requestIsHttps) {
        // Two years, include subdomains; add "preload" once the domain is submitted to hstspreload.org
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
    }

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self' 'nonce-" . csp_nonce() . "' https://checkout.razorpay.com",
        // Inline style attributes are used throughout the Tailwind markup and by Razorpay Checkout.
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: https:",
        // Razorpay Checkout opens an iframe and talks to api./lumberjack. sub-domains.
        "frame-src https://*.razorpay.com",
        "connect-src 'self' https://*.razorpay.com",
    ];
    if ($requestIsHttps) {
        $csp[] = 'upgrade-insecure-requests';
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
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
            // Align MySQL's NOW()/CURDATE() with the application clock (Asia/Kolkata)
            // so hold expiry and rate-limit windows behave the same on a UTC host.
            try {
                $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
                $pdo->exec("SET time_zone = '" . $offset . "'");
            } catch (Throwable $tzErr) {
                error_log('Could not set MySQL session time_zone: ' . $tzErr->getMessage());
            }
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
            'business' => require __DIR__ . '/config/business.php',
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
 * Resolve the base URL dynamically based on the active HTTP request,
 * falling back to config('app.url') for CLI/background workers.
 */
function app_base_url(): string
{
    $configuredUrl = rtrim((string) (config('app.url') ?? ''), '/');
    if ($configuredUrl !== '' && !str_contains($configuredUrl, 'localhost')) {
        return $configuredUrl;
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_ends_with($scriptDir, '/public') && !str_starts_with($requestUri, '/public')) {
            $scriptDir = rtrim(substr($scriptDir, 0, -7), '/');
        }
        $basePath = ($scriptDir !== '' && $scriptDir !== '/') ? $scriptDir : '';
        return $scheme . $_SERVER['HTTP_HOST'] . $basePath;
    }

    return $configuredUrl !== '' ? $configuredUrl : 'http://localhost';
}

/**
 * Return the full public URL for a static asset.
 * Example: asset('css/app.css') → http://localhost/sksl/public/css/app.css
 */
function asset(string $path): string
{
    return app_base_url() . '/' . ltrim($path, '/');
}

/**
 * Return the full URL for an application route path.
 * Example: app_url('login') → http://localhost/sksl/public/login
 */
function app_url(string $path = ''): string
{
    $base = app_base_url();
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

/**
 * Is a stored image reference acceptable for output?
 * Allowed: a relative path under public/images/ or public/uploads/ that ends
 * in .jpg/.jpeg/.png/.webp (no traversal), or an absolute https:// URL.
 */
function is_safe_image_path(?string $path): bool
{
    $path = trim((string) $path);
    if ($path === '' || str_contains($path, '..') || preg_match('/[\s<>"\'\\\\]/', $path)) {
        return false;
    }
    if (preg_match('#^(images|uploads)/[A-Za-z0-9_\-/]+\.(?i:jpe?g|png|webp)$#', $path)) {
        return true;
    }
    return (bool) preg_match('#^https://[^\s<>"\']+\.(?i:jpe?g|png|webp)(\?[^\s<>"\']*)?$#', $path)
        && filter_var($path, FILTER_VALIDATE_URL) !== false;
}

/**
 * Attribute-safe <img src> for an admin/DB-supplied image reference.
 * Anything outside the allow-list falls back to $fallback (a bundled asset).
 */
function safe_image(?string $path, string $fallback = 'images/services/spa.jpg'): string
{
    $path = trim((string) $path);
    if (!is_safe_image_path($path)) {
        $path = $fallback;
    }
    $url = str_starts_with($path, 'https://') ? $path : asset($path);
    return h($url);
}

/**
 * Attribute-safe <img src> for a service's stored image. Accepts the legacy
 * bare filename form ("spa.jpg") as well as images/… and uploads/… paths.
 */
function service_image(?string $image, string $fallback = 'images/services/spa.jpg'): string
{
    $image = trim((string) $image);
    if ($image !== '' && preg_match('/^[A-Za-z0-9_\-]+\.(?i:jpe?g|png|webp)$/', $image)) {
        $image = 'images/services/' . $image;
    }
    return safe_image($image, $fallback);
}

/**
 * Is a stored link acceptable as an href?
 * Allowed: a site-relative path ("/services", "/booking?service_id=3") or an
 * absolute http(s):// URL. Rejects protocol-relative ("//evil"), javascript:,
 * data:, and anything with control characters or markup.
 */
function is_safe_link(?string $link): bool
{
    $link = trim((string) $link);
    if ($link === '' || preg_match('/[\s<>"\'\\\\\x00-\x1F]/', $link)) {
        return false;
    }
    if (str_starts_with($link, '//')) {
        return false;
    }
    if ($link[0] === '/') {
        return (bool) preg_match('~^/[A-Za-z0-9_\-/.?=&%+#]*$~', $link);
    }
    if (preg_match('#^https?://#i', $link)) {
        return filter_var($link, FILTER_VALIDATE_URL) !== false;
    }
    return false;
}

/**
 * Attribute-safe href for an admin/DB-supplied link. Relative paths are
 * resolved through app_url(); unsafe values fall back to $fallback.
 */
function safe_link(?string $link, string $fallback = '/services'): string
{
    $link = trim((string) $link);
    if (!is_safe_link($link)) {
        $link = $fallback;
    }
    $url = preg_match('#^https?://#i', $link) ? $link : app_url(ltrim($link, '/'));
    return h($url);
}
