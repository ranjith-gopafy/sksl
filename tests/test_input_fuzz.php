<?php

/**
 * Hostile-input sweep: every route, as an anonymous visitor, a customer and an admin, is sent arrays,
 * very long text, control characters, huge numbers and markup in every parameter the code reads.
 * Nothing may answer 5xx, leak a trace or path, or write a PHP warning/notice/fatal to the error log.
 *
 * Admin and customer POSTs use ids that do not exist (999999999) so a valid CSRF token cannot change
 * real data; any rows the sweep still manages to create are removed afterwards.
 *
 * Needs the site running (XAMPP/Apache at http://localhost/sksl/public, or pass a base URL as argv[1]).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\RateLimit;
use App\Models\AdminModel;
use App\Models\UserModel;

$base = rtrim($argv[1] ?? getenv('SKSL_TEST_BASE_URL') ?: 'http://localhost/sksl/public', '/');
$root = dirname(__DIR__);
$db   = getDb();
$pass = 0;
$fail = 0;
$failures = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) { $pass++; return; }
    $fail++;
    $key = preg_replace('/\d+/', 'N', $name);
    if (!isset($failures[$key])) {
        $failures[$key] = true;
        echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

final class Client
{
    public string $jar;
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'sksl_fz'); }
    public function __destruct() { @unlink($this->jar); }
    /** @return array{code:int, body:string, location:string} */
    public function req(string $method, string $url, array|string|null $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body); }
        $res = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        preg_match('/^Location:\s*(.+)$/mi', substr($res, 0, $size), $m);
        return ['code' => $code, 'body' => substr($res, $size), 'location' => trim($m[1] ?? '')];
    }
    public function token(string $url): string
    {
        $r = $this->req('GET', $url);
        return preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : (preg_match('/name="csrf-token"\s+content="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : '');
    }
}

if ((new Client())->req('GET', $base . '/health')['code'] === 0) {
    echo "[SKIP] site not reachable at $base\n";
    exit(0);
}
echo "SKSL — hostile input sweep\n";
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip', 'cust_login', 'cust_login_ip', 'cust_register', 'cust_register_ip', 'cust_forgot', 'cust_forgot_ip', 'cust_reset_ip'] as $s) { RateLimit::clearScope($s); }

// ---------------------------------------------------------------- what to fuzz
$src = (string) file_get_contents($root . '/public/index.php');
preg_match_all('/\$router->(get|post)\(\s*\'([^\']+)\'/', $src, $mm, PREG_SET_ORDER);
$routes = [];
foreach ($mm as [, $verb, $path]) { $routes[] = [strtoupper($verb), $path]; }

$names = [];
$files = array_merge(glob($root . '/app/controllers/*.php') ?: [], glob($root . '/app/controllers/admin/*.php') ?: [], glob($root . '/app/services/*.php') ?: [], glob($root . '/app/middleware/*.php') ?: []);
foreach ($files as $f) {
    $code = (string) file_get_contents($f);
    if (preg_match_all('/\$_(?:POST|GET|REQUEST)\[\s*\'([A-Za-z0-9_\-]+)\'\s*\]/', $code, $m)) { $names = array_merge($names, $m[1]); }
    if (preg_match_all('/(?:\$data|\$input|\$body|\$payload|\$json)\[\s*\'([A-Za-z0-9_\-]+)\'\s*\]/', $code, $m)) { $names = array_merge($names, $m[1]); }
}
$names = array_values(array_unique(array_merge($names, ['id', 'ref', 'token', 'tab', 'status', 'date', 'service_id', 'start_time', 'search', 'page', 'redirect', 'email', 'password', 'name', 'mobile', 'otp'])));
sort($names);
check('F0 collected parameter names', count($names) > 25, (string) count($names));

$variants = [
    'array'        => fn () => ['x'],
    'nested array' => fn () => ['a' => ['b' => ['c' => 'd']]],
    'long text'    => fn () => str_repeat('Ab9 ', 1500),
    'control chars' => fn () => "a\x00b\x1f\r\n\t\u{202E}\u{0}z",
    'sql quote'    => fn () => "' OR '1'='1'; --",
    'numbers'      => fn () => '-1',
    'markup'       => fn () => '<img src=x onerror=alert(1)>"\'',
    'unicode'      => fn () => "Zoë 😀 \u{0301}\u{200B}",
];

// ---------------------------------------------------------------- sessions
$tag = 'fz' . substr(bin2hex(random_bytes(3)), 0, 6);
$pwd = 'Fuzzing!' . random_int(1000, 9999);
$userId = (new UserModel())->create('Fuzz Customer', "{$tag}@sk-sports-lab.test", '9876543210', password_hash($pwd, PASSWORD_BCRYPT));
$adminModel = new AdminModel();
$adminId = $adminModel->create('Fuzz Admin', "{$tag}_admin@sk-sports-lab.test", 'active');

$anon = new Client();
$cust = new Client();
$r = $cust->req('POST', $base . '/login', ['email' => "{$tag}@sk-sports-lab.test", 'password' => $pwd, '_csrf_token' => $cust->token($base . '/login')]);
check('F1 fuzz customer signed in', $r['code'] === 302 && !str_contains($r['location'], 'login'), $r['location']);
$adm = new Client();
$adm->req('POST', $base . '/admin/login', ['email' => "{$tag}_admin@sk-sports-lab.test", '_csrf_token' => $adm->token($base . '/admin/login')]);
$adminModel->createOtp($adminId, password_hash('246810', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 600));
$r = $adm->req('POST', $base . '/admin/verify-otp', ['otp' => '246810', '_csrf_token' => $adm->token($base . '/admin/verify-otp')]);
check('F2 fuzz admin signed in', $r['code'] === 302 && str_contains($r['location'], 'dashboard'), $r['location']);

// snapshot so anything the sweep creates can be removed
$tables = ['closed_dates', 'manual_blocks', 'availability_rules', 'packages', 'package_items', 'service_duration_options', 'bookings', 'booking_holds', 'payments', 'customer_packages', 'service_categories'];
$maxId = [];
foreach ($tables as $t) { $maxId[$t] = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM `$t`")->fetchColumn(); }
$svcSnapshot = $db->query('SELECT id, name, price, capacity, description, status, image FROM services ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$settingsSnapshot = null;
try { $settingsSnapshot = $db->query('SELECT * FROM site_settings')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $settingsSnapshot = null; }

$logFile = $root . '/storage/logs/php-error.log';
clearstatcache();
$logStart = is_file($logFile) ? filesize($logFile) : 0;

$leaks = fn (string $b): bool => (bool) preg_match('/Stack trace|Fatal error|Parse error|Warning:|Notice:|Deprecated:|PDOException|TypeError|on line \d+|[A-Z]:\\\\Users|\/xampp\/|\/htdocs\//i', $b);
$sub = fn (string $p, string $id = '999999999') => str_replace(['{id}', '{ref}'], [$id, 'SKSL-20200101-ZZZZZZ'], $p);

$sessions = [['anon', $anon], ['customer', $cust], ['admin', $adm]];
$count = 0;
foreach ($sessions as [$who, $client]) {
    $tok = $client->token($base . ($who === 'admin' ? '/admin/dashboard' : ($who === 'customer' ? '/my-bookings' : '/login')));
    foreach ($routes as [$verb, $path]) {
        if (in_array($path, ['/logout', '/api/logout', '/admin/logout'], true)) { continue; } // would end the session mid-sweep
        $url = $base . $sub($path);
        foreach ($variants as $vname => $make) {
            $value = $make();
            $label = "$who $verb $path [$vname]";
            if ($verb === 'GET') {
                $params = [];
                foreach ($names as $n) { $params[$n] = $value; }
                $r = $client->req('GET', $url . '?' . http_build_query($params));
                $count++;
                check("$label stays below 5xx", $r['code'] < 500, (string) $r['code']);
                check("$label leaks nothing", !$leaks($r['body']), substr(trim(strip_tags($r['body'])), 0, 100));
                continue;
            }
            $form = ['_csrf_token' => $tok];
            foreach ($names as $n) { if ($n !== '_csrf_token') { $form[$n] = $value; } }
            $r = $client->req('POST', $url, $form);
            $count++;
            check("$label (form) stays below 5xx", $r['code'] < 500 || ($path === '/api/payment/webhook' && $r['code'] === 503), (string) $r['code']);
            check("$label (form) leaks nothing", !$leaks($r['body']), substr(trim(strip_tags($r['body'])), 0, 100));
            $json = [];
            foreach ($names as $n) { $json[$n] = $value; }
            $r = $client->req('POST', $url, json_encode($json, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}', ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $tok]);
            $count++;
            check("$label (json) stays below 5xx", $r['code'] < 500 || ($path === '/api/payment/webhook' && $r['code'] === 503), (string) $r['code']);
            check("$label (json) leaks nothing", !$leaks($r['body']), substr(trim(strip_tags($r['body'])), 0, 100));
        }
    }
}
// malformed JSON bodies on the API
foreach ([['/api/bookings/hold', '{"service_id":'], ['/api/payment/verify', '[[[[[[[['], ['/api/payment/create-order', 'null'], ['/api/bookings/professional', '"just a string"'], ['/api/packages/1/purchase', "\xff\xfe\x00"]] as [$p, $raw]) {
    $tok = $cust->token($base . '/my-bookings');
    $r = $cust->req('POST', $base . $p, $raw, ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $tok]);
    check("F3 malformed JSON to $p stays below 5xx", $r['code'] < 500 && !$leaks($r['body']), (string) $r['code']);
    $count++;
}
echo "  ($count requests sent)\n";

// ---------------------------------------------------------------- the error log
clearstatcache();
$new = '';
if (is_file($logFile) && filesize($logFile) > $logStart) {
    $fh = fopen($logFile, 'rb');
    fseek($fh, $logStart);
    $new = (string) stream_get_contents($fh);
    fclose($fh);
}
$bad = [];
foreach (explode("\n", $new) as $line) {
    if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|Uncaught/', $line) && !str_contains($line, 'fwrite(): Write of')) {
        $bad[preg_replace(['/^\[[^\]]*\]\s*/', '/\d+/'], ['', 'N'], $line)] = ($bad[preg_replace(['/^\[[^\]]*\]\s*/', '/\d+/'], ['', 'N'], $line)] ?? 0) + 1;
    }
}
foreach ($bad as $line => $n) { check('L1 error log: ' . substr($line, 0, 220) . " (×$n)", false); }
check('L2 the sweep wrote no PHP warnings, notices or fatals to the error log', $bad === []);

// ---------------------------------------------------------------- did anything real change?
$svcNow = $db->query('SELECT id, name, price, capacity, description, status, image FROM services ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check('S1 no modality was changed by the sweep', $svcNow === $svcSnapshot);
if ($svcNow !== $svcSnapshot) {
    $upd = $db->prepare('UPDATE services SET name = ?, price = ?, capacity = ?, description = ?, status = ?, image = ? WHERE id = ?');
    foreach ($svcSnapshot as $s) { $upd->execute([$s['name'], $s['price'], $s['capacity'], $s['description'], $s['status'], $s['image'], $s['id']]); }
}
if ($settingsSnapshot !== null) {
    check('S2 site settings were not changed by the sweep', $db->query('SELECT * FROM site_settings')->fetchAll(PDO::FETCH_ASSOC) === $settingsSnapshot);
}
foreach (['closed_dates', 'manual_blocks', 'availability_rules', 'packages', 'service_duration_options', 'service_categories'] as $t) {
    $made = (int) $db->query("SELECT COUNT(*) FROM `$t` WHERE id > {$maxId[$t]}")->fetchColumn();
    check("S3 the sweep created no rows in $t", $made === 0, "$made new rows");
    if ($made > 0) {
        if ($t === 'packages') { $db->exec("DELETE FROM package_items WHERE package_id > {$maxId['packages']}"); }
        $db->exec("DELETE FROM `$t` WHERE id > {$maxId[$t]}");
    }
}

// ---------------------------------------------------------------- cleanup
$db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = $userId)");
$db->exec("DELETE FROM booking_holds WHERE user_id = $userId");
$db->exec("DELETE FROM bookings WHERE user_id = $userId");
$db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id = $userId)");
$db->exec("DELETE FROM customer_packages WHERE user_id = $userId");
$db->exec("DELETE FROM password_resets WHERE user_id = $userId");
$db->exec("DELETE FROM users WHERE id = $userId");
$db->exec("DELETE FROM users WHERE email LIKE '%@sk-sports-lab.test' AND created_at >= NOW() - INTERVAL 1 HOUR AND email NOT LIKE 'e2e%' AND id > {$userId} - 0 AND name NOT LIKE 'Fuzz%' AND 0");
$db->exec("DELETE FROM admin_otps WHERE admin_id = $adminId");
$db->exec("DELETE FROM admins WHERE id = $adminId");
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip', 'cust_login', 'cust_login_ip', 'cust_register', 'cust_register_ip', 'cust_forgot', 'cust_forgot_ip', 'cust_reset_ip'] as $s) { RateLimit::clearScope($s); }

echo "=== Input sweep: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
