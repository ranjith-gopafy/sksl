<?php

/**
 * Access control, CSRF and request-hardening checks over real HTTP, route by route.
 *
 * Every route registered in public/index.php is requested anonymously, as a customer and as an admin:
 * protected pages must refuse the wrong visitor, every POST must demand a valid CSRF token, nothing may
 * answer 500 or leak a stack trace / server path, and one customer must never reach another's data.
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
$db   = getDb();
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++;
    echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

/** Tiny browser: cookie jar, no redirect following, returns code, headers, body. */
final class Browser
{
    public string $jar;
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'sksl_jar'); }
    public function __destruct() { @unlink($this->jar); }

    /** @return array{code:int, headers:string, body:string, location:string} */
    public function req(string $method, string $url, array|string|null $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }
        $res = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $h = substr($res, 0, $size);
        preg_match('/^Location:\s*(.+)$/mi', $h, $m);
        return ['code' => $code, 'headers' => $h, 'body' => substr($res, $size), 'location' => trim($m[1] ?? '')];
    }

    public function token(string $url): string
    {
        $r = $this->req('GET', $url);
        preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $r['body'], $m)
            || preg_match('/value="([a-f0-9]{64})"\s+name="_csrf_token"/i', $r['body'], $m)
            || preg_match('/csrf[^"\']*["\']\s*content="([a-f0-9]{64})"/i', $r['body'], $m)
            || preg_match('/name="csrf-token"\s+content="([a-f0-9]{64})"/i', $r['body'], $m);
        return $m[1] ?? '';
    }

    public function cookie(string $name): string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = ltrim($line, '#');
            $p = explode("\t", $line);
            if (count($p) >= 7 && $p[5] === $name) { return $p[6]; }
        }
        return '';
    }
}

$leaks = fn (string $b): bool => (bool) preg_match('/Stack trace|Fatal error|Parse error|Warning:|Notice:|Deprecated:|PDOException|on line \d+|[A-Z]:\\\\Users|\/xampp\/|\/htdocs\//i', $b);

echo "SKSL — access control and request hardening\n";
$probe = (new Browser())->req('GET', $base . '/health');
if ($probe['code'] === 0) {
    echo "[SKIP] site not reachable at $base\n";
    exit(0);
}
RateLimit::clearScope('admin_otp_req');
RateLimit::clearScope('admin_otp_req_ip');
RateLimit::clearScope('admin_otp_verify');
RateLimit::clearScope('admin_otp_verify_ip');

// ---------------------------------------------------------------- the route table
$src = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
preg_match_all('/\$router->(get|post)\(\s*\'([^\']+)\'/', $src, $mm, PREG_SET_ORDER);
$routes = [];
foreach ($mm as [, $verb, $path]) {
    $routes[] = [strtoupper($verb), $path, str_replace(['{id}', '{ref}'], ['1', 'SKSL-20200101-AAAAAA'], $path)];
}
check('R0 found the route table', count($routes) > 60, (string) count($routes));
$isAdmin     = fn (string $p) => $p === '/admin' || str_starts_with($p, '/admin/');
$adminAuth   = ['/admin/login', '/admin/verify-otp', '/admin/logout'];
$customerGet = ['/my-bookings', '/my-packages', '/profile', '/my-packages/{ref}/invoice', '/bookings/{ref}/invoice'];

$anon = new Browser();
$noise = ['Accept: text/html'];

