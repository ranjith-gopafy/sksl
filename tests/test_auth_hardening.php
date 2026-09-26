<?php
/**
 * Audit Medium (auth/session) — hardening verification.
 *
 * Service-level checks (no server needed):
 *   1. PasswordPolicy rules
 *   2. Registration does not reveal whether an email exists; terms acceptance required
 *   3. Sessions opened before a password change are invalidated (password_changed_at)
 *   4. Customer/admin session scopes are mutually exclusive
 *   5. CSRF token rotates on login/logout
 *   6. safe_return_url() open-redirect allow-list
 *   7. Error reporting: E_ALL, display_errors off unless APP_DEBUG, friendly 503 page present
 *
 * Live HTTP checks (default base http://localhost/sksl/public, override with argv[1]):
 *   8. GET /logout and GET /admin/logout are not routable; POST /logout ends the session
 *   9. /health exposes no environment details
 *  10. Login returns the visitor to the page they were heading to (intended_url)
 *  11. HTTP registration shows the same outcome for new and existing emails
 *
 * Run:  php tests/test_auth_hardening.php [base-url]
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers\Csrf;
use App\Helpers\PasswordPolicy;
use App\Helpers\RateLimit;
use App\Middleware\CustomerAuth;
use App\Models\UserModel;
use App\Services\AuthService;

$baseUrl = rtrim($argv[1] ?? getenv('SKSL_TEST_BASE_URL') ?: 'http://localhost/sksl/public', '/');
$pass = 0;
$fail = 0;
function check(bool $ok, string $label): void
{
    global $pass, $fail;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

/** @return array{code:int, headers:string, body:string} */
function http(string $method, string $url, array $post = [], ?string &$jar = null, array $extraHeaders = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $extraHeaders,
    ];
    if ($jar !== null) {
        $opts[CURLOPT_COOKIEFILE] = $jar;
        $opts[CURLOPT_COOKIEJAR]  = $jar;
    }
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}
function csrfFrom(string $html): string
{
    return preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $m) ? $m[1] : '';
}
function tmpJar(): string
{
    return tempnam(sys_get_temp_dir(), 'sksl_jar_');
}

echo "=== Auth hardening test ({$baseUrl}) ===\n";

$db          = getDb();
$userModel   = new UserModel();
$authService = new AuthService();
$runId       = substr(bin2hex(random_bytes(4)), 0, 8);
$emailA      = "hard_a_{$runId}@sk-sports-lab.test";
$emailB      = "hard_b_{$runId}@sk-sports-lab.test";
$emailHttp   = "hard_http_{$runId}@sk-sports-lab.test";
$goodPass    = 'Kinetic-Run-2026';

$cleanup = static function () use ($db, $runId) {
    $db->exec("DELETE FROM users WHERE email LIKE 'hard_%_{$runId}@sk-sports-lab.test'");
    foreach (['cust_login', 'cust_login_ip', 'cust_register', 'cust_register_ip'] as $scope) {
        RateLimit::clearScope($scope);
    }
};
$cleanup();

// ── 1. Password policy ────────────────────────────────────────────────────
echo "\n1. Password policy\n";
check(PasswordPolicy::validate('short1') !== null, '1.1 rejects < 8 characters');
check(PasswordPolicy::validate(str_repeat('a1', 70)) !== null, '1.2 rejects > 128 characters');
check(PasswordPolicy::validate('onlyletters') !== null, '1.3 rejects letters without a digit');
check(PasswordPolicy::validate('1234567890') !== null, '1.4 rejects digits without a letter');
check(PasswordPolicy::validate('Password123') !== null, '1.5 rejects common password (case-insensitive)');
check(PasswordPolicy::validate('pass-word-123') !== null, '1.6 rejects common password with separators stripped');
check(PasswordPolicy::validate('ravi.kumar2024', 'ravi.kumar@example.com') !== null, '1.7 rejects password containing email local-part');
check(PasswordPolicy::validate('kumar_speed9', '', 'Ravi Kumar') !== null, '1.8 rejects password containing a name part');
check(PasswordPolicy::validate($goodPass, 'ravi.kumar@example.com', 'Ravi Kumar') === null, '1.9 accepts a strong unrelated password');
check(PasswordPolicy::validate('Straße2026') === null, '1.10 accepts unicode letters');

// ── 2. Registration: no enumeration, terms required ──────────────────────
echo "\n2. Registration\n";
$input = ['name' => 'Hardening A', 'email' => $emailA, 'mobile' => '9800000001', 'password' => $goodPass, 'password_confirmation' => $goodPass];
$noTerms = $authService->register($input);
check($noTerms['success'] === false && isset($noTerms['errors']['terms']), '2.1 registration requires accepting terms');
check($userModel->findByEmail($emailA) === null, '2.2 no account created when terms are not accepted');

