<?php
/**
 * Audit H7 — transport & cookie security headers.
 *
 * Verifies (against a running server, default http://localhost/sksl/public):
 *   - Content-Security-Policy is sent, has no 'unsafe-inline' for scripts, uses a nonce
 *   - every inline <script> in the response carries the nonce from the CSP header
 *   - no inline event handler attributes (onclick=, onsubmit=, ...) remain in the HTML
 *   - X-XSS-Protection is explicitly disabled (0)
 *   - session cookie is HttpOnly + SameSite=Strict
 *   - HSTS/upgrade-insecure-requests are only emitted on HTTPS (checked by source inspection)
 *   - production forces the Secure cookie flag regardless of SESSION_SECURE
 *
 * Run:  php tests/test_security_headers.php [base-url]
 */

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? getenv('SKSL_TEST_BASE_URL') ?: 'http://localhost/sksl/public', '/');
$pass = 0; $fail = 0;
function check(bool $ok, string $label): void {
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

function fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
    $res = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = [];
    foreach (explode("\r\n", substr($res, 0, $hs)) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))][] = trim($v);
        }
    }
    return [$code, $headers, substr($res, $hs)];
}

echo "=== Security headers test ({$baseUrl}) ===\n";

// ── 1. Source-level guarantees (no server needed) ─────────────────────────
$bootstrap = file_get_contents(__DIR__ . '/../bootstrap.php');
check(str_contains($bootstrap, "header('X-XSS-Protection: 0')"), '1.1 bootstrap disables legacy X-XSS-Protection');
check(str_contains($bootstrap, 'Strict-Transport-Security: max-age=') && str_contains($bootstrap, 'if ($requestIsHttps)'), '1.2 HSTS emitted only for HTTPS requests');
check(str_contains($bootstrap, "\$secure   = \$isProduction ? true :"), '1.3 production forces Secure session cookie');
check(str_contains($bootstrap, "session.use_strict_mode"), '1.4 session.use_strict_mode enabled');
check(preg_match('/script-src \'self\' \'nonce-/', $bootstrap) === 1 && !preg_match("/script-src[^\"]*unsafe-inline/", $bootstrap), '1.5 script-src is nonce based, no unsafe-inline');
check(str_contains($bootstrap, 'function csp_nonce()'), '1.6 csp_nonce() helper exists');

$viewFiles = array_merge(
    glob(__DIR__ . '/../app/views/*/*.php') ?: [],
    glob(__DIR__ . '/../app/views/*.php') ?: []
);
$inlineHandlers = [];
$unnoncedScripts = [];
foreach ($viewFiles as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $src, $m)) {
        $inlineHandlers[] = basename($f) . ' x' . count($m[0]);
    }
    if (preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*\bnonce=)[^>]*>/i', $src, $m)) {
        $unnoncedScripts[] = basename($f) . ' x' . count($m[0]);
    }
    if (preg_match_all('/<script[^>]*\bsrc=["\']https?:\/\/(?!checkout\.razorpay\.com\/)/i', $src, $m)) {
        $unnoncedScripts[] = basename($f) . ' external-src-not-in-CSP';
    }
}
check($inlineHandlers === [], '1.7 no inline on*= handlers in views' . ($inlineHandlers ? ' — ' . implode(', ', $inlineHandlers) : ''));
check($unnoncedScripts === [], '1.8 every inline <script> in views is nonced' . ($unnoncedScripts ? ' — ' . implode(', ', $unnoncedScripts) : ''));
check(file_exists(__DIR__ . '/../public/js/app.js'), '1.9 public/js/app.js delegated binder exists');

// ── 2. Live HTTP checks ───────────────────────────────────────────────────
$probe = @file_get_contents($baseUrl . '/health', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]));
if ($probe === false) {
    echo "[SKIP] server not reachable at {$baseUrl} — HTTP checks skipped\n";
} else {
    foreach (['/', '/services', '/login', '/admin/login'] as $path) {
        [$code, $h, $body] = fetch($baseUrl . $path);
        $csp = $h['content-security-policy'][0] ?? '';
        check($code === 200, "2.{$path} HTTP 200");
        check($csp !== '', "2.{$path} CSP header present");
        check(preg_match("/script-src[^;]*'nonce-([A-Za-z0-9_\-]+)'/", $csp, $nm) === 1, "2.{$path} CSP script-src carries a nonce");
        $nonce = $nm[1] ?? '';
        check(!preg_match("/script-src[^;]*unsafe-inline/", $csp), "2.{$path} script-src has no unsafe-inline");
        check(str_contains($csp, "object-src 'none'") && str_contains($csp, "base-uri 'self'") && str_contains($csp, "frame-ancestors 'self'") && str_contains($csp, "form-action 'self'"), "2.{$path} CSP has object-src/base-uri/frame-ancestors/form-action");
        // Every inline <script> in the body must carry the header nonce
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>/i', $body, $tags);
        $bad = array_filter($tags[0], static fn ($t) => !str_contains($t, 'nonce="' . $nonce . '"'));
        check(count($tags[0]) > 0 && $bad === [], "2.{$path} all " . count($tags[0]) . " inline <script> tags carry the header nonce");
        check(!preg_match('/\son(click|submit|input|error|load|change)\s*=/i', $body), "2.{$path} no inline event handlers in HTML");
        check(($h['x-xss-protection'][0] ?? '') === '0', "2.{$path} X-XSS-Protection: 0");
        check(($h['x-content-type-options'][0] ?? '') === 'nosniff', "2.{$path} X-Content-Type-Options: nosniff");
        check(($h['x-frame-options'][0] ?? '') === 'SAMEORIGIN', "2.{$path} X-Frame-Options: SAMEORIGIN");
        check(str_contains($h['referrer-policy'][0] ?? '', 'strict-origin'), "2.{$path} Referrer-Policy set");
        if ($path === '/') {
            $cookie = implode(' ', $h['set-cookie'] ?? []);
            check(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Strict') !== false, '2./ session cookie HttpOnly + SameSite=Strict');
            $isHttps = str_starts_with($baseUrl, 'https://');
            check($isHttps ? isset($h['strict-transport-security']) : !isset($h['strict-transport-security']), '2./ HSTS ' . ($isHttps ? 'present on HTTPS' : 'absent on plain HTTP (only sent over TLS)'));
            check(preg_match('/nonce-[A-Za-z0-9_\-]{20,}/', $csp) === 1, '2./ nonce is at least 20 chars of base64url');
        }
    }
    // Nonce must differ between requests
    [, $h1] = fetch($baseUrl . '/');
    [, $h2] = fetch($baseUrl . '/');
    check(($h1['content-security-policy'][0] ?? 'a') !== ($h2['content-security-policy'][0] ?? 'a'), '2.x nonce is regenerated per request');
}

echo "\nResults: {$pass} Passed, {$fail} Failed\n";
if ($fail === 0) {
    echo ">>> ALL SECURITY HEADER TESTS PASSED <<<\n";
} else {
    echo ">>> {$fail} TESTS FAILED <<<\n";
    exit(1);
}
