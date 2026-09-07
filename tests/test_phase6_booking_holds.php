<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\BookingService;
use App\Models\BookingHoldModel;
use App\Models\UserModel;
use App\Helpers\TimeHelper;

echo "=== SKSL Phase 6 Booking Engine & Holds Test Suite ===\n\n";

$db = getDb();
$bookingService = new BookingService();
$holdModel = new BookingHoldModel();
$userModel = new UserModel();

$testsPassed = 0;
$testsFailed = 0;

function assertHold(bool $condition, string $testName): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $testsFailed++;
    }
}

// Create test user
$userEmail = 'test_hold_user_' . time() . '@example.com';
$userId = $userModel->create('Hold Athlete', $userEmail, '9876543210', password_hash('Pass123!', PASSWORD_DEFAULT));
$testDate = TimeHelper::now()->modify('+5 days')->format('Y-m-d');

// ── Test 1: Valid Hold Creation ──────────────────────────────────────────────
echo "1. Testing Valid Booking Hold Creation...\n";

// Lap Pool (id=10, cap 1, 45 min) at 14:15 - 15:00
$holdRes = $bookingService->createHold($userId, 10, $testDate, '14:15');
assertHold($holdRes['success'], 'Successfully created booking hold');
assertHold(str_starts_with($holdRes['data']['booking_reference'], 'SKSL-'), 'Reference starts with SKSL- prefix');
assertHold($holdRes['data']['hold_minutes'] === 10, 'Hold duration is 10 minutes');
assertHold($holdRes['data']['end_time'] === '15:00', 'Calculated end time is 15:00 for 45-min service');
assertHold($holdRes['data']['pricing']['base_price'] === 499.0, 'Base price snapshot is 499.00');
assertHold($holdRes['data']['pricing']['gst_amount'] === 89.82, 'GST amount snapshot is 89.82');
assertHold($holdRes['data']['pricing']['total_amount'] === 588.82, 'Total amount snapshot is 588.82');

$ref1 = $holdRes['data']['booking_reference'];

// Verify in DB
$dbHold = $holdModel->findActiveByReference($ref1);
assertHold($dbHold !== null, 'Hold found in database via findActiveByReference');
assertHold($dbHold['status'] === 'active', 'Hold status is active');

// ── Test 2: Concurrency / Capacity Lockout ────────────────────────────────────
echo "\n2. Testing Capacity Lockout (Lap Pool cap=1)...\n";

// User 2 attempts to reserve the exact same slot while User 1 holds it
$user2Email = 'test_hold_user2_' . time() . '@example.com';
$user2Id = $userModel->create('Competitor Athlete', $user2Email, '9876543211', password_hash('Pass123!', PASSWORD_DEFAULT));

$holdRes2 = $bookingService->createHold($user2Id, 10, $testDate, '14:15');
assertHold(!$holdRes2['success'], 'Second customer is blocked from taking occupied capacity');
assertHold(str_contains($holdRes2['message'], 'fully booked') || str_contains($holdRes2['message'], 'Another athlete'), 'Error indicates capacity taken');

// ── Test 3: User Conflict Prevention ─────────────────────────────────────────
echo "\n3. Testing Customer Duplicate / Overlapping Hold Prevention...\n";

// User 1 tries to reserve another service (e.g. Ice Bath) overlapping 14:15 - 15:00
$conflictRes = $bookingService->createHold($userId, 4, $testDate, '14:20');
assertHold(!$conflictRes['success'], 'Customer cannot create overlapping hold on same date');
assertHold(str_contains($conflictRes['message'], 'active payment hold') || str_contains($conflictRes['message'], 'interval'), 'Error specifies active payment hold conflict');

// ── Test 4: Release Hold ─────────────────────────────────────────────────────
echo "\n4. Testing Release Hold...\n";

$releaseOk = $bookingService->releaseHold($userId, $ref1);
assertHold($releaseOk, 'Customer successfully released hold');

$releasedCheck = $holdModel->findActiveByReference($ref1);
assertHold($releasedCheck === null, 'Released hold is no longer returned as active');