$weak = $authService->register(array_merge($input, ['terms' => '1', 'password' => 'hardening a1', 'password_confirmation' => 'hardening a1']));
check($weak['success'] === false && isset($weak['errors']['password']), '2.3 password containing the name is rejected on register');

$first = $authService->register($input + ['terms' => '1']);
check($first['success'] === true && ($first['user_id'] ?? 0) > 0, '2.4 valid registration succeeds');
$userA = $userModel->findByEmail($emailA);
check($userA !== null && !empty($userA['terms_accepted_at']), '2.5 terms_accepted_at recorded');

$second = $authService->register(['name' => 'Someone Else', 'email' => strtoupper($emailA), 'mobile' => '9800000002', 'password' => $goodPass, 'password_confirmation' => $goodPass, 'terms' => '1']);
check($second['success'] === true, '2.6 duplicate email returns success (no enumeration)');
check(!isset($second['user_id']) && !isset($second['errors']), '2.7 duplicate response carries no user_id or field error');
check(($second['message'] ?? '') === ($first['message'] ?? '-'), '2.8 identical message for new and existing email');
check((int) $db->query("SELECT COUNT(*) FROM users WHERE email = '{$emailA}'")->fetchColumn() === 1, '2.9 still exactly one account');

// ── 3. Session invalidation after password change ────────────────────────
echo "\n3. Session invalidation\n";
$_SESSION = [];
$login = $authService->login($emailA, $goodPass);
check($login['success'] === true && isset($_SESSION['auth_at']), '3.1 login records auth_at in the session');
check(CustomerAuth::check() === true, '3.2 fresh session passes CustomerAuth::check()');

// Simulate a session opened before the password change
$_SESSION['auth_at'] = time() - 120;
$userModel->updatePassword((int) $userA['id'], password_hash('Another-Pass-77', PASSWORD_DEFAULT));
$row = $db->prepare('SELECT password_changed_at FROM users WHERE id = ?');
$row->execute([$userA['id']]);
check(!empty($row->fetchColumn()), '3.3 updatePassword stamps password_changed_at');
check(CustomerAuth::check() === false, '3.4 session opened before the change is rejected');
check(empty($_SESSION['user_id']) && empty($_SESSION['auth_at']), '3.5 stale session data is cleared');

$_SESSION = [];
$relogin = $authService->login($emailA, 'Another-Pass-77');
check($relogin['success'] === true && CustomerAuth::check() === true, '3.6 logging in with the new password works');

// changePassword() keeps the *current* session alive but kills older ones
$before = $_SESSION['auth_at'];
$chg = $authService->changePassword((int) $userA['id'], 'Another-Pass-77', 'Third-Pass-88', 'Third-Pass-88');
check($chg['success'] === true, '3.7 changePassword succeeds with a policy-compliant password');
check(CustomerAuth::check() === true, '3.8 the device that changed the password stays signed in');
$same = $authService->changePassword((int) $userA['id'], 'Third-Pass-88', 'Third-Pass-88', 'Third-Pass-88');
check($same['success'] === false, '3.9 re-using the current password is rejected');
$weakChg = $authService->changePassword((int) $userA['id'], 'Third-Pass-88', 'password1', 'password1');
check($weakChg['success'] === false, '3.10 common password rejected on change');

// Deactivated account loses its session
$db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$userA['id']]);
check(CustomerAuth::check() === false, '3.11 deactivated account session is rejected');
$db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$userA['id']]);

// ── 4. Role separation ────────────────────────────────────────────────────
echo "\n4. Role separation\n";
$_SESSION = ['admin_id' => 999, 'admin_name' => 'Ghost Admin', 'admin_email' => 'ghost@sk-sports-lab.test'];
$authService->login($emailA, 'Third-Pass-88');
check(empty($_SESSION['admin_id']) && empty($_SESSION['admin_name']), '4.1 customer login drops any admin session keys');
$adminSrc = file_get_contents(dirname(__DIR__) . '/app/services/AdminAuthService.php');
check(str_contains($adminSrc, "\$_SESSION['user_id']") && str_contains($adminSrc, 'Csrf::rotate()'), '4.2 admin OTP verification drops customer keys and rotates CSRF (source check)');

// ── 5. CSRF rotation ──────────────────────────────────────────────────────
echo "\n5. CSRF rotation\n";
$_SESSION = [];
$t0 = Csrf::token();
$authService->login($emailA, 'Third-Pass-88');
$t1 = Csrf::token();
check($t0 !== $t1 && strlen($t1) >= 32, '5.1 token rotates on login');
$authService->logout();
$t2 = Csrf::token();
check($t1 !== $t2, '5.2 token rotates on logout');
check(empty($_SESSION['user_id']) && empty($_SESSION['auth_at']), '5.3 logout clears customer keys');

