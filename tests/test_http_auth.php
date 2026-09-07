<?php

declare(strict_types=1);

echo "=== SKSL HTTP End-to-End Auth Test ===\n\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'sksl_cookie_');
$baseUrl   = 'http://localhost/sksl/public';

function makeRequest(string $url, string $method = 'GET', array $data = [], string $cookieJar = ''): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($cookieJar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $header = substr($response, 0, $headerSize);
    $body   = substr($response, $headerSize);

    return [
        'code'  => $httpCode,
        'url'   => $effectiveUrl,
        'body'  => $body,
    ];
}

// 1. GET /register
echo "1. GET /register...\n";
$res1 = makeRequest("{$baseUrl}/register", 'GET', [], $cookieJar);
preg_match('/name="_csrf_token" value="([a-f0-9]+)"/', $res1['body'], $m);
$csrf = $m[1] ?? '';
echo "   CSRF Token: " . substr($csrf, 0, 16) . "...\n";
if (empty($csrf)) {
    echo "   [FAIL] Could not get CSRF token.\n";
    exit(1);
}

// 2. POST /register
$testEmail = 'e2e_' . time() . '@example.com';
echo "2. POST /register ({$testEmail})...\n";
$regData = [
    '_csrf_token'           => $csrf,
    'name'                  => 'E2E Athlete',
    'email'                 => $testEmail,
    'mobile'                => '9876543210',
    'password'              => 'E2eStrongPass123!',
    'password_confirmation' => 'E2eStrongPass123!',
];
$res2 = makeRequest("{$baseUrl}/register", 'POST', $regData, $cookieJar);
echo "   Response URL: {$res2['url']} (Code: {$res2['code']})\n";
assert(str_contains($res2['body'], 'Sign In to SKSL') || str_contains($res2['url'], 'login'), 'Redirected to login after registration');
echo "   [PASS] Registration completed & redirected to login.\n";

// 3. Extract fresh CSRF token from login page
preg_match('/name="_csrf_token" value="([a-f0-9]+)"/', $res2['body'], $m);
$loginCsrf = $m[1] ?? $csrf;

// 4. POST /login
echo "3. POST /login...\n";
$loginData = [
    '_csrf_token' => $loginCsrf,
    'email'       => $testEmail,
    'password'    => 'E2eStrongPass123!',
];
$res3 = makeRequest("{$baseUrl}/login", 'POST', $loginData, $cookieJar);
echo "   Response URL: {$res3['url']} (Code: {$res3['code']})\n";
assert(str_contains($res3['url'], 'services'), 'Redirected to services after successful login');
echo "   [PASS] Login successful & redirected.\n";

// 5. GET /profile (Protected route)
echo "4. GET /profile with authenticated session...\n";
$res4 = makeRequest("{$baseUrl}/profile", 'GET', [], $cookieJar);
echo "   Response Code: {$res4['code']}\n";
assert(str_contains($res4['body'], 'E2E Athlete'), 'Profile contains registered user name');
assert(str_contains($res4['body'], $testEmail), 'Profile contains registered email');
echo "   [PASS] Protected profile accessed successfully!\n";

// 6. Cleanup
require_once dirname(__DIR__) . '/bootstrap.php';
$db = getDb();
$db->exec("DELETE FROM users WHERE email = '{$testEmail}'");
@unlink($cookieJar);

echo "\n[SUCCESS] All HTTP end-to-end authentication tests passed!\n";
