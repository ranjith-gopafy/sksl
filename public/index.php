<?php

/**
 * SKSL — Front Controller
 *
 * This is the single entry point for all web requests.
 * Apache .htaccess routes all requests here.
 */

declare(strict_types=1);

// Serve static assets directly when running via PHP built-in web server
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path !== '/' && file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
        return false;
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Router.php';

use App\Controllers\HomeController;
use App\Controllers\ServiceController;
use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Middleware\CsrfMiddleware;

// ─── CSRF Protection ──────────────────────────────────────────────────────
CsrfMiddleware::handle();

// ─── Router ───────────────────────────────────────────────────────────────
$router = new Router();

// ─── Public Homepage & Informational Routes ────────────────────────────────
$router->get('/', [HomeController::class, 'index']);
$router->get('/services', [ServiceController::class, 'index']);
$router->get('/privacy-policy', [HomeController::class, 'privacy']);
$router->get('/terms', [HomeController::class, 'terms']);
$router->get('/cancellation-refund', [HomeController::class, 'cancellation']);
$router->get('/contact', [HomeController::class, 'contact']);

// ─── Crawler files (generated so URLs follow APP_URL / deployment path) ────
$router->get('/robots.txt', function () {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    // Paths are root-relative, so prefix the deployment directory when the app
    // is not served from the domain root (e.g. http://host/sksl -> /sksl/admin).
    $basePath = rtrim((string) (parse_url(app_base_url(), PHP_URL_PATH) ?: ''), '/');
    echo "User-agent: *\n";
    foreach (\App\Helpers\Seo::disallowedPaths() as $path) {
        echo 'Disallow: ' . $basePath . $path . "\n";
    }
    echo 'Allow: ' . ($basePath !== '' ? $basePath . '/' : '/') . "\n\n";
    echo 'Sitemap: ' . app_url('sitemap.xml') . "\n";
});

$router->get('/sitemap.xml', function () {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (\App\Helpers\Seo::sitemapEntries() as $entry) {
        echo "  <url>\n";
        echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        echo '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
        echo '    <priority>' . $entry['priority'] . "</priority>\n";
        echo "  </url>\n";
    }
    echo '</urlset>' . "\n";
});

// ─── Public API Routes ────────────────────────────────────────────────────
$router->get('/api/services', [ServiceController::class, 'apiIndex']);
$router->get('/api/services/{id}', [ServiceController::class, 'apiShow']);
$router->get('/api/availability', [\App\Controllers\AvailabilityController::class, 'index']);

// ─── Booking Engine Routes ────────────────────────────────────────────────
$router->get('/booking', [\App\Controllers\BookingController::class, 'showBooking']);
$router->post('/api/bookings/hold', [\App\Controllers\BookingController::class, 'createHold']);
$router->post('/api/bookings/hold/release', [\App\Controllers\BookingController::class, 'releaseHold']);

// ─── Payment & Checkout Routes ────────────────────────────────────────────
$router->post('/api/payment/create-order', [\App\Controllers\PaymentController::class, 'createOrder']);
$router->post('/api/payment/verify', [\App\Controllers\PaymentController::class, 'verify']);
$router->post('/api/payment/webhook', [\App\Controllers\PaymentController::class, 'webhook']);
$router->get('/booking-confirmation', [\App\Controllers\PaymentController::class, 'confirmation']);
$router->get('/bookings/{ref}/invoice', [\App\Controllers\PaymentController::class, 'downloadInvoice']);

// ─── Customer Dashboard & Booking Management ──────────────────────────────
$router->get('/my-bookings', [\App\Controllers\CustomerBookingController::class, 'index']);
// ─── Admin Authentication Routes ──────────────────────────────────────────
$router->get('/admin/login', [\App\Controllers\AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [\App\Controllers\AdminAuthController::class, 'sendOtp']);
$router->get('/admin/verify-otp', [\App\Controllers\AdminAuthController::class, 'showVerifyOtp']);
$router->post('/admin/verify-otp', [\App\Controllers\AdminAuthController::class, 'verifyOtp']);
$router->post('/admin/logout', [\App\Controllers\AdminAuthController::class, 'logout']); // POST only (CSRF-protected)

// ─── Admin Management Operations ──────────────────────────────────────────
$router->get('/admin/bookings', [\App\Controllers\AdminBookingController::class, 'index']);
$router->post('/admin/bookings/{id}/status', [\App\Controllers\AdminBookingController::class, 'updateStatus']);

$router->get('/admin/services', [\App\Controllers\AdminServiceController::class, 'index']);
$router->post('/admin/services/{id}/toggle', [\App\Controllers\AdminServiceController::class, 'toggle']);
$router->post('/admin/services/{id}/update', [\App\Controllers\AdminServiceController::class, 'update']);

$router->get('/admin/closed-dates', [\App\Controllers\AdminClosedDateController::class, 'index']);
$router->post('/admin/closed-dates', [\App\Controllers\AdminClosedDateController::class, 'create']);
$router->post('/admin/closed-dates/{id}/delete', [\App\Controllers\AdminClosedDateController::class, 'delete']);

$router->get('/admin/banner', [\App\Controllers\AdminBannerController::class, 'index']);
$router->post('/admin/banner', [\App\Controllers\AdminBannerController::class, 'update']);


// Liveness probe. Public response is a bare status (no environment or
// infrastructure details); pass HEALTH_CHECK_TOKEN to receive the details.
$router->get('/health', function () {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $configured = (string) ($_ENV['HEALTH_CHECK_TOKEN'] ?? '');
    $presented  = (string) ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ($_GET['token'] ?? ''));
    $detailed   = $configured !== '' && $presented !== '' && hash_equals($configured, $presented);

    $dbOk = false;
    try {
        $config = require dirname(__DIR__) . '/config/database.php';
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        error_log('Health check: database unreachable: ' . $e->getMessage());
    }

    if (!$dbOk) {
        http_response_code(503);
    }
    $body = ['success' => $dbOk, 'status' => $dbOk ? 'ok' : 'degraded'];
    if ($detailed) {
        $body += ['env' => config('app.env'), 'db' => $dbOk ? 'connected' : 'unreachable', 'time' => date('c')];
    }
    echo json_encode($body);
});

// ─── Customer Authentication Routes ───────────────────────────────────────
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/api/register', [AuthController::class, 'register']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/api/login', [AuthController::class, 'login']);

$router->post('/logout', [AuthController::class, 'logout']); // POST only (CSRF-protected)
$router->post('/api/logout', [AuthController::class, 'logout']);

$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/forgot-password', [AuthController::class, 'forgotPassword']);

$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/api/reset-password', [AuthController::class, 'resetPassword']);

// ─── Customer Profile Routes ──────────────────────────────────────────────
$router->get('/profile', [ProfileController::class, 'show']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->post('/profile/password', [ProfileController::class, 'changePassword']);

// ─── Dispatch ─────────────────────────────────────────────────────────────
$router->dispatch();
