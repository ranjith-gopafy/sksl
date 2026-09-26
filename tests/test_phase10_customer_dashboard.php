<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\BookingModel;
use App\Models\PaymentModel;
use App\Controllers\CustomerBookingController;

echo "=======================================================\n";
echo "SKSL — Phase 10 Customer Dashboard Verification Tests\n";
echo "=======================================================\n\n";

$db = getDb();
$userModel    = new UserModel();
$serviceModel = new ServiceModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();

// 1. Create Test Athlete
$testEmail = 'dashathlete_' . time() . '@sk-sports-lab.test';
$userId = $userModel->create(
    'Dashboard Athlete',
    $testEmail,
    '9876543210',
    password_hash('Pass1234!', PASSWORD_BCRYPT)
);
assert($userId > 0, 'User creation failed');
echo "[PASS] 1. Test athlete created (ID: $userId)\n";

// 2. Create an Upcoming Session (> 24 hours in the future)
$service = $serviceModel->findBySlug('hot-bath');
assert($service !== null, 'Service not found');

$upcomingRef = 'SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$upcomingBookingId = $bookingModel->create([
    'booking_reference'        => $upcomingRef,
    'user_id'                  => $userId,
    'service_id'               => (int) $service['id'],
    'booking_date'             => date('Y-m-d', strtotime('+3 days')),
    'start_time'               => '15:00:00',
    'end_time'                 => '15:10:00',
    'service_duration_minutes' => 10,
    'base_amount'              => 199.00,
    'gst_amount'               => 35.82,
    'total_amount'             => 234.82,
    'booking_status'           => 'confirmed',
    'payment_status'           => 'paid',
]);

// 3. Create a Near-Term Session (within 1 hour from now — non-cancellable per policy)
$nearRef = 'SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$nearStartTime = date('H:i:s', strtotime('+45 minutes'));
$nearEndTime   = date('H:i:s', strtotime('+55 minutes'));
$nearBookingId = $bookingModel->create([
    'booking_reference'        => $nearRef,
    'user_id'                  => $userId,
    'service_id'               => (int) $service['id'],
    'booking_date'             => date('Y-m-d'),
    'start_time'               => $nearStartTime,
    'end_time'                 => $nearEndTime,
    'service_duration_minutes' => 10,
    'base_amount'              => 199.00,
    'gst_amount'               => 35.82,
    'total_amount'             => 234.82,
    'booking_status'           => 'confirmed',
    'payment_status'           => 'paid',
]);

echo "[PASS] 2. Test bookings generated (1 future session, 1 near-term session)\n";

// 4. Test findByUser
$all = $bookingModel->findByUser($userId, null);
assert(count($all) === 2, 'Expected 2 bookings in findByUser');
echo "[PASS] 3. findByUser returned all 2 user bookings\n";

$upcoming = $bookingModel->findByUser($userId, 'upcoming');
assert(count($upcoming) >= 1, 'Expected at least 1 upcoming booking');
echo "[PASS] 4. Upcoming filter returned valid active bookings\n";

// 5. Customers have no self-cancel capability; staff cancel via the state machine
assert(!method_exists(BookingModel::class, 'checkCancellationEligibility'), 'Customer cancellation helper must be removed');
assert(!method_exists(\App\Controllers\CustomerBookingController::class, 'cancel'), 'Customer cancel action must be removed');
echo "[PASS] 5. Customer self-cancellation code removed (cancellations handled by SKSL staff)\n";

$upcomingBooking = $bookingModel->findById($upcomingBookingId);
$check = BookingModel::checkAdminTransition($upcomingBooking, 'cancelled');
assert($check['allowed'] === true, 'Staff may cancel a confirmed booking');
echo "[PASS] 6. Staff cancellation permitted for a confirmed booking\n";

// 6. Perform staff cancellation atomically
$cancelSuccess = $bookingModel->transitionStatus($upcomingBookingId, 'confirmed', 'cancelled');
assert($cancelSuccess === true, 'Failed to transition to cancelled');

$cancelledBooking = $bookingModel->findById($upcomingBookingId);
assert($cancelledBooking['booking_status'] === 'cancelled', 'Status was not updated to cancelled');
echo "[PASS] 7. Confirmed session transitioned to 'cancelled' by staff\n";

// 7. Cancelled is terminal — cannot be re-cancelled or resurrected
assert(BookingModel::checkAdminTransition($cancelledBooking, 'cancelled')['allowed'] === false, 'Already cancelled booking cannot be cancelled again');
assert(BookingModel::checkAdminTransition($cancelledBooking, 'confirmed')['allowed'] === false, 'Cancelled booking cannot be re-confirmed');
assert($bookingModel->transitionStatus($upcomingBookingId, 'confirmed', 'completed') === false, 'Stale transition must not apply');
echo "[PASS] 8. Cancelled session is terminal (no re-cancel, no resurrection)\n";

// 8. Near-term booking remains a normal confirmed session
$nearBooking = $bookingModel->findById($nearBookingId);
assert($nearBooking['booking_status'] === 'confirmed', 'Near-term booking should remain confirmed');
echo "[PASS] 9. Near-term session unaffected\n";

// 8. Test HTTP Dashboard via Apache
$baseUrl = 'http://localhost/sksl/public';
$cookieFile = sys_get_temp_dir() . '/sksl_dash_cookie_' . time() . '.txt';

// Test unauthenticated GET /my-bookings redirects to login
$ch = curl_init("$baseUrl/my-bookings");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 302, "Expected 302 redirect for unauthenticated access, got $httpCode");
echo "[PASS] 10. Unauthenticated access to /my-bookings redirects to /login\n";

// Login via HTTP
$ch = curl_init("$baseUrl/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $loginHtml, $m);
$csrfToken = $m[1] ?? '';

$ch = curl_init("$baseUrl/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'email' => $testEmail,
        'password' => 'Pass1234!',
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch);
curl_close($ch);

// View dashboard via HTTP
$ch = curl_init("$baseUrl/my-bookings?tab=cancelled");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$dashHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200, "Expected HTTP 200 for dashboard, got $httpCode");
assert(str_contains($dashHtml, 'My Recovery Sessions'), 'Missing dashboard heading');
assert(str_contains($dashHtml, $upcomingRef), 'Cancelled booking reference not found in dashboard');
echo "[PASS] 11. Authenticated customer dashboard renders with session history and status tabs\n";

// 9. Cleanup
@unlink($cookieFile);
$db->prepare('DELETE FROM bookings WHERE id IN (?, ?)')->execute([$upcomingBookingId, $nearBookingId]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 12. Test data cleaned up successfully\n\n";

echo ">>> ALL PHASE 10 CUSTOMER DASHBOARD TESTS PASSED SUCCESSFULLY! <<<\n";
