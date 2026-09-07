<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Services\BookingService;

$baseUrl = 'http://localhost/sksl/public';

echo "Testing HTTP Payment Endpoints via Apache...\n";

// Create a test user directly in DB
$userModel = new UserModel();
$testEmail = 'httppay_' . time() . '@test.com';
$userId = $userModel->create('HTTP Pay Athlete', $testEmail, '9876543210', password_hash('Pass1234!', PASSWORD_BCRYPT));

// 1. Unauthenticated call to /api/payment/create-order should fail (401 or redirect)
$ch = curl_init("$baseUrl/api/payment/create-order");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['booking_reference' => 'SKSL-FAKE']),
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 401 || $httpCode === 302 || $httpCode === 403, "Expected 401, 302 or 403, got $httpCode");
echo "[PASS] 1. Unauthenticated/unprotected request to /api/payment/create-order is rejected (HTTP $httpCode)\n";

// 2. Login via HTTP to obtain session cookie
$cookieFile = sys_get_temp_dir() . '/sksl_pay_cookie_' . time() . '.txt';

// Fetch login page for CSRF token
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

// Post login
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
$loginResp = curl_exec($ch);
curl_close($ch);

// 3. Create a hold via HTTP
$serviceModel = new ServiceModel();
$spa = $serviceModel->findBySlug('spa');
$serviceId = (int) $spa['id'];
$date = date('Y-m-d', strtotime('+4 days'));
$startTime = '10:00';

$ch = curl_init("$baseUrl/api/bookings/hold");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'service_id' => $serviceId,
        'booking_date' => $date,
        'start_time' => $startTime,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$holdJson = json_decode(curl_exec($ch), true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200 && ($holdJson['success'] ?? false) === true, "Hold creation failed: " . json_encode($holdJson));
$bookingRef = $holdJson['data']['booking_reference'];
echo "[PASS] 2. HTTP Hold created: $bookingRef\n";

// 4. Create payment order via HTTP
$ch = curl_init("$baseUrl/api/payment/create-order");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'booking_reference' => $bookingRef,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$orderJson = json_decode(curl_exec($ch), true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200 && ($orderJson['success'] ?? false) === true, "Order creation failed: " . json_encode($orderJson));
$orderId = $orderJson['data']['razorpay_order_id'];
echo "[PASS] 3. HTTP Razorpay Order created: $orderId\n";

// 5. Verify payment via HTTP
$ch = curl_init("$baseUrl/api/payment/verify");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'booking_reference' => $bookingRef,
        'razorpay_order_id' => $orderId,
        'razorpay_payment_id' => 'pay_http_test_123',
        'razorpay_signature' => 'sig_http_test_123',
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$verifyJson = json_decode(curl_exec($ch), true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200 && ($verifyJson['success'] ?? false) === true, "Verification failed: " . json_encode($verifyJson));
echo "[PASS] 4. HTTP Payment verified: {$verifyJson['message']}\n";

// 6. Access /booking-confirmation?ref=... via HTTP
$ch = curl_init("$baseUrl/booking-confirmation?ref=" . urlencode($bookingRef));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$confHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200, "Expected 200 for confirmation page, got $httpCode");
assert(str_contains($confHtml, $bookingRef), "Booking reference $bookingRef missing from confirmation page.");
assert(str_contains($confHtml, 'Booking Confirmed &amp; Paid'), "Confirmed badge missing.");
echo "[PASS] 5. HTTP Booking confirmation page rendered with reference $bookingRef\n";

// Cleanup
@unlink($cookieFile);
$db = getDb();
$db->prepare('DELETE FROM payments WHERE razorpay_order_id = ?')->execute([$orderId]);
$db->prepare('DELETE FROM bookings WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 6. HTTP test cleanup completed.\n";

echo "\n>>> ALL HTTP PAYMENT FLOW TESTS PASSED! <<<\n";