// Capacity is now immediately available again for User 2!
$holdRes2Retry = $bookingService->createHold($user2Id, 10, $testDate, '14:15');
assertHold($holdRes2Retry['success'], 'Capacity immediately restored for other customers after hold release');
$ref2 = $holdRes2Retry['data']['booking_reference'];

// ── Test 5: Cron Expiration Runner ───────────────────────────────────────────
echo "\n5. Testing cron/expire-holds.php...\n";

// Force hold 2 expiry into past
$db->exec("UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE booking_reference = '{$ref2}'");

ob_start();
require dirname(__DIR__) . '/cron/expire-holds.php';
$cronOutput = ob_get_clean();

assertHold(preg_match('/Expired \d+ stale booking hold/', $cronOutput) === 1, 'Cron expired the stale hold');
assertHold(file_exists(dirname(__DIR__) . '/storage/logs/cron.log'), 'Cron log written');

// ── Test 6: HTTP End-to-End Booking Wizard & Hold ────────────────────────────
echo "\n6. Testing HTTP Booking Endpoints...\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'sksl_book_');
$baseUrl = 'http://localhost/sksl/public';

// Helper curl
function curlReq(string $url, string $method = 'GET', array $data = [], string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($jar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'url' => $effUrl, 'body' => $body];
}

// Unauthenticated GET /booking -> redirects to /login
$unauth = curlReq("{$baseUrl}/booking", 'GET', [], $cookieJar);
assertHold(str_contains($unauth['url'], 'login'), 'Unauthenticated GET /booking redirects to /login');

// Authenticate via login
$loginPage = curlReq("{$baseUrl}/login", 'GET', [], $cookieJar);
preg_match('/name="_csrf_token" value="([a-f0-9]+)"/', $loginPage['body'], $m);
$csrf = $m[1] ?? '';

$loginPost = curlReq("{$baseUrl}/login", 'POST', [
    '_csrf_token' => $csrf,
    'email'       => $userEmail,
    'password'    => 'Pass123!',
], $cookieJar);

// Authenticated GET /booking
$authBooking = curlReq("{$baseUrl}/booking?service_id=4", 'GET', [], $cookieJar);
assertHold($authBooking['code'] === 200, 'Authenticated GET /booking returns 200 OK');
assertHold(str_contains($authBooking['body'], 'Book Your Recovery Session'), 'Booking wizard rendered');
assertHold(str_contains($authBooking['body'], 'Reservation Summary'), 'Summary card present');

// Extract fresh CSRF for API hold request
preg_match('/name="_csrf_token" value="([a-f0-9]+)"/', $authBooking['body'], $m);
$csrfBook = $m[1] ?? $csrf;

// POST /api/bookings/hold
$httpHold = curlReq("{$baseUrl}/api/bookings/hold", 'POST', [
    '_csrf_token'  => $csrfBook,
    'service_id'   => 4,
    'booking_date' => $testDate,
    'start_time'   => '16:00',
], $cookieJar);

$holdJson = json_decode($httpHold['body'], true);
assertHold(isset($holdJson['success']) && $holdJson['success'], 'HTTP POST /api/bookings/hold succeeded');
assertHold(isset($holdJson['data']['booking_reference']), 'Returned booking reference via HTTP');

$httpRef = $holdJson['data']['booking_reference'] ?? '';

// POST /api/bookings/hold/release
$httpRel = curlReq("{$baseUrl}/api/bookings/hold/release", 'POST', [
    '_csrf_token'        => $csrfBook,
    'booking_reference' => $httpRef,
], $cookieJar);

$relJson = json_decode($httpRel['body'], true);
assertHold(isset($relJson['success']) && $relJson['success'], 'HTTP POST /api/bookings/hold/release succeeded');

// ── Cleanup ──────────────────────────────────────────────────────────────────
echo "\n7. Cleaning up test data...\n";
$db->exec("DELETE FROM booking_holds WHERE user_id IN ({$userId}, {$user2Id})");
$db->exec("DELETE FROM users WHERE id IN ({$userId}, {$user2Id})");
@unlink($cookieJar);
echo "  [INFO] Test data cleaned up.\n";

echo "\n============================================\n";
echo "Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "============================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
