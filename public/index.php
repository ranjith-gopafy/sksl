<?php

/**
 * SKSL — Front Controller
 *
 * This is the single entry point for all web requests.
 * Apache .htaccess routes all requests here.
 */

declare(strict_types=1);

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

$router->get('/health', function() {
    header('Content-Type: application/json');
    try {
        $db = getDb();
        $db->query('SELECT 1');
        echo json_encode([
            'success' => true,
            'message' => 'Application is healthy.',
            'env'     => config('app.env'),
            'db'      => 'connected',
        ]);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed.',
        ]);
    }
});

// ─── Customer Authentication Routes ───────────────────────────────────────
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/api/register', [AuthController::class, 'register']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/api/login', [AuthController::class, 'login']);

$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);
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