// ── 6. safe_return_url ───────────────────────────────────────────────────
echo "\n6. safe_return_url()\n";
$base = app_base_url();
check(safe_return_url(null, 'services') === app_url('services'), '6.1 null → fallback');
check(safe_return_url('https://evil.example/phish', 'services') === app_url('services'), '6.2 foreign absolute URL → fallback');
check(safe_return_url('//evil.example/phish', 'services') === app_url('services'), '6.3 protocol-relative URL → fallback');
check(safe_return_url('/\\evil.example', 'services') === app_url('services'), '6.4 backslash trick → fallback');
check(safe_return_url("/my-bookings\r\nSet-Cookie: x=1", 'services') === app_url('services'), '6.5 control characters → fallback');
check(safe_return_url('javascript:alert(1)', 'services') === app_url('services'), '6.6 javascript: scheme → fallback');
check(safe_return_url('/login', 'services') === app_url('services'), '6.7 auth endpoint → fallback (no login loop)');
check(safe_return_url('/my-bookings?tab=upcoming', 'services') === app_url('my-bookings?tab=upcoming'), '6.8 relative in-app path kept');
check(safe_return_url($base . '/profile', 'services') === app_url('profile'), '6.9 own absolute URL accepted');
$basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
check(safe_return_url($basePath . '/profile', 'services') === app_url('profile'), '6.10 deployment base path is not doubled');

// ── 7. Error reporting & friendly DB failure page ────────────────────────
echo "\n7. Error handling\n";
check(error_reporting() === E_ALL, '7.1 error_reporting is E_ALL');
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
check($debug || in_array(strtolower((string) ini_get('display_errors')), ['0', '', 'off', 'stderr'], true), '7.2 display_errors off when APP_DEBUG is false');
check(ini_get('log_errors') === '1', '7.3 log_errors enabled');
$sua = dirname(__DIR__) . '/app/views/errors/service-unavailable.php';
check(file_exists($sua) && !str_contains(file_get_contents($sua), 'javascript:') && str_contains(file_get_contents($sua), 'Try again'), '7.4 friendly 503 page exists without inline JS');
$bootstrapSrc = file_get_contents(dirname(__DIR__) . '/bootstrap.php');
check(str_contains($bootstrapSrc, 'http_response_code(503)') && str_contains($bootstrapSrc, 'Retry-After'), '7.5 database failure answers 503 + Retry-After');
$routes = file_get_contents(dirname(__DIR__) . '/public/index.php');
check(!preg_match("~->get\('/logout'~", $routes) && !preg_match("~->get\('/admin/logout'~", $routes), '7.6 no GET logout routes registered');

