<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\AdminModel;
use App\Services\AdminAuthService;

echo "=======================================================\n";
echo "SKSL — Phase 11 Admin Email OTP Authentication Tests\n";
echo "=======================================================\n\n";

$db = getDb();
// CLI part: use the development mail driver so the OTP body is readable from mail.log.
// (The web-server part below cannot rely on that and issues its own known code.)
$_ENV['MAIL_DRIVER'] = 'log';
$adminModel = new AdminModel();
$authService = new AdminAuthService();
$mailLogFile = dirname(__DIR__) . '/storage/logs/mail.log';
$emailLogModel = new \App\Models\EmailLogModel();

// 1. Setup Test Admin
$adminEmail = 'staff_' . time() . '@sk-sports-lab.test';
$adminName  = 'Head Recovery Director';
$adminId    = $adminModel->create($adminName, $adminEmail, 'active');
assert($adminId > 0, 'Failed to create test admin.');
echo "[PASS] 1. Test administrator created (ID: $adminId, Email: $adminEmail)\n";

// 2. Test Request OTP
$reqRes = $authService->requestOtp($adminEmail);
assert($reqRes['success'] === true, 'Failed to request OTP');
echo "[PASS] 2. OTP request processed successfully\n";

// Verify OTP written by the dev mail driver and recorded in email_logs
assert(file_exists($mailLogFile), 'Mail log file does not exist');
$mailContent = file_get_contents($mailLogFile);
assert(str_contains($mailContent, $adminEmail), "Admin email $adminEmail missing from mail log");

// The code must be in the body only — never in the subject line
preg_match_all('/^SUBJECT:\s*(.*)$/m', $mailContent, $subjects);
$lastSubject = end($subjects[1]) ?: '';
assert(str_contains($lastSubject, 'sign-in code') && !preg_match('/\d{6}/', $lastSubject), "OTP subject must not contain the code, got: $lastSubject");
preg_match_all('/SKSL Admin Login Code:\s*(\d{6})/i', $mailContent, $matches);
assert(!empty($matches[1]), 'Could not extract 6-digit OTP from mail log body');
$plainOtp = end($matches[1]);
echo "[PASS] 3. OTP present in email body only (subject: \"$lastSubject\"); extracted $plainOtp\n";

$otpLogs = $emailLogModel->findByRecipient($adminEmail, 'admin_otp', 5);
assert(count($otpLogs) === 1 && $otpLogs[0]['status'] === 'logged', 'email_logs must hold one admin_otp row for this recipient');
assert(!preg_match('/\d{6}/', (string) $otpLogs[0]['subject']), 'email_logs subject must not contain the OTP');
echo "[PASS] 3b. Delivery recorded in email_logs without the code\n";

// 3. Test Invalid OTP Inputs
$badFormat = $authService->verifyOtp($adminEmail, '12345');
assert($badFormat['success'] === false, 'Accepted 5-digit OTP');
echo "[PASS] 4. Rejects invalid OTP format (non-6-digit)\n";

$wrongCode = $authService->verifyOtp($adminEmail, '000000');
assert($wrongCode['success'] === false, 'Accepted incorrect OTP code');
echo "[PASS] 5. Rejects incorrect OTP code\n";

// 4. Test Valid OTP Verification
$goodVerify = $authService->verifyOtp($adminEmail, $plainOtp);
assert($goodVerify['success'] === true, 'Failed to verify valid OTP: ' . $goodVerify['message']);
assert(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] === $adminId, 'Session admin_id not populated');
assert($_SESSION['admin_name'] === $adminName, 'Session admin_name mismatch');
echo "[PASS] 6. Valid 6-digit OTP verified, session established (Admin ID: {$_SESSION['admin_id']})\n";

// 5. Test Single-Use (Replay Prevention)
$replayVerify = $authService->verifyOtp($adminEmail, $plainOtp);
assert($replayVerify['success'] === false, 'Re-used previously consumed OTP');
echo "[PASS] 7. Single-use enforcement verified (replaying used OTP is rejected)\n";

// 6. Test Logout
$authService->logout();
assert(empty($_SESSION['admin_id']), 'Session admin_id not cleared on logout');
echo "[PASS] 8. Admin logout successfully terminates session\n";

// 7. Test HTTP End-to-End Flow via Apache
$baseUrl = 'http://localhost/sksl/public';
$cookieFile = sys_get_temp_dir() . '/sksl_admin_cookie_' . time() . '.txt';

// Step A: GET /admin/login
$ch = curl_init("$baseUrl/admin/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200, "Expected 200 for /admin/login, got $httpCode");
assert(str_contains($loginHtml, 'Admin Portal'), 'Missing Admin Portal title');
echo "[PASS] 9. HTTP GET /admin/login renders login form\n";

// Extract CSRF
preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $loginHtml, $m);
$csrfToken = $m[1] ?? '';

// Step B: POST /admin/login
$ch = curl_init("$baseUrl/admin/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'email' => $adminEmail,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 302, "Expected 302 redirect after sending OTP, got $httpCode");
echo "[PASS] 10. HTTP POST /admin/login redirects to verify-otp\n";

// The web server may deliver through real SMTP (nothing readable on disk), so
// issue a second, known code for this admin exactly as the service would.
$httpOtp = '135790';
$adminModel->createOtp($adminId, password_hash($httpOtp, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));

// Step C: GET /admin/verify-otp
$ch = curl_init("$baseUrl/admin/verify-otp?email=" . urlencode($adminEmail));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$verifyHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $verifyHtml, $m2);
$csrfToken2 = $m2[1] ?? '';

// Step D: POST /admin/verify-otp
$ch = curl_init("$baseUrl/admin/verify-otp");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken2,
        'email' => $adminEmail,
        'otp'   => $httpOtp,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
$verifyResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 302, "Expected 302 redirect after successful OTP verification, got $httpCode");
echo "[PASS] 11. HTTP POST /admin/verify-otp authenticates and redirects to admin area\n";

// Step E: Logout (POST-only, CSRF protected)
$ch = curl_init("$baseUrl/admin/logout");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$getLogoutCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert(in_array($getLogoutCode, [404, 405], true), "GET /admin/logout must not be routable, got $getLogoutCode");

// The admin page carries a fresh CSRF token (rotated on login)
$ch = curl_init("$baseUrl/admin/bookings");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
$adminHtml = (string) curl_exec($ch);
$adminPageCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($adminPageCode === 200, "Expected admin dashboard to load for the signed-in admin, got $adminPageCode");
preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $adminHtml, $m3);
$csrfToken3 = $m3[1] ?? '';
assert($csrfToken3 !== '', 'Admin page exposes a CSRF token for the logout form');
assert($csrfToken3 !== $csrfToken2, 'CSRF token is rotated after admin login');

$ch = curl_init("$baseUrl/admin/logout");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_csrf_token' => $csrfToken3]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 302, "Expected 302 redirect after logout, got $httpCode");

$ch = curl_init("$baseUrl/admin/bookings");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$afterLogoutCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($afterLogoutCode === 302, "Admin area must redirect to login after logout, got $afterLogoutCode");
echo "[PASS] 12. HTTP admin logout is POST-only and terminates the session\n";

// Cleanup
@unlink($cookieFile);
$db->prepare('DELETE FROM admin_otps WHERE admin_id = ?')->execute([$adminId]);
$db->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
$db->prepare('DELETE FROM email_logs WHERE recipient = ?')->execute([$adminEmail]);
echo "[PASS] 13. Test data cleaned up successfully\n\n";

echo ">>> ALL PHASE 11 ADMIN AUTHENTICATION TESTS PASSED SUCCESSFULLY! <<<\n";