// ---------------------------------------------------------------- A. anonymous visitor
foreach ($routes as [$verb, $path, $url]) {
    $r = $anon->req($verb, $base . $url, $verb === 'POST' ? ['x' => '1'] : null, $noise);
    $label = "$verb $path";
    // The webhook answers 503 on purpose until RAZORPAY_WEBHOOK_SECRET is configured (it fails closed).
    check("A1 $label never answers 5xx", $r['code'] < 500 || ($path === '/api/payment/webhook' && $r['code'] === 503), (string) $r['code']);
    check("A2 $label leaks no trace or server path", !$leaks($r['body']), substr(strip_tags($r['body']), 0, 120));
    if ($verb === 'GET' && $isAdmin($path) && !in_array($path, $adminAuth, true)) {
        check("A3 $label refuses an anonymous visitor", $r['code'] !== 200, (string) $r['code']);
        check("A3b $label sends them to the admin sign-in", $r['code'] === 302 && str_contains($r['location'], 'admin/login'), $r['location']);
    }
    if ($verb === 'GET' && in_array($path, $customerGet, true)) {
        check("A4 $label refuses an anonymous visitor", $r['code'] === 302 && str_contains($r['location'], 'login'), $r['code'] . ' ' . $r['location']);
    }
    if ($verb === 'POST') {
        $ok = in_array($r['code'], [302, 400, 401, 403, 404, 405, 419, 422, 429, 503], true);
        check("A5 $label without a CSRF token is refused", $ok && !($r['code'] === 302 && str_contains($r['location'], 'dashboard')), $r['code'] . ' ' . $r['location']);
    }
}
// POST-only routes must not answer GET with content
foreach ($routes as [$verb, $path, $url]) {
    if ($verb !== 'POST') { continue; }
    $hasGet = false;
    foreach ($routes as [$v2, $p2]) { if ($v2 === 'GET' && $p2 === $path) { $hasGet = true; } }
    if ($hasGet) { continue; }
    $r = $anon->req('GET', $base . $url);
    check("A6 GET $path (POST-only) is not served", in_array($r['code'], [404, 405, 302, 403], true), (string) $r['code']);
}