// ── 8–11. Live HTTP ───────────────────────────────────────────────────────
echo "\n8. Live HTTP\n";
$probe = http('GET', $baseUrl . '/health');
if ($probe['code'] === 0) {
    echo "  [SKIP] server not reachable at {$baseUrl} — HTTP checks skipped\n";
} else {
    // 9. /health minimal
    $health = json_decode($probe['body'], true) ?: [];
    check($probe['code'] === 200 && ($health['success'] ?? false) === true, '8.1 /health answers 200 when the database is up');
    check(!array_key_exists('env', $health) && !array_key_exists('db', $health), '8.2 /health exposes no env/db details without a token');

    // 8. GET logout not routable
    check(in_array(http('GET', $baseUrl . '/logout')['code'], [404, 405], true), '8.3 GET /logout is not routable');
    check(in_array(http('GET', $baseUrl . '/admin/logout')['code'], [404, 405], true), '8.4 GET /admin/logout is not routable');

    // Register over HTTP: new vs existing email look identical
    $jar = tmpJar();
    $regPage = http('GET', $baseUrl . '/register', [], $jar);
    $regCsrf = csrfFrom($regPage['body']);
    check($regCsrf !== '' && str_contains($regPage['body'], 'name="terms"'), '8.5 register form has CSRF token and terms checkbox');
    check(str_contains($regPage['body'], h(PasswordPolicy::HINT)), '8.6 register form shows the password policy hint');
    $regFields = ['_csrf_token' => $regCsrf, 'name' => 'HTTP Tester', 'email' => $emailHttp, 'mobile' => '9800000003', 'password' => $goodPass, 'password_confirmation' => $goodPass, 'terms' => '1'];
    $r1 = http('POST', $baseUrl . '/register', $regFields, $jar);
    $f1 = http('GET', $baseUrl . '/login', [], $jar);
    $r2 = http('POST', $baseUrl . '/register', $regFields + ['_csrf_token' => csrfFrom($f1['body'])], $jar);
    $f2 = http('GET', $baseUrl . '/login', [], $jar);
    $loc1 = preg_match('/^Location:\s*(\S+)/mi', $r1['headers'], $m) ? $m[1] : '';
    $loc2 = preg_match('/^Location:\s*(\S+)/mi', $r2['headers'], $m) ? $m[1] : '';
    check($r1['code'] === 302 && $r2['code'] === 302 && $loc1 === $loc2 && str_contains($loc1, '/login'), '8.7 new and duplicate registration redirect identically to /login');
    check(str_contains($f1['body'], 'If this email was not already registered') && str_contains($f2['body'], 'If this email was not already registered'), '8.8 same neutral flash for both');
    check(!preg_match('/(?<!not )already registered/i', $f2['body']), '8.9 no "email already registered" leak');

    // Intended URL + POST logout
    $jar2 = tmpJar();
    $bounce = http('GET', $baseUrl . '/my-bookings?tab=upcoming', [], $jar2);
    check($bounce['code'] === 302 && str_contains($bounce['headers'], '/login'), '8.10 protected page bounces to login');
    $loginPage = http('GET', $baseUrl . '/login', [], $jar2);
    $preToken = csrfFrom($loginPage['body']);
    $loginRes = http('POST', $baseUrl . '/login', ['_csrf_token' => $preToken, 'email' => $emailHttp, 'password' => $goodPass], $jar2);
    $loc = preg_match('/^Location:\s*(\S+)/mi', $loginRes['headers'], $m) ? $m[1] : '';
    check($loginRes['code'] === 302 && str_contains($loc, '/my-bookings') && str_contains($loc, 'tab=upcoming'), '8.11 login returns to the intended in-app page');
    $profile = http('GET', $baseUrl . '/profile', [], $jar2);
    check($profile['code'] === 200, '8.12 session is authenticated');
    $postToken = csrfFrom($profile['body']);
    check($postToken !== '' && $postToken !== $preToken, '8.13 CSRF token differs after login (rotated)');
    check(str_contains($profile['body'], 'action="' . $baseUrl . '/logout"') || preg_match('~<form[^>]+action="[^"]*/logout"~', $profile['body']) === 1, '8.14 Sign Out is a POST form');
    $getLogout = http('GET', $baseUrl . '/logout', [], $jar2);
    check($getLogout['code'] === 404 && http('GET', $baseUrl . '/profile', [], $jar2)['code'] === 200, '8.15 GET /logout does not end the session');
    $noCsrf = http('POST', $baseUrl . '/logout', [], $jar2);
    check($noCsrf['code'] === 302 && http('GET', $baseUrl . '/profile', [], $jar2)['code'] === 200, '8.16 POST /logout without CSRF token is refused');
    $logout = http('POST', $baseUrl . '/logout', ['_csrf_token' => $postToken], $jar2);
    check($logout['code'] === 302, '8.17 POST /logout with token redirects');
    check(http('GET', $baseUrl . '/profile', [], $jar2)['code'] === 302, '8.18 session ended after POST logout');

    // Redirect to a foreign host via intended_url is impossible: login lands on the default page
    $jar3 = tmpJar();
    $lp = http('GET', $baseUrl . '/login', [], $jar3);
    $lr = http('POST', $baseUrl . '/login', ['_csrf_token' => csrfFrom($lp['body']), 'email' => $emailHttp, 'password' => $goodPass, 'redirect' => 'https://evil.example/'], $jar3);
    $loc = preg_match('/^Location:\s*(\S+)/mi', $lr['headers'], $m) ? $m[1] : '';
    // The app may canonicalise its base (http://localhost/sksl vs .../sksl/public); only the host matters here.
    $locHost = (string) parse_url($loc, PHP_URL_HOST);
    check($lr['code'] === 302 && $locHost !== '' && strcasecmp($locHost, (string) parse_url($baseUrl, PHP_URL_HOST)) === 0, "8.19 login never redirects off-site (got {$lr['code']} → {$loc})");

    // Stolen-session scenario over HTTP: reset password elsewhere, old cookie dies
    sleep(1); // password_changed_at has second precision; make it strictly later than auth_at
    $userModel->updatePassword((int) ($userModel->findByEmail($emailHttp)['id'] ?? 0), password_hash('Reset-Else-2026', PASSWORD_DEFAULT));
    $dead = http('GET', $baseUrl . '/profile', [], $jar3);
    check($dead['code'] === 302 && str_contains($dead['headers'], '/login'), '8.20 pre-existing session is rejected after a password change');

    foreach ([$jar, $jar2, $jar3] as $j) {
        @unlink($j);
    }
}

$cleanup();
$_SESSION = [];

echo "\n=== Auth hardening: {$pass} passed, {$fail} failed ===\n";
exit($fail === 0 ? 0 : 1);
