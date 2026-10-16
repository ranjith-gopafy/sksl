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
// Nested form/query values ("name[]=x") are dropped before any controller sees them; the only real
// list fields are the combo-package form's rows.
$listFields = request_path() === '/admin/packages' ? ['service_id', 'quantity', 'duration_minutes'] : [];
$_GET     = scalar_input($_GET);
$_POST    = scalar_input($_POST, $listFields);
$_REQUEST = scalar_input($_REQUEST, $listFields);

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
$router->get('/consultations', [ServiceController::class, 'consultations']);
$router->get('/professional-services', [ServiceController::class, 'consultations']);

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

// RFC 9116 vulnerability-disclosure contact (served at both standard locations)
$securityTxt = function () {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    $contact = (string) ($_ENV['SECURITY_CONTACT_EMAIL'] ?? (config('business.support_email') ?: ''));
    if ($contact === '') {
        http_response_code(404);
        echo "No security contact configured.\n";
        return;
    }
    echo 'Contact: mailto:' . $contact . "\n";
    echo 'Expires: ' . gmdate('Y-m-d\TH:i:s\Z', strtotime('first day of january next year')) . "\n";
    echo "Preferred-Languages: en\n";
    echo 'Canonical: ' . app_url('.well-known/security.txt') . "\n";
    echo 'Policy: ' . app_url('privacy-policy') . "\n";
};
$router->get('/.well-known/security.txt', $securityTxt);
$router->get('/security.txt', $securityTxt);

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
$router->post('/api/bookings/professional', [\App\Controllers\BookingController::class, 'bookProfessional']);
$router->post('/api/bookings/package', [\App\Controllers\BookingController::class, 'bookPackage']);

// ─── Combo Packages Routes ───────────────────────────────────────────────
$router->get('/packages', [\App\Controllers\PackageController::class, 'index']);
$router->get('/api/packages', [\App\Controllers\PackageController::class, 'apiIndex']);
$router->get('/my-packages', [\App\Controllers\PackageController::class, 'myPackages']);
$router->get('/my-packages/{ref}/invoice', [\App\Controllers\PackageController::class, 'downloadInvoice']);
$router->post('/api/packages/{id}/purchase', [\App\Controllers\PackageController::class, 'purchase']);
$router->post('/api/packages/verify', [\App\Controllers\PackageController::class, 'verifyPurchase']);

// ─── Payment & Checkout Routes ────────────────────────────────────────────
$router->post('/api/payment/create-order', [\App\Controllers\PaymentController::class, 'createOrder']);
$router->post('/api/payment/request-offline', [\App\Controllers\PaymentController::class, 'requestOffline']);
$router->post('/api/payment/verify', [\App\Controllers\PaymentController::class, 'verify']);
$router->post('/api/payment/webhook', [\App\Controllers\PaymentController::class, 'webhook']);
$router->get('/booking-confirmation', [\App\Controllers\PaymentController::class, 'confirmation']);
$router->get('/bookings/{ref}/invoice', [\App\Controllers\PaymentController::class, 'downloadInvoice']);

// ─── Customer Dashboard & Booking Management ──────────────────────────────
$router->get('/my-bookings', [\App\Controllers\CustomerBookingController::class, 'index']);
$router->post('/my-bookings/{ref}/cancel', [\App\Controllers\CustomerBookingController::class, 'cancel']);
// ─── Admin Authentication Routes ──────────────────────────────────────────
$router->get('/admin/login', [\App\Controllers\AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [\App\Controllers\AdminAuthController::class, 'sendOtp']);
$router->get('/admin/verify-otp', [\App\Controllers\AdminAuthController::class, 'showVerifyOtp']);
$router->post('/admin/verify-otp', [\App\Controllers\AdminAuthController::class, 'verifyOtp']);
$router->post('/admin/logout', [\App\Controllers\AdminAuthController::class, 'logout']); // POST only (CSRF-protected)

// ─── Admin Management Operations ──────────────────────────────────────────
$router->get('/admin', [\App\Controllers\AdminDashboardController::class, 'index']);
$router->get('/admin/dashboard', [\App\Controllers\AdminDashboardController::class, 'index']);
$router->get('/admin/bookings', [\App\Controllers\AdminBookingController::class, 'index']);
$router->post('/admin/bookings/{id}/status', [\App\Controllers\AdminBookingController::class, 'updateStatus']);
$router->post('/admin/bookings/{id}/approve', [\App\Controllers\AdminBookingController::class, 'approvePayment']);
$router->post('/admin/bookings/{id}/visit', [\App\Controllers\AdminBookingController::class, 'markVisited']);
$router->post('/admin/bookings/{id}/no-show', [\App\Controllers\AdminBookingController::class, 'markNoShow']);

$router->get('/admin/services', [\App\Controllers\AdminServiceController::class, 'index']);
$router->post('/admin/services/{id}/toggle', [\App\Controllers\AdminServiceController::class, 'toggle']);
$router->post('/admin/services/{id}/update', [\App\Controllers\AdminServiceController::class, 'update']);
$router->post('/admin/services/{id}/duration-options', [\App\Controllers\AdminServiceController::class, 'addDurationOption']);
$router->post('/admin/services/duration-options/{id}/delete', [\App\Controllers\AdminServiceController::class, 'deleteDurationOption']);

$router->get('/admin/availability', [\App\Controllers\AdminAvailabilityController::class, 'index']);
$router->post('/admin/availability', [\App\Controllers\AdminAvailabilityController::class, 'create']);
$router->post('/admin/availability/{id}/delete', [\App\Controllers\AdminAvailabilityController::class, 'delete']);

$router->get('/admin/manual-blocks', [\App\Controllers\AdminManualBlockController::class, 'index']);
$router->post('/admin/manual-blocks', [\App\Controllers\AdminManualBlockController::class, 'create']);
$router->post('/admin/manual-blocks/{id}/delete', [\App\Controllers\AdminManualBlockController::class, 'delete']);

$router->get('/admin/packages', [\App\Controllers\AdminPackageController::class, 'index']);
$router->get('/admin/package-purchases', [\App\Controllers\AdminPackageController::class, 'purchases']);
$router->post('/admin/package-purchases/{id}/approve', [\App\Controllers\AdminPackageController::class, 'approvePurchase']);
$router->post('/admin/packages',[\App\Controllers\AdminPackageController::class, 'create']);
$router->post('/admin/packages/{id}/toggle', [\App\Controllers\AdminPackageController::class, 'toggle']);

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
    $presented  = (string) ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ''); // header only: a ?token= value would be written to access logs
    $detailed   = $configured !== '' && $presented !== '' && hash_equals($configured, $presented);

    $dbOk = false;
    try {
        // The shared connection is reused, so a monitor polling /health does not open a new
        // database connection per hit (shared hosts cap simultaneous connections).
        getDb()->query('SELECT 1');
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
