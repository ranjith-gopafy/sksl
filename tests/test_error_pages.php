<?php

/**
 * What a visitor sees when things go wrong, over real HTTP: a crash in the code, a database that is
 * down, an unknown page, odd URLs. Always a friendly page (or JSON for API callers), the right status
 * code, security headers, and never a stack trace, file path or secret.
 *
 * Uses a throw-away script in public/ that fails on purpose; it is deleted afterwards.
 * Needs the site running (XAMPP/Apache at http://localhost/sksl/public, or pass a base URL as argv[1]).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

$base = rtrim($argv[1] ?? getenv('SKSL_TEST_BASE_URL') ?: 'http://localhost/sksl/public', '/');
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++;
    echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}
/** @return array{code:int, headers:string, body:string} */
function http(string $method, string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    $res = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($res, 0, $size), 'body' => substr($res, $size)];
}
$leaks = fn (string $b): bool => (bool) preg_match('/Stack trace|Fatal error|Parse error|Warning:|Notice:|PDOException|on line \d+|[A-Z]:\\\\Users|\/xampp\/|\/htdocs\/|hunter2|DB_PASS/i', $b);

if (http('GET', $base . '/health')['code'] === 0) {
    echo "[SKIP] site not reachable at $base\n";
    exit(0);
}
echo "SKSL — error pages\n";

$publicDir = dirname(__DIR__) . '/public';
$made = [];
$script = function (string $name, string $body) use ($publicDir, &$made): string {
    $file = "$publicDir/$name.php";
    // Production runs with APP_DEBUG off; this development machine has it on (raw errors on screen by design).
    file_put_contents($file, "<?php\n\$_ENV['APP_DEBUG'] = 'false';\n$body\n");
    $made[] = $file;
    return $name . '.php';
};
register_shutdown_function(function () use (&$made) { foreach ($made as $f) { @unlink($f); } });

$tag = 'zz_fail_' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------- A. the code crashes
$throw = $script("{$tag}_throw", "require dirname(__DIR__) . '/bootstrap.php';\nthrow new RuntimeException('Deliberate test failure with a secret: DB_PASSWORD=hunter2');");
$r = http('GET', "$base/$throw", ['Accept: text/html']);
check('A1 an uncaught exception answers 500', $r['code'] === 500, (string) $r['code']);
check('A2 …with a friendly page, not a trace', !$leaks($r['body']) && strlen(trim(strip_tags($r['body']))) > 20, substr(trim(strip_tags($r['body'])), 0, 120));
check('A3 …that tells the visitor what to do next', (bool) preg_match('/try again|something went wrong|contact|home/i', $r['body']));
check('A4 …and does not leak the secret in the message', !str_contains($r['body'], 'hunter2'));
check('A5 …and sends the security headers anyway', (bool) preg_match('/^X-Content-Type-Options:\s*nosniff/mi', $r['headers']));
check('A6 …and is not cached', (bool) preg_match('/^Cache-Control:[^\r\n]*(no-store|no-cache|private)/mi', $r['headers']), $r['headers']);
$r = http('GET', "$base/$throw", ['Accept: application/json']);
$json = json_decode($r['body'], true);
check('A7 an API caller gets JSON with the same 500', $r['code'] === 500 && is_array($json) && ($json['success'] ?? null) === false, substr($r['body'], 0, 160));
check('A8 …without the secret', !str_contains($r['body'], 'hunter2') && !$leaks($r['body']));
$r = http('POST', "$base/$throw", ['Content-Type: application/json', 'Accept: application/json']);
check('A9 a crashing POST behaves the same', $r['code'] === 500 && !str_contains($r['body'], 'hunter2'));

$undefined = $script("{$tag}_fatal", "require dirname(__DIR__) . '/bootstrap.php';\nthis_function_does_not_exist();");
$r = http('GET', "$base/$undefined");
check('A10 calling a missing function (PHP fatal) answers a friendly 500 too', $r['code'] === 500 && !$leaks($r['body']), $r['code'] . ' ' . substr(trim(strip_tags($r['body'])), 0, 100));
$typeErr = $script("{$tag}_type", "require dirname(__DIR__) . '/bootstrap.php';\nstrlen([]);");
$r = http('GET', "$base/$typeErr");
check('A11 a TypeError answers a friendly 500', $r['code'] === 500 && !$leaks($r['body']), (string) $r['code']);

