<?php

declare(strict_types=1);

/**
 * HTTP exposure tests (audit finding H6).
 *
 * Simulates the "document root = project root" deployment mistake by probing
 * Apache at http://localhost/sksl/ (XAMPP maps /sksl to the project root).
 * Every non-public path must be refused; the site must still work through
 * both /sksl/... and /sksl/public/....
 *
 * Run: php tests/test_http_exposure.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

echo "===================================================================\n";
echo "SKSL — HTTP Exposure Tests (H6)\n";
echo "===================================================================\n\n";

$passCount = 0;
$failCount = 0;
function check(bool $condition, string $label): void
{
    global $passCount, $failCount;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $condition ? $passCount++ : $failCount++;
}

$root = 'http://localhost/sksl';
$status = static function (string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => false, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
};

[$probeCode] = $status($root . '/public/health');
if ($probeCode === 0) {
    echo "[SKIP] Apache not reachable at {$root}\n";
    exit(0);
}

// 1. Files that live in the repo and must never be served
echo "--- Section 1: secrets, code, dumps, tests, docs are refused ---\n";
$denied = [
    '/.env', '/.env.example', '/.gitignore', '/.git/config', '/.htaccess',
    '/bootstrap.php', '/Router.php', '/composer.json', '/composer.lock', '/package.json', '/playwright.config.js',
    '/app/', '/app/models/UserModel.php', '/config/database.php', '/config/business.php',
    '/database/sksl_export.sql', '/database/migrate.php', '/database/migrations/',
    '/storage/', '/storage/logs/', '/storage/logs/app.log', '/storage/invoices/SKSL_Invoice_X.pdf',
    '/tests/', '/tests/test_phase3_auth.php', '/tests/diagnostic/check_credentials.php',
    '/cron/', '/cron/expire-holds.php', '/docs/AUDIT_REPORT.md', '/vendor/autoload.php', '/src/', '/assets/',
    '/coming%20soon.html',
];
foreach ($denied as $path) {
    [$code, $body] = $status($root . $path);
    $ok = in_array($code, [403, 404], true) && !str_contains($body, 'DB_PASSWORD') && !str_contains($body, '<?php');
    check($ok, sprintf('%-45s -> HTTP %d', $path, $code));
}

// 2. The application still works from the project root and from public/
echo "\n--- Section 2: site still served ---\n";
foreach (['/', '/services', '/health', '/login', '/public/', '/public/services', '/public/health'] as $path) {
    [$code] = $status($root . $path);
    check($code === 200, sprintf('%-45s -> HTTP %d', $path, $code));
}
foreach (['/css/app.css' => 'text/css', '/images/services/spa.jpg' => 'image/jpeg', '/public/images/services/spa.jpg' => 'image/jpeg'] as $path => $type) {
    $ch = curl_init($root . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    check($code === 200 && str_starts_with($ct, $type), sprintf('%-45s -> HTTP %d %s', $path, $code, $ct));
}

// 3. Defence in depth: each sensitive directory carries its own deny file
echo "\n--- Section 3: per-directory deny files present ---\n";
foreach (['storage', 'cron', 'database', 'tests', 'config', 'app', 'docs'] as $dir) {
    $f = dirname(__DIR__) . '/' . $dir . '/.htaccess';
    check(is_file($f) && str_contains((string) file_get_contents($f), 'Require all denied'), "{$dir}/.htaccess denies all");
}

echo "\nResults: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo ">>> ALL HTTP EXPOSURE TESTS PASSED <<<\n";
} else {
    echo ">>> {$failCount} TESTS FAILED <<<\n";
    exit(1);
}
