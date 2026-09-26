<?php

/**
 * SKSL — Phase 3 Verification Test Suite
 *
 * Tests:
 * 1. Customer registration validation (valid, duplicate, short password, mismatch, invalid mobile)
 * 2. Password hashing with password_verify
 * 3. Customer login (valid, invalid, deactivated)
 * 4. Login rate limiting
 * 5. Forgot password token generation (SHA-256 hash in DB, email dispatch/log)
 * 6. Reset password token validation and single-use enforcement
 * 7. Profile update and password change
 * 8. CSRF token generation and constant-time verification
 * 9. XSS escaping helper h()
 * 10. Clean up test records
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\UserModel;
use App\Models\PasswordResetModel;
use App\Services\AuthService;
use App\Helpers\Csrf;
use App\Helpers\RateLimit;

echo "=== SKSL Phase 3 Verification Test Suite ===\n\n";

$db = getDb();
$authService = new AuthService();
$userModel   = new UserModel();
$resetModel  = new PasswordResetModel();

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $testName): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $testsFailed++;
    }
}

// Clean up any residual test data first
$testEmail = 'test_athlete_' . time() . '@example.com';
// Remove leftovers from earlier runs (bookings reference users via FK, so cascade by hand)
$db->exec("DELETE p FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN users u ON u.id = b.user_id WHERE u.email LIKE 'test_athlete_%@example.com'");
$db->exec("DELETE b FROM bookings b JOIN users u ON u.id = b.user_id WHERE u.email LIKE 'test_athlete_%@example.com'");
$db->exec("DELETE h FROM booking_holds h JOIN users u ON u.id = h.user_id WHERE u.email LIKE 'test_athlete_%@example.com'");
$db->exec("DELETE FROM users WHERE email LIKE 'test_athlete_%@example.com'");
// Rate limiter is DB-backed and per-IP; start from a clean slate
RateLimit::clearScope('cust_login');
RateLimit::clearScope('cust_login_ip');
RateLimit::clearScope('cust_register');
RateLimit::clearScope('cust_register_ip');
RateLimit::clearScope('cust_forgot');
RateLimit::clearScope('cust_forgot_ip');
$mailLog = dirname(__DIR__) . '/storage/logs/mail.log';
if (file_exists($mailLog)) {
    @unlink($mailLog);
}

// ── Test 1: Registration Validation ──────────────────────────────────────────
echo "1. Testing Registration Validation...\n";

$res = $authService->register([
    'name'                  => '',
    'email'                 => 'not-an-email',
    'mobile'                => '123',
    'password'              => 'short',
    'password_confirmation' => 'mismatch',
]);
assertTest(!$res['success'], 'Rejects invalid input (empty name, bad email, short password)');
assertTest(isset($res['errors']['name']), 'Reports name error');
assertTest(isset($res['errors']['email']), 'Reports email error');
assertTest(isset($res['errors']['mobile']), 'Reports mobile error');
assertTest(isset($res['errors']['password']), 'Reports password error');

// ── Test 2: Valid Registration ───────────────────────────────────────────────
echo "\n2. Testing Valid Registration...\n";

$res = $authService->register([
    'name'                  => 'Arjun Kinetic',
    'email'                 => $testEmail,
    'mobile'                => '9876543210',
    'password'              => 'StrongPassword123!',
    'password_confirmation' => 'StrongPassword123!',
]);
assertTest($res['success'], 'Successfully registers valid user');
$testUserId = (int) ($res['user_id'] ?? 0);
assertTest($testUserId > 0, "Created user ID is valid ({$testUserId})");

// Verify user in database
$dbUser = $userModel->findById($testUserId);
assertTest($dbUser !== null, 'User found in database via findById');
assertTest($dbUser['name'] === 'Arjun Kinetic', 'User name matches');
assertTest($dbUser['email'] === strtolower($testEmail), 'Email is stored lowercase');
assertTest(!isset($dbUser['password_hash']), 'findById does not leak password_hash');

// ── Test 3: Duplicate Email Prevention ───────────────────────────────────────
echo "\n3. Testing Duplicate Email Rejection...\n";

$dupRes = $authService->register([
    'name'                  => 'Duplicate Tester',
    'email'                 => $testEmail,
    'mobile'                => '9876543210',
    'password'              => 'StrongPassword123!',
    'password_confirmation' => 'StrongPassword123!',
]);
assertTest(!$dupRes['success'], 'Duplicate email registration is rejected');
assertTest(isset($dupRes['errors']['email']), 'Duplicate error specifies email field');

// ── Test 4: Customer Login ───────────────────────────────────────────────────
echo "\n4. Testing Login...\n";

$loginBad = $authService->login($testEmail, 'WrongPassword!');
assertTest(!$loginBad['success'], 'Rejects invalid password');

$loginGood = $authService->login($testEmail, 'StrongPassword123!');
assertTest($loginGood['success'], 'Accepts valid password');
assertTest($_SESSION['user_id'] === $testUserId, 'Session user_id populated');
assertTest($_SESSION['user_name'] === 'Arjun Kinetic', 'Session user_name populated');
assertTest(!isset($loginGood['user']['password_hash']), 'Login result omits password_hash');

// ── Test 5: Logout ───────────────────────────────────────────────────────────
echo "\n5. Testing Logout...\n";

$authService->logout();
assertTest(!isset($_SESSION['user_id']), 'Session user_id cleared on logout');

// ── Test 6: Rate Limiting ───────────────────────────────────────────────────
echo "\n6. Testing Login Rate Limiting...\n";

$spamEmail = 'rate_limit_test_' . time() . '@example.com';
$key = RateLimit::key('cust_login', $spamEmail);
RateLimit::clear($key);

for ($i = 1; $i <= 5; $i++) {
    $r = $authService->login($spamEmail, 'bad_pass');
    assertTest(!$r['success'] && !str_contains($r['message'], 'Too many'), "Attempt {$i} rejected for bad credentials");
}
$sixth = $authService->login($spamEmail, 'bad_pass');
assertTest(!$sixth['success'] && str_contains($sixth['message'], 'Too many'), '6th attempt is rate-limited/locked out');

// Lockout lives in the database, not the session: a fresh session is still locked
$rowStmt = $db->prepare('SELECT attempts, locked_until FROM rate_limits WHERE rl_key = ?');
$rowStmt->execute([$key]);
$rlRow = $rowStmt->fetch();
assertTest($rlRow !== false && (int) $rlRow['attempts'] >= 5 && $rlRow['locked_until'] !== null, 'Lockout persisted in rate_limits table');
$_SESSION = [];
$afterReset = $authService->login($spamEmail, 'bad_pass');
assertTest(!$afterReset['success'] && str_contains($afterReset['message'], 'Too many'), 'Dropping the session cookie does not reset the lockout');
assertTest(RateLimit::remainingSeconds($key) > 0 && RateLimit::remainingSeconds($key) <= 900, 'remainingSeconds reports the active lockout');
RateLimit::clear($key);
assertTest(RateLimit::remainingSeconds($key) === 0, 'clear() removes the lockout');

// Successful logins are not counted against the account
for ($i = 1; $i <= 7; $i++) {
    $ok = $authService->login($testEmail, 'StrongPassword123!');
    assertTest($ok['success'], "Successful login {$i} not throttled");
}
$authService->logout();

// ── Test 7: Forgot Password Flow ─────────────────────────────────────────────
echo "\n7. Testing Forgot Password...\n";

$forgotRes = $authService->forgotPassword($testEmail);
assertTest($forgotRes['success'], 'Forgot password returns generic success message');

// Check that token hash was created in password_resets table
$stmt = $db->prepare('SELECT * FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$testUserId]);
$resetRecord = $stmt->fetch();
assertTest($resetRecord !== null, 'Password reset record created in database');
assertTest(strlen($resetRecord['token_hash']) === 64, 'Token hash is SHA-256 (64 hex chars)');
assertTest($resetRecord['used_at'] === null, 'Token used_at is initially NULL');
assertTest($resetRecord['expires_at'] > date('Y-m-d H:i:s'), 'Token expires in the future');

// Check mail log
$mailLog = dirname(__DIR__) . '/storage/logs/mail.log';
assertTest(file_exists($mailLog), 'Mail log file exists in storage/logs/mail.log');
$logContent = file_get_contents($mailLog);
assertTest(str_contains($logContent, $testEmail), 'Reset email was logged for recipient');

// Extract plain token from mail log for testing reset
preg_match('/reset-password\?token=([a-f0-9]+)/', $logContent, $matches);
$plainToken = $matches[1] ?? '';
assertTest(!empty($plainToken), 'Extracted plain reset token from email log');

// Verify token lookup
$validRecord = $authService->verifyResetToken($plainToken);
assertTest($validRecord !== null, 'Plain token successfully validates against stored SHA-256 hash');

// Test with invalid token
$invalidRecord = $authService->verifyResetToken('bogus_token_123');
assertTest($invalidRecord === null, 'Bogus token fails verification');

// ── Test 8: Reset Password ───────────────────────────────────────────────────
echo "\n8. Testing Password Reset Execution...\n";

$resetExec = $authService->resetPassword($plainToken, 'NewStrongPass456!', 'NewStrongPass456!');
assertTest($resetExec['success'], 'Password successfully reset with valid token');

// Verify token cannot be reused (one-time use enforcement)
$reused = $authService->resetPassword($plainToken, 'AnotherPass789!', 'AnotherPass789!');
assertTest(!$reused['success'], 'Re-using same reset token is rejected (single-use enforced)');

// Verify login with new password works
$loginNew = $authService->login($testEmail, 'NewStrongPass456!');
assertTest($loginNew['success'], 'Login with newly reset password succeeds');

// Verify login with old password fails
$loginOld = $authService->login($testEmail, 'StrongPassword123!');
assertTest(!$loginOld['success'], 'Login with old password now fails');

// ── Test 9: Profile Update & Password Change ─────────────────────────────────
echo "\n9. Testing Profile Update and Password Change...\n";

$_SESSION['user_id'] = $testUserId;
$profileRes = $authService->updateProfile($testUserId, [
    'name'   => 'Arjun Champion',
    'mobile' => '9998887776',
]);
assertTest($profileRes['success'], 'Profile updated successfully');

$updatedUser = $userModel->findById($testUserId);
assertTest($updatedUser['name'] === 'Arjun Champion', 'Updated name reflected in DB');
assertTest($updatedUser['mobile'] === '9998887776', 'Updated mobile reflected in DB');

$pwChange = $authService->changePassword($testUserId, 'NewStrongPass456!', 'FinalPassword999!', 'FinalPassword999!');
assertTest($pwChange['success'], 'Password changed via profile flow');

$loginFinal = $authService->login($testEmail, 'FinalPassword999!');
assertTest($loginFinal['success'], 'Login with final password succeeds');

// ── Test 10: CSRF & Security Helpers ─────────────────────────────────────────
echo "\n10. Testing Security Helpers (CSRF & XSS)...\n";

$csrfToken = Csrf::token();
assertTest(!empty($csrfToken) && strlen($csrfToken) === 64, 'CSRF token is 64 hex characters');
assertTest(Csrf::verify($csrfToken), 'CSRF token verifies matching session token');
assertTest(!Csrf::verify('forged_token'), 'CSRF rejects forged token');
assertTest(!Csrf::verify(''), 'CSRF rejects empty token');

$fieldHtml = Csrf::field();
assertTest(str_contains($fieldHtml, 'name="_csrf_token"'), 'Csrf::field() outputs hidden input');
assertTest(str_contains($fieldHtml, $csrfToken), 'Csrf::field() contains session token');

// XSS escaping
$dangerous = '<script>alert("xss")</script>&"\'';
$escaped = h($dangerous);
assertTest(!str_contains($escaped, '<script>'), 'h() neutralizes script tags');
assertTest(str_contains($escaped, '&lt;script&gt;'), 'h() encodes HTML entities');

// ── Cleanup ──────────────────────────────────────────────────────────────────
echo "\n11. Cleaning up test data...\n";
$resetModel->deleteOldForUser($testUserId);
$db->exec("DELETE FROM password_resets WHERE user_id = {$testUserId}");
$db->exec("DELETE FROM users WHERE id = {$testUserId}");
echo "  [INFO] Test user and reset records cleaned up.\n";

echo "\n============================================\n";
echo "Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "============================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