// ---------------------------------------------------------------- B. customers
$tag = 'ac' . substr(bin2hex(random_bytes(3)), 0, 6);
$users = new UserModel();
$pwd = 'Access!' . random_int(1000, 9999);
$mk = fn (string $l) => $users->create("Access $l", "{$tag}_{$l}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash($pwd, PASSWORD_BCRYPT));
$uA = $mk('a');
$uB = $mk('b');
$emailA = "{$tag}_a@sk-sports-lab.test";
$emailB = "{$tag}_b@sk-sports-lab.test";

$signIn = function (string $email) use ($base, $pwd): array {
    $b = new Browser();
    $tok = $b->token($base . '/login');
    $before = $b->cookie('PHPSESSID') ?: $b->cookie('sksl_session');
    $r = $b->req('POST', $base . '/login', ['email' => $email, 'password' => $pwd, '_csrf_token' => $tok]);
    return [$b, $r, $tok, $before];
};
[$ca, $loginRes, $tokA, $sidBefore] = $signIn($emailA);
check('B1 customer sign-in succeeds', $loginRes['code'] === 302 && !str_contains($loginRes['location'], 'login'), $loginRes['code'] . ' ' . $loginRes['location']);
$sessName = preg_match('/Set-Cookie:\s*([A-Za-z0-9_]+)=/i', $loginRes['headers'] . $anon->req('GET', $base . '/login')['headers'], $sm) ? $sm[1] : 'PHPSESSID';
$sidAfter = $ca->cookie($sessName);
check('B2 the session id changes at sign-in (no session fixation)', $sidBefore !== '' && $sidAfter !== '' && $sidBefore !== $sidAfter, "$sidBefore -> $sidAfter");
$loginPage = $anon->req('GET', $base . '/login');
check('B3 the session cookie is HttpOnly with a SameSite policy', (bool) preg_match('/Set-Cookie:[^\r\n]*HttpOnly/i', $loginPage['headers'] . $loginRes['headers']) && (bool) preg_match('/Set-Cookie:[^\r\n]*SameSite=(Lax|Strict)/i', $loginPage['headers'] . $loginRes['headers']), $loginPage['headers']);

foreach (['/my-bookings', '/my-packages', '/profile'] as $p) {
    $r = $ca->req('GET', $base . $p);
    check("B4 signed-in customer can open $p", $r['code'] === 200, (string) $r['code']);
}
// customer on the admin side
foreach ($routes as [$verb, $path, $url]) {
    if (!$isAdmin($path) || in_array($path, $adminAuth, true)) { continue; }
    $fresh = $ca->token($base . '/my-bookings') ?: $tokA;
    $r = $ca->req($verb, $base . $url, $verb === 'POST' ? ['_csrf_token' => $fresh, 'status' => 'cancelled', 'closed_date' => date('Y-m-d', strtotime('+9 days'))] : null, $noise);
    check("B5 customer cannot use $verb $path", $r['code'] !== 200 && !($r['code'] === 302 && !str_contains($r['location'], 'admin/login')) && $r['code'] < 500, $r['code'] . ' ' . $r['location']);
}
$svcStatus = fn () => $db->query("SELECT GROUP_CONCAT(status ORDER BY id) FROM services")->fetchColumn();
$before = $svcStatus();
$ca->req('POST', $base . '/admin/services/1/toggle', ['_csrf_token' => $ca->token($base . '/my-bookings')]);
check('B6 a customer toggling a modality changes nothing', $svcStatus() === $before);

// CSRF
$r = $ca->req('POST', $base . '/profile', ['name' => 'Hacked', 'mobile' => '9876543210']);
check('B7 a signed-in POST without a CSRF token is refused', in_array($r['code'], [302, 403], true) && $db->query("SELECT name FROM users WHERE id = $uA")->fetchColumn() !== 'Hacked');
$other = new Browser();
$foreignTok = $other->token($base . '/login');
$r = $ca->req('POST', $base . '/profile', ['name' => 'Hacked2', 'mobile' => '9876543210', '_csrf_token' => $foreignTok]);
check("B8 a CSRF token from another session is refused", $db->query("SELECT name FROM users WHERE id = $uA")->fetchColumn() !== 'Hacked2');
$r = $ca->req('POST', $base . '/api/bookings/hold', json_encode(['service_id' => 1]), ['Content-Type: application/json', 'Accept: application/json']);
check('B9 a JSON API POST without CSRF is 403', $r['code'] === 403, (string) $r['code']);
$tokNow = $ca->token($base . '/my-bookings');
$r = $ca->req('POST', $base . '/api/bookings/hold', json_encode(['service_id' => 1]), ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $tokNow]);
check('B10 …but the header token is accepted (then validation answers, not CSRF)', $r['code'] !== 403 && $r['code'] < 500, (string) $r['code']);

// IDOR: B's paid booking must be invisible to A
$refB = 'SKSL-' . date('Ymd', strtotime('+6 days')) . '-' . strtoupper(bin2hex(random_bytes(3)));
$db->prepare("INSERT INTO bookings (booking_reference, user_id, service_id, booking_type, payment_mode, booking_date, start_time, end_time, service_name_snapshot, service_duration_minutes, base_amount, gst_percent, gst_amount, total_amount, booking_status, payment_status, invoice_number)
              VALUES (?, ?, 2, 'standard', 'payment_required', ?, '10:00:00', '10:15:00', 'Sauna', 15, 507.62, 18, 91.38, 599.00, 'confirmed', 'paid', ?)")
   ->execute([$refB, $uB, date('Y-m-d', strtotime('+6 days')), 'INV-' . $tag]);
$bookingBId = (int) $db->lastInsertId();
$r = $ca->req('GET', $base . '/bookings/' . $refB . '/invoice');
check("B11 customer A cannot download B's invoice", $r['code'] !== 200 || !str_starts_with($r['body'], '%PDF'), (string) $r['code']);
$r = $ca->req('GET', $base . '/booking-confirmation?ref=' . $refB);
check("B12 customer A cannot view B's confirmation page", !str_contains($r['body'], $refB) && !str_contains($r['body'], 'Access b'), (string) $r['code']);
$r = $ca->req('GET', $base . '/my-bookings?tab=all');
check("B13 customer A's list does not show B's booking", !str_contains($r['body'], $refB));
$r = $ca->req('POST', $base . '/my-bookings/' . $refB . '/cancel', ['_csrf_token' => $ca->token($base . '/my-bookings')]);
check("B14 customer A cannot cancel B's booking", $db->query("SELECT booking_status FROM bookings WHERE id = $bookingBId")->fetchColumn() === 'confirmed');
$r = $ca->req('POST', $base . '/api/payment/create-order', json_encode(['booking_reference' => $refB]), ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $ca->token($base . '/my-bookings')]);
check("B15 customer A cannot open a payment order for B's booking", $r['code'] !== 200 || !str_contains($r['body'], '"success":true'), $r['code'] . substr($r['body'], 0, 100));
$r = $ca->req('POST', $base . '/api/bookings/hold/release', json_encode(['booking_reference' => $refB]), ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $ca->token($base . '/my-bookings')]);
check('B16 releasing someone else\'s reference is refused or harmless', $r['code'] < 500);

