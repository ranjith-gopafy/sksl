<?php
/**
 * Audit Medium (schema & money) — verification.
 *
 *   1. Admin search: LIKE wildcards in the search box are literal (%, _, \)
 *   2. payments.markPaid()/markFailed() never overwrite a paid row
 *   3. Invoice number is stored once per booking and reused (ledger) — schema check
 *   4. Service price/capacity validation over HTTP (price > 0, numeric, capacity 1..50)
 *
 * Run:  php tests/test_schema_money.php [base-url]
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\AdminModel;
use App\Models\BookingModel;
use App\Models\PaymentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Controllers\AdminServiceController;

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
function http(string $method, string $url, array $post = [], ?string $jar = null): array
{
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20];
    if ($jar !== null) {
        $opts[CURLOPT_COOKIEFILE] = $jar;
        $opts[CURLOPT_COOKIEJAR]  = $jar;
    }
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}
function csrfFrom(string $html): string
{
    return preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $m) ? $m[1] : '';
}

echo "=== Schema & money test ({$baseUrl}) ===\n";

$db           = getDb();
$userModel    = new UserModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();
$serviceModel = new ServiceModel();
$adminModel   = new AdminModel();
$runId        = substr(bin2hex(random_bytes(4)), 0, 8);

// ── 1. LIKE escaping ─────────────────────────────────────────────────────
echo "\n1. Admin search wildcards\n";
check(BookingModel::likeTerm('100%') === '%100\\%%', '1.1 % is escaped');
check(BookingModel::likeTerm('a_b') === '%a\\_b%', '1.2 _ is escaped');
check(BookingModel::likeTerm('c\\d') === '%c\\\\d%', '1.3 backslash is escaped');
check(strlen(BookingModel::likeTerm(str_repeat('x', 500))) === 102, '1.4 search term capped at 100 chars');

$spa = $serviceModel->findBySlug('spa');
$u1 = $userModel->create('Percent Tester', "pct_{$runId}@sk-sports-lab.test", '9811100001', password_hash('x-y-z-1234', PASSWORD_DEFAULT));
$u2 = $userModel->create('Plain Tester', "plain_{$runId}@sk-sports-lab.test", '9811100002', password_hash('x-y-z-1234', PASSWORD_DEFAULT));
$mk = static function (int $uid, string $ref) use ($bookingModel, $spa): int {
    return $bookingModel->create([
        'booking_reference' => $ref, 'user_id' => $uid, 'service_id' => (int) $spa['id'],
        'booking_date' => date('Y-m-d', strtotime('+10 days')), 'start_time' => '09:00:00', 'end_time' => '09:40:00',
        'service_duration_minutes' => 40, 'base_amount' => 100, 'gst_amount' => 18, 'total_amount' => 118,
        'booking_status' => 'confirmed', 'payment_status' => 'paid',
    ]);
};
$refA = 'SKSL-TST-' . strtoupper($runId) . 'A';
$refB = 'SKSL-TST-' . strtoupper($runId) . 'B';
$b1 = $mk($u1, $refA);
$b2 = $mk($u2, $refB);
$wild = $bookingModel->getAdminBookings(['status' => 'all', 'search' => '%' . strtoupper($runId) . '%']);
check(count($wild) === 0, '1.5 a literal % in the search does not act as a wildcard');
$under = $bookingModel->getAdminBookings(['status' => 'all', 'search' => 'pct_' . $runId]);
check(count($under) === 1 && (int) $under[0]['user_id'] === $u1, '1.6 _ is matched literally (only the pct_ user)');
$plain = $bookingModel->getAdminBookings(['status' => 'all', 'search' => strtoupper($runId)]);
check(count($plain) === 2, '1.7 normal substring search still finds both');
$counts = $bookingModel->getAdminStatusCounts(['search' => '%']);
check((int) ($counts['all'] ?? 0) === 0, '1.8 status counts respect the same escaping');

// ── 2. Payments guard ────────────────────────────────────────────────────
echo "\n2. Payment idempotency\n";
$payId = $paymentModel->create(['booking_id' => $b1, 'razorpay_order_id' => 'order_tst_' . $runId, 'amount' => 118.00, 'currency' => 'INR']);
check($payId > 0, '2.1 payment row created');
$paymentModel->markPaid($payId, 'pay_first_' . $runId, 'sig1');
$paymentModel->markPaid($payId, 'pay_second_' . $runId, 'sig2');
$p = $paymentModel->findByBookingId($b1);
check($p['status'] === 'paid' && $p['razorpay_payment_id'] === 'pay_first_' . $runId, '2.2 second markPaid does not overwrite the payment id');
$paymentModel->markFailed($payId, 'late failure');
$p = $paymentModel->findByBookingId($b1);
check($p['status'] === 'paid', '2.3 markFailed cannot downgrade a paid row');

// ── 3. Invoice ledger & reference width ──────────────────────────────────
echo "\n3. Schema\n";
$cols = [];
foreach ($db->query('SHOW COLUMNS FROM bookings') as $c) {
    $cols[$c['Field']] = $c;
}
check(isset($cols['invoice_number']) && stripos((string) $cols['invoice_number']['Type'], 'varchar') !== false, '3.1 bookings.invoice_number exists');
check(preg_match('/varchar\((\d+)\)/i', (string) $cols['booking_reference']['Type'], $m) && (int) $m[1] >= 32, '3.2 booking_reference is at least VARCHAR(32)');
$idx = $db->query("SHOW INDEX FROM bookings WHERE Column_name = 'invoice_number' AND Non_unique = 0")->fetchAll();
check(count($idx) >= 1, '3.3 invoice_number has a UNIQUE index (one ledger entry per number)');
$svcCols = [];
foreach ($db->query('SHOW COLUMNS FROM services') as $c) {
    $svcCols[$c['Field']] = $c;
}
check(stripos((string) $svcCols['price']['Type'], 'decimal') !== false, '3.4 services.price is DECIMAL (no float money)');

// ── 4. Price / capacity validation over HTTP ─────────────────────────────
echo "\n4. Service pricing guard rails\n";
check(AdminServiceController::MIN_PRICE >= 1.0, '4.1 minimum price is at least ₹1');
$probe = http('GET', $baseUrl . '/health');
if ($probe['code'] === 0) {
    echo "  [SKIP] server not reachable at {$baseUrl}\n";
} else {
    $walker = $serviceModel->findBySlug('walker');
    $adminEmail = "money_admin_{$runId}@sk-sports-lab.test";
    $adminId = $adminModel->create('Money Admin', $adminEmail, 'active');
    $jar = tempnam(sys_get_temp_dir(), 'sksl_money_');
    $login = http('GET', $baseUrl . '/admin/login', [], $jar);
    http('POST', $baseUrl . '/admin/login', ['_csrf_token' => csrfFrom($login['body']), 'email' => $adminEmail], $jar);
    $otp = '424242';
    $adminModel->createOtp($adminId, password_hash($otp, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));
    $verifyPage = http('GET', $baseUrl . '/admin/verify-otp?email=' . urlencode($adminEmail), [], $jar);
    http('POST', $baseUrl . '/admin/verify-otp', ['_csrf_token' => csrfFrom($verifyPage['body']), 'email' => $adminEmail, 'otp' => $otp], $jar);
    $page = http('GET', $baseUrl . '/admin/services', [], $jar);
    $csrf = csrfFrom($page['body']);
    check($page['code'] === 200 && $csrf !== '', '4.2 admin signed in');

    $post = static function (array $fields) use ($baseUrl, $walker, $csrf, $jar): string {
        http('POST', $baseUrl . '/admin/services/' . $walker['id'] . '/update', $fields + ['_csrf_token' => $csrf], $jar);
        $after = http('GET', $baseUrl . '/admin/services', [], $jar);
        // Flash messages are emitted as window.showToast("<json string>", 'error'|'success')
        if (preg_match('/window\.showToast\((".*?"),\s*\'(?:error|success|info)\'\)/s', $after['body'], $m)) {
            $decoded = json_decode($m[1], true);
            return is_string($decoded) ? $decoded : '';
        }
        return '';
    };
    $base = ['capacity' => $walker['capacity'], 'description' => $walker['description'], 'status' => 'active'];

    $msg = $post($base + ['price' => '0']);
    check(str_contains($msg, 'Price must be between'), '4.3 ₹0 price rejected — ' . $msg);
    $msg = $post($base + ['price' => 'abc']);
    check(str_contains($msg, 'Price must be a number'), '4.4 non-numeric price rejected');
    $msg = $post($base + ['price' => '1e3']);
    check(str_contains($msg, 'Price must be a number'), '4.5 scientific notation rejected');
    $msg = $post($base + ['price' => '149.999']);
    check(str_contains($msg, 'Price must be a number'), '4.6 more than two decimals rejected');
    $msg = $post($base + ['price' => '250000']);
    check(str_contains($msg, 'Price must be between'), '4.7 absurd price rejected');
    $msg = $post(['price' => '149.00', 'capacity' => '0', 'description' => $walker['description'], 'status' => 'active']);
    check(str_contains($msg, 'Capacity must be'), '4.8 capacity 0 rejected');
    $msg = $post(['price' => '149.00', 'capacity' => '999', 'description' => $walker['description'], 'status' => 'active']);
    check(str_contains($msg, 'Capacity must be'), '4.9 capacity 999 rejected');
    $unchanged = $serviceModel->findById((int) $walker['id']);
    check((float) $unchanged['price'] === (float) $walker['price'] && (int) $unchanged['capacity'] === (int) $walker['capacity'], '4.10 rejected updates left the service untouched');

    $msg = $post($base + ['price' => '199.50']);
    $changed = $serviceModel->findById((int) $walker['id']);
    check((float) $changed['price'] === 199.50 && ($changed['image'] ?? null) === ($walker['image'] ?? null), '4.11 valid price accepted, image preserved — ' . $msg);

    // restore
    $serviceModel->updateDetails((int) $walker['id'], [
        'price' => $walker['price'], 'capacity' => $walker['capacity'], 'description' => $walker['description'],
        'status' => $walker['status'], 'image' => $walker['image'] ?? null,
    ]);
    @unlink($jar);
    $db->prepare('DELETE FROM admin_otps WHERE admin_id = ?')->execute([$adminId]);
    $db->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
    $db->prepare('DELETE FROM email_logs WHERE recipient = ?')->execute([$adminEmail]);
}

// cleanup
$db->prepare('DELETE FROM payments WHERE booking_id IN (?, ?)')->execute([$b1, $b2]);
$db->prepare('DELETE FROM bookings WHERE id IN (?, ?)')->execute([$b1, $b2]);
$db->prepare('DELETE FROM users WHERE id IN (?, ?)')->execute([$u1, $u2]);

echo "\n=== Schema & money: {$pass} passed, {$fail} failed ===\n";
exit($fail === 0 ? 0 : 1);