// ---------------------------------------------------------------- B. the database is down
$down = $script("{$tag}_db", "\$_ENV['DB_HOST'] = '127.0.0.1';\n\$_ENV['DB_PORT'] = '1';\n\$_ENV['DB_HOSTNAME'] = '127.0.0.1';\nrequire dirname(__DIR__) . '/bootstrap.php';\ngetDb();\necho 'reached';");
$r = http('GET', "$base/$down", ['Accept: text/html']);
check('B1 a database that cannot be reached answers 503 (try later), not 500', $r['code'] === 503, (string) $r['code']);
check('B2 …with Retry-After', (bool) preg_match('/^Retry-After:\s*\d+/mi', $r['headers']), $r['headers']);
check('B3 …with a friendly page and no connection details', !$leaks($r['body']) && !preg_match('/127\.0\.0\.1|SQLSTATE|mysql/i', $r['body']), substr(trim(strip_tags($r['body'])), 0, 140));
check('B4 …and the page does not say "reached"', !str_contains($r['body'], 'reached'));
$r = http('GET', "$base/$down", ['Accept: application/json']);
$json = json_decode($r['body'], true);
check('B5 an API caller gets a JSON 503', $r['code'] === 503 && is_array($json) && ($json['success'] ?? null) === false, $r['code'] . ' ' . substr($r['body'], 0, 100));

// ---------------------------------------------------------------- C. unknown pages and odd URLs
$r = http('GET', "$base/no-such-page-$tag", ['Accept: text/html']);
check('C1 an unknown page answers 404', $r['code'] === 404, (string) $r['code']);
check('C2 …with the site look (navigation/home link), not a server page', str_contains($r['body'], 'href') && stripos($r['body'], 'Apache') === false && stripos($r['body'], 'Not Found') !== false || str_contains($r['body'], 'page'), substr(trim(strip_tags($r['body'])), 0, 100));
check('C3 …that leaks nothing', !$leaks($r['body']));
check('C4 …and keeps the security headers', (bool) preg_match('/^X-Frame-Options:/mi', $r['headers']) && (bool) preg_match('/^Content-Security-Policy:/mi', $r['headers']));
$r = http('GET', "$base/api/no-such-endpoint-$tag", ['Accept: application/json']);
$json = json_decode($r['body'], true);
check('C5 an unknown API path answers JSON 404', $r['code'] === 404 && is_array($json), $r['code'] . ' ' . substr($r['body'], 0, 100));
foreach (['/Services', '/services/', '//services', '/services?x=1', '/services#frag', '/SERVICES', '/index.php', '/index.php/services', '/./services', '/services/.', '/%73ervices'] as $odd) {
    $r = http('GET', $base . $odd);
    check("C6 odd URL $odd is handled (200/301/302/404, never 5xx)", $r['code'] < 500 && !$leaks($r['body']), (string) $r['code']);
}
foreach (['HEAD', 'OPTIONS'] as $m) {
    $r = http($m, "$base/services");
    check("C7 $m /services is handled without a server error", $r['code'] < 500, (string) $r['code']);
}
$r = http('HEAD', "$base/services");
check('C8 HEAD returns no body', $r['body'] === '');

// ---------------------------------------------------------------- D. health check and static files
$r = http('GET', "$base/health");
$json = json_decode($r['body'], true);
check('D1 /health answers 200 JSON when all is well', $r['code'] === 200 && ($json['success'] ?? false) === true);
check('D2 /health reveals no environment or database details', !array_key_exists('env', (array) $json) && !array_key_exists('db', (array) $json) && !preg_match('/localhost|sksl|root/i', $r['body']));
foreach (['/css/app.css', '/js/app.js', '/images/sksl-logo.png', '/robots.txt', '/sitemap.xml'] as $file) {
    $r = http('GET', $base . $file);
    check("D3 $file is served", $r['code'] === 200 && strlen($r['body']) > 10, (string) $r['code']);
}
$r = http('GET', "$base/css/does-not-exist-$tag.css");
check('D4 a missing static file is a 404, not a crash or the home page', $r['code'] === 404, (string) $r['code']);
$r = http('GET', "$base/images/services/does-not-exist-$tag.jpg");
check('D5 a missing image is a 404', $r['code'] === 404);
foreach (['/.env', '/composer.json', '/storage/logs/php-error.log', '/uploads/../../.env'] as $secret) {
    $r = http('GET', $base . $secret);
    check("D6 $secret is not served", $r['code'] >= 400 && !str_contains($r['body'], 'DB_PASSWORD') && !str_contains($r['body'], '"require"'), (string) $r['code']);
}

foreach ($made as $f) { @unlink($f); }
echo "=== Error pages: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