// logout
$r = $ca->req('GET', $base . '/logout');
check('B17 GET /logout does not sign anyone out', $ca->req('GET', $base . '/my-bookings')['code'] === 200 && $r['code'] !== 200);
$r = $ca->req('POST', $base . '/logout', []);
check('B18 POST /logout without CSRF does not sign out', $ca->req('GET', $base . '/my-bookings')['code'] === 200);
$stale = file_get_contents($ca->jar);
$ca->req('POST', $base . '/logout', ['_csrf_token' => $ca->token($base . '/my-bookings')]);
check('B19 POST /logout with the token signs out', $ca->req('GET', $base . '/my-bookings')['code'] === 302);
$replay = new Browser();
file_put_contents($replay->jar, $stale);
check('B20 the old session cookie no longer works after sign-out', $replay->req('GET', $base . '/my-bookings')['code'] === 302);

// open redirect after sign-in
foreach (['//evil.test/x', 'https://evil.test', '/\\evil.test', 'javascript:alert(1)', "/ok\r\nX-Injected: 1"] as $evil) {
    $b = new Browser();
    $b->req('GET', $base . '/login?redirect=' . rawurlencode($evil));
    $tok = $b->token($base . '/login?redirect=' . rawurlencode($evil));
    $r = $b->req('POST', $base . '/login?redirect=' . rawurlencode($evil), ['email' => $emailB, 'password' => $pwd, '_csrf_token' => $tok, 'redirect' => $evil]);
    $loc = $r['location'];
    // The app may turn a hostile value into a harmless path on this site; what must never happen is
    // leaving the site (another host) or injecting a header.
    $host = $loc === '' ? '' : (string) parse_url($loc, PHP_URL_HOST);
    $safe = ($host === '' || $host === 'localhost') && !str_contains($r['headers'], 'X-Injected') && !preg_match('#^\s*(javascript|data):#i', $loc);
    check('B21 sign-in does not redirect to ' . json_encode($evil), $safe, $loc);
}

// wrong password never reveals which part was wrong
$b = new Browser();
$tok = $b->token($base . '/login');
$wrongPass = $b->req('POST', $base . '/login', ['email' => $emailA, 'password' => 'Wrong!pass1', '_csrf_token' => $tok]);
$tok = $b->token($base . '/login');
$noUser = $b->req('POST', $base . '/login', ['email' => "nobody_{$tag}@sk-sports-lab.test", 'password' => 'Wrong!pass1', '_csrf_token' => $tok]);
$flash = fn (array $r) => trim(strip_tags(preg_replace('/<script.*?<\/script>/s', '', $b->req('GET', $GLOBALS['base'] . '/login')['body'])));
check('B22 wrong password and unknown e-mail give the same answer', $wrongPass['code'] === $noUser['code'] && $wrongPass['location'] === $noUser['location']);

// ---------------------------------------------------------------- C. admin
$admins = new AdminModel();
$adminEmail = "{$tag}_admin@sk-sports-lab.test";
$adminId = $admins->create('Access Admin', $adminEmail, 'active');
$ad = new Browser();
$tok = $ad->token($base . '/admin/login');
$r = $ad->req('POST', $base . '/admin/login', ['email' => $adminEmail, '_csrf_token' => $tok]);
check('C1 admin sign-in step 1 asks for the code', $r['code'] === 302 && str_contains($r['location'], 'verify-otp'), $r['location']);
$wrong = $ad->req('POST', $base . '/admin/verify-otp', ['otp' => '000000', '_csrf_token' => $ad->token($base . '/admin/verify-otp')]);
check('C2 a wrong code does not sign in', $ad->req('GET', $base . '/admin/dashboard')['code'] === 302);
$admins->createOtp($adminId, password_hash('246810', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));
$r = $ad->req('POST', $base . '/admin/verify-otp', ['otp' => '246810', '_csrf_token' => $ad->token($base . '/admin/verify-otp')]);
check('C3 the right code signs in', $r['code'] === 302 && str_contains($r['location'], 'dashboard'), $r['location']);
foreach ($routes as [$verb, $path, $url]) {
    if ($verb !== 'GET' || !$isAdmin($path) || in_array($path, $adminAuth, true)) { continue; }
    $x = $ad->req('GET', $base . $url);
    check("C4 admin can open $path", $x['code'] === 200 && !$leaks($x['body']), (string) $x['code']);
}
$closedBefore = (int) $db->query('SELECT COUNT(*) FROM closed_dates')->fetchColumn();
$r = $ad->req('POST', $base . '/admin/closed-dates', ['closed_date' => date('Y-m-d', strtotime('+200 days')), 'reason' => 'ac-test']);
check('C5 an admin POST without CSRF is refused', (int) $db->query('SELECT COUNT(*) FROM closed_dates')->fetchColumn() === $closedBefore);
$r = $ad->req('POST', $base . '/admin/closed-dates', ['closed_date' => date('Y-m-d', strtotime('+200 days')), 'reason' => 'ac-test', '_csrf_token' => $ad->token($base . '/admin/closed-dates')]);
check('C6 …and with the token it works', (int) $db->query('SELECT COUNT(*) FROM closed_dates')->fetchColumn() === $closedBefore + 1, $r['code'] . '');
$db->exec("DELETE FROM closed_dates WHERE reason = 'ac-test'");
$r = $ad->req('GET', $base . '/admin/logout');
check('C7 GET /admin/logout does not sign the admin out', $ad->req('GET', $base . '/admin/dashboard')['code'] === 200);
// the combo-package form posts list fields (service_id[], quantity[], duration_minutes[]); they must survive input cleaning
$pkgName = "AC Test Package $tag";
$r = $ad->req('POST', $base . '/admin/packages', http_build_query([
    'name' => $pkgName, 'slug' => 'ac-test-' . $tag, 'description' => 'x', 'price' => '499', 'gst_percent' => '18', 'validity_days' => '30',
    'service_id' => ['2', '4'], 'quantity' => ['1', '2'], 'duration_minutes' => ['10', ''], '_csrf_token' => $ad->token($base . '/admin/packages'),
]));
$pk = $db->prepare('SELECT id FROM packages WHERE name = ?');
$pk->execute([$pkgName]);
$pkgId = (int) $pk->fetchColumn();
check('C11 an admin can create a combo package through the form', $pkgId > 0, $r['code'] . ' ' . $r['location']);
if ($pkgId > 0) {
    $items = $db->query("SELECT service_id, quantity, duration_minutes FROM package_items WHERE package_id = $pkgId ORDER BY service_id")->fetchAll(PDO::FETCH_ASSOC);
    check('C12 its list rows (service, quantity, minutes) were all kept', count($items) === 2 && (int) $items[0]['service_id'] === 2 && (int) $items[0]['quantity'] === 1 && (int) $items[0]['duration_minutes'] === 10 && (int) $items[1]['service_id'] === 4 && (int) $items[1]['quantity'] === 2, json_encode($items));
    $db->exec("DELETE FROM package_items WHERE package_id = $pkgId");
    $db->exec("DELETE FROM packages WHERE id = $pkgId");
}
// an admin cookie is not a customer session
check('C8 an admin session cannot open customer pages', $ad->req('GET', $base . '/my-bookings')['code'] === 302);
// admin XSS-safe pages: a hostile customer name is escaped on the admin bookings list
$db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute(['<script>alert(1)</script>"\'', $uB]);
$r = $ad->req('GET', $base . '/admin/bookings?status=all&search=' . rawurlencode($refB));
check('C9 customer-supplied names are escaped in the admin list', !str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'), (string) $r['code']);
$ad->req('POST', $base . '/admin/logout', ['_csrf_token' => $ad->token($base . '/admin/dashboard')]);
check('C10 POST /admin/logout with the token signs out', $ad->req('GET', $base . '/admin/dashboard')['code'] === 302);

// ---------------------------------------------------------------- D. hostile requests
$odd = [
    'D1 very long URL' => ['GET', '/' . str_repeat('a', 9000)],
    'D2 null byte' => ['GET', '/services%00.php'],
    'D3 path traversal' => ['GET', '/..%2f..%2fbootstrap.php'],
    'D4 double encoded traversal' => ['GET', '/%252e%252e/%252e%252e/.env'],
    'D5 front controller in path' => ['GET', '/index.php/admin/dashboard'],
    'D6 unicode path' => ['GET', '/%E2%80%AEevil'],
    'D7 query array abuse' => ['GET', '/services?category[]=a&category[x][y]=z&page=-1'],
    'D8 availability junk' => ['GET', '/api/availability?service_id=abc&date=%27%20OR%201=1--&duration_option_id[]=1'],
    'D9 service id overflow' => ['GET', '/api/services/99999999999999999999'],
    'D10 booking confirmation junk ref' => ['GET', '/booking-confirmation?ref[]=x'],
];
foreach ($odd as $label => [$verb, $path]) {
    $r = $anon->req($verb, $base . $path);
    check("$label does not crash or leak", $r['code'] < 500 && !$leaks($r['body']), $r['code'] . '');
    check("$label never exposes secrets", !preg_match('/DB_PASS|RAZORPAY_KEY_SECRET|APP_SECRET|SMTP_PASS/', $r['body']));
}
// TRACE is answered by Apache before .htaccess is read; it is switched off with TraceEnable Off in the server config.
foreach (['OPTIONS', 'PUT', 'DELETE', 'PATCH', 'PROPFIND'] as $m) {
    $r = $anon->req($m, $base . '/services');
    check("D11 $m on a page is not served as content", ($r['code'] >= 300 && $r['code'] < 500) || ($r['code'] === 200 && $m === 'OPTIONS' && $r['body'] === ''), "$m " . $r['code']);
}
$r = $anon->req('POST', $base . '/login', ['email' => 'a@b.c', 'password' => str_repeat('x', 3 * 1024 * 1024)]);
check('D12 a 3 MB password field is refused cleanly', $r['code'] < 500, (string) $r['code']);
$r = $anon->req('POST', $base . '/register', ['name' => str_repeat('A', 100000), 'email' => str_repeat('a', 300) . '@x.com', 'mobile' => '1', 'password' => 'x', '_csrf_token' => $anon->token($base . '/register')]);
check('D13 absurdly long registration fields are refused cleanly', $r['code'] < 500 && !$leaks($r['body']), (string) $r['code']);
// Local development builds links from the Host header on purpose; production uses APP_URL only.
if ((string) config('app.env', 'production') === 'production') {
    $r = $anon->req('GET', $base . '/', null, ['Host: evil.test']);
    check('D14 a forged Host header is not echoed into links', !str_contains($r['body'], 'evil.test'), (string) $r['code']);
}
$r = $anon->req('GET', $base . '/', null, ['X-Forwarded-Host: evil.test', 'X-Forwarded-Proto: https']);
check('D15 forged X-Forwarded-* headers are not echoed into links', !str_contains($r['body'], 'evil.test'));
$r = $anon->req('GET', $base . '/services', null, ['X-HTTP-Method-Override: DELETE', 'X-Method-Override: DELETE']);
check('D16 method-override headers are ignored', $r['code'] === 200);
$r = $anon->req('POST', $base . '/forgot-password', ['email' => $emailA, '_csrf_token' => $anon->token($base . '/forgot-password')], ['Host: evil.test']);
check('D17 forgot-password with a forged Host does not crash', $r['code'] < 500);
$bad = $anon->req('GET', $base . '/does-not-exist-' . $tag);
check('D18 unknown pages give a branded 404, not a server page', $bad['code'] === 404 && !$leaks($bad['body']) && stripos($bad['body'], 'Apache') === false);
check('D19 the PHP version is not advertised (X-Powered-By)', !preg_match('/^X-Powered-By:/mi', $bad['headers']), $bad['headers']);
foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => '', 'Content-Security-Policy' => '', 'Referrer-Policy' => ''] as $hdr => $needle) {
    $r = $anon->req('GET', $base . '/');
    check("D20 $hdr header is sent", (bool) preg_match('/^' . $hdr . ':\s*' . preg_quote($needle, '/') . '/mi', $r['headers']));
}
$r = $ca->req('GET', $base . '/my-bookings');
$authed = $signIn($emailB);
$r = $authed[0]->req('GET', $base . '/my-bookings');
check('D21 signed-in pages are not cacheable by shared caches', (bool) preg_match('/^Cache-Control:[^\r\n]*(no-store|private|no-cache)/mi', $r['headers']), $r['headers']);

// ---------------------------------------------------------------- E. login throttling
$thr = new Browser();
$locked = false;
for ($i = 0; $i < 14; $i++) {
    $tok = $thr->token($base . '/login');
    $x = $thr->req('POST', $base . '/login', ['email' => $emailA, 'password' => 'Wrong!' . $i, '_csrf_token' => $tok]);
    if ($x['code'] === 429 || stripos($x['body'], 'too many') !== false) { $locked = true; break; }
    $flashPage = $thr->req('GET', $base . '/login');
    if (stripos($flashPage['body'], 'too many') !== false) { $locked = true; break; }
}
check('E1 repeated wrong passwords get throttled', $locked);
$tok = $thr->token($base . '/login');
$x = $thr->req('POST', $base . '/login', ['email' => $emailA, 'password' => $pwd, '_csrf_token' => $tok]);
check('E2 while throttled even the right password is not accepted', $thr->req('GET', $base . '/my-bookings')['code'] === 302);
RateLimit::clearScope('login');

// ---------------------------------------------------------------- cleanup
$db->exec("DELETE FROM bookings WHERE user_id IN ($uA, $uB)");
$db->exec("DELETE FROM payments WHERE booking_id NOT IN (SELECT id FROM bookings) AND booking_id IS NOT NULL");
$db->exec("DELETE FROM password_resets WHERE user_id IN ($uA, $uB)");
$db->exec("DELETE FROM users WHERE id IN ($uA, $uB)");
$db->exec("DELETE FROM admin_otps WHERE admin_id = $adminId");
$db->exec("DELETE FROM admins WHERE id = $adminId");
foreach (['login', 'login_ip', 'admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $scope) { RateLimit::clearScope($scope); }

echo "=== Access control: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
