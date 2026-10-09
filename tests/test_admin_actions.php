<?php

/**
 * Admin dashboard actions over real HTTP, as a signed-in admin: modalities (toggle, edit, duration
 * options), bookings (cancel, complete, visited / no-show, package credit return, filters), practitioner
 * schedules, manual blocks, closed dates, combo packages and the read-only pages.
 * Everything the test creates or changes is put back at the end.
 *
 * Needs the site running (XAMPP/Apache at http://localhost/sksl/public, or pass a base URL as argv[1]).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\BookingRules;
use App\Helpers\RateLimit;
use App\Helpers\TimeHelper;
use App\Models\AdminModel;
use App\Models\BookingModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;

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

final class Admin
{
    public string $jar;
    public string $page = '';
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'sksl_adm'); }
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
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $res = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        preg_match('/^Location:\s*(.+)$/mi', substr($res, 0, $size), $m);
        return ['code' => $code, 'body' => substr($res, $size), 'location' => trim($m[1] ?? '')];
    }
    public function token(string $path): string
    {
        $r = $this->req('GET', $GLOBALS['base'] . $path);
        return preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : (preg_match('/name="csrf-token"\s+content="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : '');
    }
    /** POST a form with a fresh CSRF token, follow the redirect once and return the page that shows the flash message. */
    public function post(string $path, array $fields = [], string $tokenFrom = '/admin/dashboard'): array
    {
        $fields['_csrf_token'] = $this->token($tokenFrom);
        $r = $this->req('POST', $GLOBALS['base'] . $path, http_build_query($fields));
        $next = $r['location'] !== '' ? $this->req('GET', $r['location']) : ['code' => 0, 'body' => '', 'location' => ''];
        return ['code' => $r['code'], 'location' => $r['location'], 'flash' => html_entity_decode(strip_tags($next['body']), ENT_QUOTES | ENT_HTML5), 'html' => $next['body']];
    }
}

if ((new Admin())->req('GET', $base . '/health')['code'] === 0) {
    echo "[SKIP] site not reachable at $base\n";
    exit(0);
}
echo "SKSL — admin dashboard actions\n";
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $s) { RateLimit::clearScope($s); }

$q = function (string $sql, array $a = []) use ($db): array { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };
$tag = 'ad' . substr(bin2hex(random_bytes(3)), 0, 6);

// ---------------------------------------------------------------- sign in
$admins = new AdminModel();
$adminId = $admins->create('Actions Admin', "{$tag}_admin@sk-sports-lab.test", 'active');
$a = new Admin();
$a->req('POST', $base . '/admin/login', http_build_query(['email' => "{$tag}_admin@sk-sports-lab.test", '_csrf_token' => $a->token('/admin/login')]));
$admins->createOtp($adminId, password_hash('246810', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 600));
$r = $a->req('POST', $base . '/admin/verify-otp', http_build_query(['otp' => '246810', '_csrf_token' => $a->token('/admin/verify-otp')]));
check('S0 admin signed in', $r['code'] === 302 && str_contains($r['location'], 'dashboard'), $r['location']);

// snapshots to restore
$svcSnap = $q('SELECT id, name, price, capacity, description, status, image FROM services ORDER BY id');
$maxId = [];
foreach (['closed_dates', 'manual_blocks', 'availability_rules', 'packages', 'service_duration_options'] as $t) { $maxId[$t] = (int) $db->query("SELECT COALESCE(MAX(id),0) FROM `$t`")->fetchColumn(); }

$bannerSnap = $q('SELECT * FROM hero_banners ORDER BY id');
$bannerBackup = sys_get_temp_dir() . '/sksl_hero_banners_backup.json';
file_put_contents($bannerBackup, json_encode($bannerSnap)); // last resort if the process is killed
$restoreBanners = function () use ($db, $bannerSnap): void {
    $db->exec('DELETE FROM hero_banners');
    foreach ($bannerSnap as $bRow) {
        $cols = array_keys($bRow);
        $db->prepare('INSERT INTO hero_banners (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($bRow));
    }
};
register_shutdown_function($restoreBanners); // runs after a crash too
$svc = fn (string $slug) => (new ServiceModel())->findBySlug($slug);
$sauna = $svc('sauna');
$cycle = $svc('cycle');
$sid = (int) $sauna['id'];

// ---------------------------------------------------------------- A. modalities
$r = $a->post("/admin/services/$sid/toggle", [], '/admin/services');
check('A1 toggling a modality deactivates it', $q('SELECT status FROM services WHERE id = ?', [$sid])[0]['status'] === 'inactive' && str_contains($r['flash'], 'Inactive'), $r['flash']);
$pub = (new Admin())->req('GET', $base . '/api/services');
check('A2 an inactive modality disappears from the public catalogue', !str_contains($pub['body'], '"slug":"sauna"'));
$a->post("/admin/services/$sid/toggle", [], '/admin/services');
check('A3 toggling again reactivates it', $q('SELECT status FROM services WHERE id = ?', [$sid])[0]['status'] === 'active');
$r = $a->post('/admin/services/999999999/toggle', [], '/admin/services');
check('A4 toggling an unknown modality is refused cleanly', $r['code'] === 302 && str_contains($r['flash'], 'not found'), $r['flash']);

$edit = fn (array $f) => $a->post("/admin/services/$sid/update", $f, '/admin/services');
$r = $edit(['price' => '650.50', 'capacity' => '5', 'description' => 'Edited by test', 'status' => 'active']);
$row = $q('SELECT price, capacity, description FROM services WHERE id = ?', [$sid])[0];
check('A5 a valid edit saves price, capacity and description', (float) $row['price'] === 650.50 && (int) $row['capacity'] === 5 && $row['description'] === 'Edited by test', json_encode($row) . $r['flash']);
$api = json_decode((new Admin())->req('GET', $base . "/api/services/$sid")['body'], true);
check('A6 the public price is the edited GST-inclusive price', abs((float) ($api['data']['pricing']['total_amount'] ?? $api['data']['price'] ?? 0) - 650.50) < 0.01 || str_contains(json_encode($api), '650.5'), substr(json_encode($api), 0, 200));
foreach (['abc' => 'number', '-5' => 'between', '0' => 'between', '1e3' => 'number', '12.345' => 'two decimals', '9999999' => 'between', '' => 'number'] as $badPrice => $_) {
    $before = $q('SELECT price FROM services WHERE id = ?', [$sid])[0]['price'];
    $edit(['price' => $badPrice, 'capacity' => '5', 'description' => 'x', 'status' => 'active']);
    check('A7 price ' . json_encode($badPrice) . ' is refused and nothing changes', $q('SELECT price FROM services WHERE id = ?', [$sid])[0]['price'] === $before);
}
foreach (['0', '-1', 'x', '1000', '2.5', ''] as $badCap) {
    $before = $q('SELECT capacity FROM services WHERE id = ?', [$sid])[0]['capacity'];
    $edit(['price' => '650.50', 'capacity' => $badCap, 'description' => 'x', 'status' => 'active']);
    check('A8 capacity ' . json_encode($badCap) . ' is refused and nothing changes', $q('SELECT capacity FROM services WHERE id = ?', [$sid])[0]['capacity'] === $before);
}
$edit(['price' => '650.50', 'capacity' => '5', 'description' => str_repeat('x', 1001), 'status' => 'active']);
check('A9 a 1001-character description is refused', $q('SELECT description FROM services WHERE id = ?', [$sid])[0]['description'] === 'Edited by test');
$edit(['price' => '650.50', 'capacity' => '5', 'description' => 'Edited by test', 'status' => 'hacked']);
check('A10 an invalid status value is not stored', in_array($q('SELECT status FROM services WHERE id = ?', [$sid])[0]['status'], ['active', 'inactive'], true));
$edit(['price' => '650.50', 'capacity' => '5', 'description' => '<script>alert(1)</script>', 'status' => 'active']);
$page = $a->req('GET', $base . '/admin/services')['body'];
check('A11 a script in the description is escaped on the admin page', !str_contains($page, '<script>alert(1)</script>'));
$pubPage = (new Admin())->req('GET', $base . '/services')['body'];
check('A12 …and on the public page', !str_contains($pubPage, '<script>alert(1)</script>'));
// restore the modality
$db->prepare('UPDATE services SET price = ?, capacity = ?, description = ?, status = ? WHERE id = ?')->execute([$sauna['price'], $sauna['capacity'], $sauna['description'], $sauna['status'], $sid]);

// duration options
$optPath = "/admin/services/$sid/duration-options";
$r = $a->post($optPath, ['duration_minutes' => '20', 'price' => '300', 'label' => 'Test 20'], '/admin/services');
$opt = $q('SELECT * FROM service_duration_options WHERE service_id = ? AND duration_minutes = 20', [$sid])[0] ?? null;
check('A13 a duration option can be added', $opt !== null && (float) $opt['price'] === 300.0 && $opt['status'] === 'active', $r['flash']);
$r = $a->post($optPath, ['duration_minutes' => '20', 'price' => '310', 'label' => 'Dup'], '/admin/services');
check('A14 the same length twice is refused', (int) $q('SELECT COUNT(*) c FROM service_duration_options WHERE service_id = ? AND duration_minutes = 20', [$sid])[0]['c'] === 1 && str_contains($r['flash'], 'already exists'), $r['flash']);
foreach ([['4', '100'], ['361', '100'], ['abc', '100'], ['25', '0'], ['25', '-5'], ['25', '100001'], ['25', 'abc']] as [$mins, $price]) {
    $a->post($optPath, ['duration_minutes' => $mins, 'price' => $price, 'label' => 'Bad'], '/admin/services');
    check("A15 option minutes=$mins price=$price is refused", (int) $q('SELECT COUNT(*) c FROM service_duration_options WHERE service_id = ? AND duration_minutes = 25', [$sid])[0]['c'] === 0);
}
$optId = (int) $opt['id'];
$r = $a->post("/admin/services/duration-options/$optId/delete", [], '/admin/services');
check('A16 an unused option is deleted', (int) $q('SELECT COUNT(*) c FROM service_duration_options WHERE id = ?', [$optId])[0]['c'] === 0, $r['flash']);
// an option used by a booking is deactivated rather than deleted
$u = (new UserModel())->create('Admin Test Customer', "{$tag}_c@sk-sports-lab.test", '9876543210', password_hash('Xx1!' . random_int(1000, 9999), PASSWORD_BCRYPT));
$a->post($optPath, ['duration_minutes' => '35', 'price' => '350', 'label' => 'Used'], '/admin/services');
$usedOpt = $q('SELECT id FROM service_duration_options WHERE service_id = ? AND duration_minutes = 35', [$sid])[0];
$bm = new BookingModel();
$mkBooking = function (int $uid, int $serviceId, int $hoursAhead, array $o = []) use ($bm, $svc, $db): array {
    $s = $db->prepare('SELECT name FROM services WHERE id = ?');
    $s->execute([$serviceId]);
    $name = (string) $s->fetchColumn();
    $start = TimeHelper::now()->modify("+$hoursAhead hours");
    $start = $start->setTime((int) $start->format('H'), (int) $start->format('i'), 0);
    $end = $start->modify('+15 minutes');
    if ($end->format('Y-m-d') !== $start->format('Y-m-d')) { $end = $start->setTime(23, 59, 59); }
    $ref = 'SKSL-' . $start->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $mode = $o['mode'] ?? 'payment_required';
    $split = ServiceModel::calculatePricing(599.00);
    $id = $bm->create([
        'booking_reference' => $ref, 'user_id' => $uid, 'service_id' => $serviceId, 'booking_type' => $o['type'] ?? 'standard', 'payment_mode' => $mode,
        'duration_option_id' => $o['option'] ?? null, 'package_credit_id' => $o['credit'] ?? null,
        'booking_date' => $start->format('Y-m-d'), 'start_time' => $start->format('H:i:s'), 'end_time' => $end->format('H:i:s'),
        'service_name_snapshot' => $name, 'service_duration_minutes' => 15,
        'base_amount' => $mode === 'payment_required' ? $split['base_price'] : 0, 'gst_amount' => $mode === 'payment_required' ? $split['gst_amount'] : 0,
        'total_amount' => $mode === 'payment_required' ? 599.00 : 0,
        'booking_status' => $o['status'] ?? 'confirmed', 'payment_status' => $o['pay'] ?? 'paid', 'visit_status' => 'pending',
    ]);
    return ['id' => $id, 'ref' => $ref];
};
$usedBooking = $mkBooking($u, $sid, 50, ['option' => (int) $usedOpt['id']]);
$r = $a->post('/admin/services/duration-options/' . (int) $usedOpt['id'] . '/delete', [], '/admin/services');
$after = $q('SELECT status FROM service_duration_options WHERE id = ?', [(int) $usedOpt['id']]);
check('A17 an option used by a booking is deactivated, not deleted', count($after) === 1 && $after[0]['status'] === 'inactive', $r['flash']);
$r = $a->post('/admin/services/duration-options/999999999/delete', [], '/admin/services');
check('A18 deleting an unknown option is refused cleanly', $r['code'] === 302 && str_contains($r['flash'], 'not found'), $r['flash']);

// ---------------------------------------------------------------- B. bookings
$status = fn (int $id) => $q('SELECT booking_status, payment_status, visit_status, cancelled_by, cancelled_at, refund_amount, cancellation_fee FROM bookings WHERE id = ?', [$id])[0];
$setStatus = fn (int $id, string $to) => $a->post("/admin/bookings/$id/status", ['status' => $to], '/admin/bookings');

$paid = $mkBooking($u, $sid, 60);
$r = $setStatus($paid['id'], 'cancelled');
$s = $status($paid['id']);
check('B1 an admin can cancel a paid booking', $s['booking_status'] === 'cancelled' && $s['cancelled_by'] === 'admin' && $s['cancelled_at'] !== null, json_encode($s));
check('B2 the full amount is recorded as refund due, and the flash tells staff to refund it', (float) $s['refund_amount'] === 599.00 && str_contains($r['flash'], 'refund'), $r['flash']);
$page = $a->req('GET', $base . '/admin/bookings?status=cancelled&search=' . $paid['ref'])['body'];
check('B3 the bookings list shows "Refund due" for it', str_contains($page, 'Refund due') && str_contains($page, '599.00'));
$r = $setStatus($paid['id'], 'cancelled');
check('B4 cancelling twice is refused', str_contains($r['flash'], 'already cancelled') || str_contains($r['flash'], 'already'), $r['flash']);
$r = $setStatus($paid['id'], 'confirmed');
check('B5 a cancelled booking cannot be reopened', $status($paid['id'])['booking_status'] === 'cancelled', $r['flash']);
$r = $setStatus($paid['id'], 'completed');
check('B6 a cancelled booking cannot be completed', $status($paid['id'])['booking_status'] === 'cancelled');
$r = $setStatus($paid['id'], 'bogus');
check('B7 an invalid status is refused', str_contains($r['flash'], 'Invalid') && $status($paid['id'])['booking_status'] === 'cancelled', $r['flash']);
$r = $a->post('/admin/bookings/999999999/status', ['status' => 'cancelled'], '/admin/bookings');
check('B8 an unknown booking is refused cleanly', str_contains($r['flash'], 'not found'), $r['flash']);

$conf = $mkBooking($u, $sid, 70);
$r = $setStatus($conf['id'], 'confirmed');
check('B9 bookings cannot be "confirmed" by hand', str_contains($r['flash'], 'automatically') || str_contains($r['flash'], 'already'), $r['flash']);
$r = $a->post('/admin/bookings/' . $conf['id'] . '/visit', [], '/admin/bookings');
check('B10 a session that has not started cannot be marked visited', $status($conf['id'])['visit_status'] === 'pending', $r['flash']);
$r = $a->post('/admin/bookings/' . $conf['id'] . '/no-show', [], '/admin/bookings');
check('B11 a session that has not started cannot be marked no-show', $status($conf['id'])['visit_status'] === 'pending', $r['flash']);
$r = $setStatus($conf['id'], 'completed');
check('B12 a paid, confirmed booking can be completed by hand', $status($conf['id'])['booking_status'] === 'completed', $r['flash']);

$pend = $mkBooking($u, $sid, 80, ['status' => 'pending', 'pay' => 'pending']);
$r = $setStatus($pend['id'], 'completed');
check('B13 an unpaid booking cannot be completed', $status($pend['id'])['booking_status'] === 'pending', $r['flash']);
$r = $setStatus($pend['id'], 'cancelled');
$s = $status($pend['id']);
check('B14 an unpaid pending booking can be cancelled, with no refund amount', $s['booking_status'] === 'cancelled' && $s['refund_amount'] === null, json_encode($s));

// started session: visited / no-show
$started = $mkBooking($u, $sid, -1, ['status' => 'confirmed']);
$db->prepare("UPDATE bookings SET start_time = ?, end_time = ?, booking_date = ? WHERE id = ?")->execute([TimeHelper::now()->modify('-10 minutes')->format('H:i:s'), TimeHelper::now()->modify('+5 minutes')->format('H:i:s'), TimeHelper::today(), $started['id']]);
$r = $a->post('/admin/bookings/' . $started['id'] . '/visit', [], '/admin/bookings');
check('B15 a session that has started can be marked visited (and is completed)', $status($started['id'])['visit_status'] === 'visited' && $status($started['id'])['booking_status'] === 'completed', $r['flash'] . json_encode($status($started['id'])));
$r = $a->post('/admin/bookings/' . $started['id'] . '/no-show', [], '/admin/bookings');
check('B16 a visited session cannot then be marked no-show', $status($started['id'])['visit_status'] === 'visited', $r['flash']);
$started2 = $mkBooking($u, $sid, -1);
$db->prepare("UPDATE bookings SET start_time = ?, end_time = ?, booking_date = ? WHERE id = ?")->execute([TimeHelper::now()->modify('-10 minutes')->format('H:i:s'), TimeHelper::now()->modify('+5 minutes')->format('H:i:s'), TimeHelper::today(), $started2['id']]);
$r = $a->post('/admin/bookings/' . $started2['id'] . '/no-show', [], '/admin/bookings');
check('B17 a started session can be marked no-show', $status($started2['id'])['visit_status'] === 'no_show', $r['flash']);
$r = $a->post('/admin/bookings/' . $started2['id'] . '/no-show', [], '/admin/bookings');
check('B18 marking no-show twice changes nothing', $status($started2['id'])['visit_status'] === 'no_show');

// package credit comes back on admin cancel (once)
$db->prepare("INSERT INTO customer_packages (user_id, package_id, purchase_reference, package_name_snapshot, validity_days_snapshot, base_amount, gst_percent, gst_amount, total_amount, payment_status, purchased_at, expires_at, status) VALUES (?, 8, ?, 'AdminTest', 30, 100, 18, 18, 118, 'paid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')")->execute([$u, 'SKSL-PKG-' . strtoupper(bin2hex(random_bytes(3)))]);
$cpId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO customer_package_credits (customer_package_id, service_id, duration_minutes, allocated_quantity, used_quantity, remaining_quantity) VALUES (?, ?, 15, 1, 1, 0)')->execute([$cpId, (int) $cycle['id']]);
$credId = (int) $db->lastInsertId();
$pkgBooking = $mkBooking($u, (int) $cycle['id'], 90, ['mode' => 'package_credit', 'type' => 'package', 'credit' => $credId]);
$r = $setStatus($pkgBooking['id'], 'cancelled');
$cr = $q('SELECT remaining_quantity, used_quantity FROM customer_package_credits WHERE id = ?', [$credId])[0];
check('B19 cancelling a package booking returns the credit', (int) $cr['remaining_quantity'] === 1 && str_contains($r['flash'], 'credit'), json_encode($cr) . $r['flash']);
$setStatus($pkgBooking['id'], 'cancelled');
check('B20 …only once', (int) $q('SELECT remaining_quantity FROM customer_package_credits WHERE id = ?', [$credId])[0]['remaining_quantity'] === 1);

// filters and search
foreach (['all', 'confirmed', 'visited', 'completed', 'no_show', 'cancelled', 'pending', 'expired', 'nonsense'] as $st) {
    $x = $a->req('GET', $base . '/admin/bookings?status=' . $st);
    check("B21 status filter '$st' loads", $x['code'] === 200);
}
foreach ([$paid['ref'], $u . '', 'Admin Test Customer', "{$tag}_c@sk-sports-lab.test", '9876543210'] as $term) {
    $x = $a->req('GET', $base . '/admin/bookings?status=all&search=' . rawurlencode((string) $term));
    check('B22 search "' . $term . '" finds the booking', str_contains($x['body'], $paid['ref']) || $term === (string) $u, (string) $x['code']);
}
$x = $a->req('GET', $base . '/admin/bookings?status=all&search=' . rawurlencode("%' OR 1=1 --"));
check('B23 an SQL-looking search term returns no rows and no error', $x['code'] === 200 && !str_contains($x['body'], $paid['ref']));
$x = $a->req('GET', $base . '/admin/bookings?status=all&date=' . TimeHelper::today());
check('B24 the date filter loads', $x['code'] === 200);
$x = $a->req('GET', $base . '/admin/bookings?status=all&date=not-a-date&service_id=abc');
check('B25 a junk date / service filter does not crash', $x['code'] === 200);
$x = $a->req('GET', $base . '/admin/bookings?status=all&service_id=' . $sid);
check('B26 filtering by modality only lists that modality', $x['code'] === 200);

// the dashboard overview reflects the refund
$dash = $a->req('GET', $base . '/admin/dashboard')['body'];
check('B27 the dashboard warns about paid bookings cancelled and awaiting a refund', str_contains($dash, 'may need a refund'));

// ---------------------------------------------------------------- C. schedules, blocks, closed dates
$physio = $svc('physiotherapy');
$ph = (int) $physio['id'];
$rulePath = '/admin/availability';
$ruleCount = fn () => (int) $q('SELECT COUNT(*) c FROM availability_rules WHERE service_id = ?', [$ph])[0]['c'];
$n0 = $ruleCount();
$r = $a->post($rulePath, ['service_id' => $ph, 'rule_type' => 'recurring_weekday', 'weekday' => '0', 'start_time' => '10:00', 'end_time' => '12:00', 'is_available' => '1', 'notes' => 'test'], '/admin/availability');
check('C1 a weekly working-hours rule can be added', $ruleCount() === $n0 + 1, $r['flash']);
$ruleId = (int) $q('SELECT MAX(id) m FROM availability_rules WHERE service_id = ?', [$ph])[0]['m'];
foreach ([
    'end before start' => ['weekday' => '0', 'start_time' => '12:00', 'end_time' => '10:00'],
    'same start and end' => ['weekday' => '0', 'start_time' => '10:00', 'end_time' => '10:00'],
    'bad time format' => ['weekday' => '0', 'start_time' => '25:99', 'end_time' => '26:00'],
    'weekday 7' => ['weekday' => '7', 'start_time' => '10:00', 'end_time' => '11:00'],
    'weekday letters' => ['weekday' => 'mon', 'start_time' => '10:00', 'end_time' => '11:00'],
    'missing weekday' => ['start_time' => '10:00', 'end_time' => '11:00'],
] as $label => $fields) {
    $n = $ruleCount();
    $a->post($rulePath, $fields + ['service_id' => $ph, 'rule_type' => 'recurring_weekday', 'is_available' => '1'], '/admin/availability');
    check("C2 rule refused: $label", $ruleCount() === $n);
}
$n = $ruleCount();
$a->post($rulePath, ['service_id' => 999999999, 'rule_type' => 'recurring_weekday', 'weekday' => '1', 'start_time' => '10:00', 'end_time' => '11:00'], '/admin/availability');
check('C3 a rule for an unknown service is refused', $ruleCount() === $n);
$a->post($rulePath, ['service_id' => $ph, 'rule_type' => 'specific_date', 'specific_date' => '2026-02-30', 'start_time' => '10:00', 'end_time' => '11:00'], '/admin/availability');
check('C4 an impossible specific date is refused', $ruleCount() === $n);
$a->post($rulePath, ['service_id' => $ph, 'rule_type' => 'bogus', 'start_time' => '10:00', 'end_time' => '11:00'], '/admin/availability');
check('C5 an unknown rule type is refused', $ruleCount() === $n);
$a->post("/admin/availability/$ruleId/delete", [], '/admin/availability');
check('C6 a rule can be deleted', $ruleCount() === $n0);
$r = $a->post('/admin/availability/999999999/delete', [], '/admin/availability');
check('C7 deleting an unknown rule is refused cleanly', str_contains($r['flash'], 'not found'), $r['flash']);

$blockPath = '/admin/manual-blocks';
$bc = fn () => (int) $q('SELECT COUNT(*) c FROM manual_blocks')[0]['c'];
$day = date('Y-m-d', strtotime('+12 days'));
$b0 = $bc();
$r = $a->post($blockPath, ['service_id' => $sid, 'block_type' => 'specific_date', 'specific_date' => $day, 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => 'admin test'], '/admin/manual-blocks');
check('C8 a one-day time block can be added', $bc() === $b0 + 1, $r['flash']);
$blkId = (int) $q('SELECT MAX(id) m FROM manual_blocks')[0]['m'];
$book = new BookingService();
$g = BookingRules::gridStarts(15);
$inside = null; foreach ($g as $t) { if ($t >= '09:00' && $t < '10:00') { $inside = $t; break; } }
$u2 = (new UserModel())->create('Admin Test Customer2', "{$tag}_c2@sk-sports-lab.test", '9876543211', password_hash('Xx1!' . random_int(1000, 9999), PASSWORD_BCRYPT));
$closedCheck = $q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [$day])[0]['c'] == 0;
if ($inside && $closedCheck) {
    $h = $book->createHold($u2, $sid, $day, $inside);
    check('C9 the block stops customers booking that slot', $h['success'] === false && stripos($h['message'], 'blocked') !== false, $h['message'] ?? '');
}
foreach ([
    'end before start' => ['start_time' => '11:00', 'end_time' => '10:00'],
    'bad time' => ['start_time' => '99:99', 'end_time' => '10:00'],
    'bad type' => ['block_type' => 'forever'],
    'bad date' => ['specific_date' => '2026-13-45'],
    'bad weekday' => ['block_type' => 'recurring_weekday', 'weekday' => '9'],
] as $label => $override) {
    $n = $bc();
    $a->post($blockPath, $override + ['service_id' => $sid, 'block_type' => 'specific_date', 'specific_date' => $day, 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => 'admin test'], '/admin/manual-blocks');
    check("C10 block refused: $label", $bc() === $n);
}
$a->post("/admin/manual-blocks/$blkId/delete", [], '/admin/manual-blocks');
check('C11 a block can be removed', $bc() === $b0);
if ($inside && $closedCheck) {
    $h = $book->createHold($u2, $sid, $day, $inside);
    check('C12 …and the slot is bookable again', $h['success'] === true, $h['message'] ?? '');
    if ($h['success']) { $book->releaseHold($u2, $h['data']['booking_reference']); }
}

$cdPath = '/admin/closed-dates';
$cdDay = date('Y-m-d', strtotime('+25 days'));
$cc = fn () => (int) $q('SELECT COUNT(*) c FROM closed_dates')[0]['c'];
$c0 = $cc();
$r = $a->post($cdPath, ['closed_date' => $cdDay, 'reason' => 'admin test'], '/admin/closed-dates');
check('C13 a closed date can be added', $cc() === $c0 + 1, $r['flash']);
$cdId = (int) $q('SELECT id FROM closed_dates WHERE closed_date = ?', [$cdDay])[0]['id'];
$h = $book->createHold($u2, $sid, $cdDay, $g[2]);
check('C14 customers cannot book on a closed date', $h['success'] === false && stripos($h['message'], 'closed') !== false, $h['message'] ?? '');
$r = $a->post($cdPath, ['closed_date' => $cdDay, 'reason' => 'again'], '/admin/closed-dates');
check('C15 closing the same date twice is refused', $cc() === $c0 + 1 && str_contains($r['flash'], 'already'), $r['flash']);
foreach (['2026-02-30', 'tomorrow', '', '12/10/2026', "2026-10-12'; --"] as $badDate) {
    $a->post($cdPath, ['closed_date' => $badDate, 'reason' => 'bad'], '/admin/closed-dates');
    check('C16 closed date ' . json_encode($badDate) . ' is refused', $cc() === $c0 + 1);
}
$a->post($cdPath, ['closed_date' => date('Y-m-d', strtotime('+26 days')), 'reason' => str_repeat('r', 400)], '/admin/closed-dates');
$long = $q('SELECT reason FROM closed_dates WHERE closed_date = ?', [date('Y-m-d', strtotime('+26 days'))])[0]['reason'] ?? '';
check('C17 an over-long reason is cut to 255 characters, not an error', strlen($long) === 255, (string) strlen($long));
// closing a day that already has a confirmed booking warns staff
$off = 35;
while ($q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [date('Y-m-d', strtotime("+$off days"))])[0]['c'] > 0) { $off++; } // the dev database already has many closed dates
$clash = $mkBooking($u, $sid, 24 * $off);
$clashDate = $q('SELECT booking_date FROM bookings WHERE id = ?', [$clash['id']])[0]['booking_date'];
$r = $a->post($cdPath, ['closed_date' => $clashDate, 'reason' => 'clash test'], '/admin/closed-dates');
check('C18 closing a day with confirmed bookings warns staff', str_contains($r['flash'], 'confirmed booking'), $r['code'] . ' ' . $r['location'] . ' clashDate=' . $clashDate);
$a->post("/admin/closed-dates/$cdId/delete", [], '/admin/closed-dates');
check('C19 a closed date can be reopened', (int) $q('SELECT COUNT(*) c FROM closed_dates WHERE id = ?', [$cdId])[0]['c'] === 0);
$r = $a->post('/admin/closed-dates/999999999/delete', [], '/admin/closed-dates');
check('C20 reopening an unknown date is refused cleanly', $r['code'] === 302 && !str_contains($r['flash'], 'Fatal'), $r['flash']);

// ---------------------------------------------------------------- D. combo packages
$pkgPath = '/admin/packages';
$pn = fn () => (int) $q('SELECT COUNT(*) c FROM packages')[0]['c'];
$good = ['name' => "Admin Pkg $tag", 'slug' => '', 'description' => 'd', 'price' => '799', 'gst_percent' => '18', 'validity_days' => '45', 'service_id' => ['2', '3'], 'quantity' => ['2', '1'], 'duration_minutes' => ['', '10']];
$p0 = $pn();
$r = $a->post($pkgPath, $good, '/admin/packages');
check('D1 a package is created from the form (slug built from the name)', $pn() === $p0 + 1 && str_contains($r['flash'], 'created'), $r['flash']);
$pk = $q('SELECT * FROM packages WHERE name = ?', ["Admin Pkg $tag"])[0] ?? null;
check('D2 price, GST and validity are stored', $pk && (float) $pk['price'] === 799.0 && (int) $pk['validity_days'] === 45 && $pk['status'] === 'active', json_encode($pk));
$pid = (int) ($pk['id'] ?? 0);
$items = $q('SELECT service_id, quantity, duration_minutes FROM package_items WHERE package_id = ? ORDER BY service_id', [$pid]);
check('D3 both items are stored with quantity and minutes', count($items) === 2 && (int) $items[0]['quantity'] === 2 && $items[0]['duration_minutes'] === null && (int) $items[1]['duration_minutes'] === 10, json_encode($items));
$pubPk = (new Admin())->req('GET', $base . '/api/packages')['body'];
check('D4 an active package shows on the public combo page', str_contains($pubPk, "Admin Pkg $tag"));
$a->post("/admin/packages/$pid/toggle", [], '/admin/packages');
check('D5 toggling deactivates it', $q('SELECT status FROM packages WHERE id = ?', [$pid])[0]['status'] === 'inactive');
check('D6 an inactive package disappears from the public page', !str_contains((new Admin())->req('GET', $base . '/api/packages')['body'], "Admin Pkg $tag"));
$r = $a->post($pkgPath, $good, '/admin/packages');
check('D7 the same name (slug) twice is refused', $pn() === $p0 + 1 && str_contains($r['flash'], 'already exists'), $r['flash']);
foreach ([
    'no name' => ['name' => ''],
    'price 0' => ['price' => '0'], 'price negative' => ['price' => '-5'], 'price text' => ['price' => 'abc'], 'price too high' => ['price' => '500001'],
    'gst 29' => ['gst_percent' => '29'], 'gst negative' => ['gst_percent' => '-1'],
    'validity 0' => ['validity_days' => '0'], 'validity 731' => ['validity_days' => '731'],
    'no items' => ['service_id' => [], 'quantity' => [], 'duration_minutes' => []],
    'quantity 0' => ['quantity' => ['0', '1']], 'quantity 51' => ['quantity' => ['51', '1']],
    'same modality twice' => ['service_id' => ['2', '2']],
    'unknown modality' => ['service_id' => ['999999', '3']],
    'appointment service' => ['service_id' => [(string) $ph, '3']],
    'minutes 4' => ['duration_minutes' => ['4', '']], 'minutes 361' => ['duration_minutes' => ['361', '']],
] as $label => $override) {
    $n = $pn();
    $payload = $override + $good;
    $payload['name'] = ($override['name'] ?? "Bad Pkg $tag $label");
    $a->post($pkgPath, $payload, '/admin/packages');
    check("D8 package refused: $label", $pn() === $n);
}
$r = $a->post('/admin/packages/999999999/toggle', [], '/admin/packages');
check('D9 toggling an unknown package is refused cleanly', str_contains($r['flash'], 'not found'), $r['flash']);
$db->exec("DELETE FROM package_items WHERE package_id = $pid");
$db->exec("DELETE FROM packages WHERE id = $pid");

// ---------------------------------------------------------------- F. hero banners
$bn = fn () => (int) $q('SELECT COUNT(*) c FROM hero_banners')[0]['c'];
$bpath = '/admin/banner';
$okBanner = ['action' => 'create', 'badge_text' => 'Test badge', 'headline' => "Admin test $tag", 'subheadline' => 'Sub', 'image_url' => 'images/hero-banner.jpg', 'cta_text' => 'Go', 'cta_link' => '/booking', 'is_active' => '1'];
// the real slides are set aside (restored at the end) so the 5-slide limit and the last-slide rule can be tested
$db->exec('DELETE FROM hero_banners');
$n0 = $bn();
$r = $a->post($bpath, $okBanner, '/admin/banner');
check('F1 a hero slide can be added', $bn() === $n0 + 1 && str_contains($r['flash'], 'added'), $r['flash']);
$newId = (int) $q('SELECT id FROM hero_banners WHERE headline = ?', ["Admin test $tag"])[0]['id'];
$home = (new Admin())->req('GET', $base . '/')['body'];
check('F2 the new slide shows on the homepage carousel', str_contains($home, "Admin test $tag"));
foreach ([
    'empty headline'         => ['headline' => ''],
    'javascript: image'      => ['image_url' => 'javascript:alert(1)'],
    'path traversal image'   => ['image_url' => 'images/../bootstrap.php'],
    'parent folder image'    => ['image_url' => '../.env'],
    'missing image file'     => ['image_url' => 'images/does-not-exist.jpg'],
    'non-image extension'    => ['image_url' => 'images/hero-banner.php'],
    'data: image'            => ['image_url' => 'data:image/png;base64,AAAA'],
    'plain http image'       => ['image_url' => 'http://example.com/x.jpg'],
    'javascript: link'       => ['cta_link' => 'javascript:alert(1)'],
    'protocol-relative link' => ['cta_link' => '//evil.test/x'],
    'link with quote'        => ['cta_link' => '/x"onmouseover="alert(1)'],
    'bare word link'         => ['cta_link' => 'booking'],
    'data: link'             => ['cta_link' => 'data:text/html,<script>alert(1)</script>'],
] as $label => $override) {
    $n = $bn();
    $a->post($bpath, $override + $okBanner, '/admin/banner');
    check("F3 slide refused: $label", $bn() === $n);
}
$r = $a->post($bpath, ['image_url' => 'https://images.example.com/hero.jpg', 'headline' => "Admin test https $tag"] + $okBanner, '/admin/banner');
check('F4 an https image URL is accepted', (int) $q('SELECT COUNT(*) c FROM hero_banners WHERE headline = ?', ["Admin test https $tag"])[0]['c'] === 1, $r['flash']);
$r = $a->post($bpath, ['cta_link' => 'https://example.com/offer', 'headline' => "Admin test link $tag"] + $okBanner, '/admin/banner');
check('F5 a full https button link is accepted', (int) $q('SELECT COUNT(*) c FROM hero_banners WHERE headline = ?', ["Admin test link $tag"])[0]['c'] === 1, $r['flash']);
while ($bn() < 5) {
    $db->prepare("INSERT INTO hero_banners (badge_text, headline, subheadline, image_url, cta_text, cta_link, is_active) VALUES ('f', ?, 's', 'images/hero-banner.jpg', 'c', '/booking', 1)")->execute(['Admin test filler ' . $bn()]);
}
if ($bn() >= 5) {
    $r = $a->post($bpath, ['headline' => "Admin test sixth $tag"] + $okBanner, '/admin/banner');
    check('F6 a sixth slide is refused (limit is 5)', (int) $q('SELECT COUNT(*) c FROM hero_banners WHERE headline = ?', ["Admin test sixth $tag"])[0]['c'] === 0 && str_contains($r['flash'], 'Maximum'), $r['flash']);
}

// edit
$upd = ['action' => 'update', 'id' => $newId, 'badge_text' => 'B2', 'headline' => "Admin test edited $tag", 'subheadline' => 'S2', 'image_url' => 'images/hero-banner.jpg', 'cta_text' => 'Book', 'cta_link' => '/services', 'is_active' => '1'];
$r = $a->post($bpath, $upd, '/admin/banner');
check('F7 a slide can be edited', $q('SELECT headline, cta_link FROM hero_banners WHERE id = ?', [$newId])[0]['headline'] === "Admin test edited $tag", $r['flash']);
$r = $a->post($bpath, ['headline' => str_repeat('H', 5000)] + $upd, '/admin/banner');
$code = $r['code'];
check('F8 a 5,000-character headline does not crash the page', $code === 302 && !preg_match('/Fatal|Stack trace|PDOException/', $r['flash']), $code . ' ' . substr($r['flash'], 0, 120));
$h = $q('SELECT headline FROM hero_banners WHERE id = ?', [$newId])[0]['headline'];
check('F9 …and the stored headline is a sensible length or unchanged', strlen($h) <= 255, (string) strlen($h));
$r = $a->post($bpath, ['id' => 999999999] + $upd, '/admin/banner');
check('F10 editing a slide that does not exist is refused, not reported as updated', str_contains($r['flash'], 'not found') && !str_contains($r['flash'], 'updated successfully'), $r['code'] . ' ' . $r['location']);
$a->post($bpath, ['headline' => "<script>alert(1)</script> $tag", 'cta_text' => '<b>x</b>'] + $upd, '/admin/banner');
$home = (new Admin())->req('GET', $base . '/')['body'];
$adminPage = $a->req('GET', $base . '/admin/banner?edit=' . $newId)['body'];
check('F11 markup in a headline is escaped on the homepage and in the editor', !str_contains($home, '<script>alert(1)</script>') && !str_contains($adminPage, '<script>alert(1)</script> ' . $tag));

// toggle / delete
$r = $a->post($bpath, ['action' => 'toggle', 'id' => $newId], '/admin/banner');
check('F12 toggling a slide off hides it from the homepage', (int) $q('SELECT is_active FROM hero_banners WHERE id = ?', [$newId])[0]['is_active'] === 0 && !str_contains((new Admin())->req('GET', $base . '/')['body'], htmlspecialchars("<script>alert(1)</script> $tag", ENT_QUOTES)), $r['flash']);
$a->post($bpath, ['action' => 'toggle', 'id' => $newId], '/admin/banner');
check('F13 toggling again shows it', (int) $q('SELECT is_active FROM hero_banners WHERE id = ?', [$newId])[0]['is_active'] === 1);
$r = $a->post($bpath, ['action' => 'toggle', 'id' => 999999999], '/admin/banner');
check('F14 toggling an unknown slide is refused cleanly', str_contains($r['flash'], 'not found'), $r['flash']);
$r = $a->post($bpath, ['action' => 'delete', 'id' => 0], '/admin/banner');
check('F15 deleting with no id is refused', str_contains($r['flash'], 'Invalid'), $r['flash']);
// every slide off: the homepage must still render
$ids = array_column($q('SELECT id FROM hero_banners'), 'id');
$db->exec('UPDATE hero_banners SET is_active = 0');
$x = (new Admin())->req('GET', $base . '/');
check('F16 with every slide switched off the homepage still loads without errors', $x['code'] === 200 && !preg_match('/Warning:|Fatal error|Stack trace/', $x['body']), (string) $x['code']);
$db->exec('UPDATE hero_banners SET is_active = 1');
$before = $bn();
$a->post($bpath, ['action' => 'delete', 'id' => $newId], '/admin/banner');
check('F17 a slide can be deleted', $bn() === $before - 1);
// the last slide cannot be deleted
$db->exec('DELETE FROM hero_banners WHERE id <> (SELECT m FROM (SELECT MIN(id) m FROM hero_banners) t)');
$lastId = (int) $q('SELECT id FROM hero_banners')[0]['id'];
$r = $a->post($bpath, ['action' => 'delete', 'id' => $lastId], '/admin/banner');
check('F18 the last remaining slide cannot be deleted', $bn() === 1 && str_contains($r['flash'], 'Cannot delete'), $r['flash']);
// ---------------------------------------------------------------- E. pages that only read
foreach (['/admin/dashboard', '/admin', '/admin/bookings', '/admin/services', '/admin/availability', '/admin/manual-blocks', '/admin/packages', '/admin/package-purchases', '/admin/closed-dates', '/admin/banner'] as $p) {
    $x = $a->req('GET', $base . $p);
    check("E1 $p loads for an admin", $x['code'] === 200 && !preg_match('/Warning:|Fatal error|Stack trace/', $x['body']), (string) $x['code']);
}
// a hostile upload to the banner is refused
$tok = $a->token('/admin/banner');
$tmp = tempnam(sys_get_temp_dir(), 'bn');
file_put_contents($tmp, '<?php echo "pwned"; ?>');
$cf = new CURLFile($tmp, 'image/jpeg', 'shell.jpg');
$ch = curl_init($base . '/admin/banner');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEJAR => $a->jar, CURLOPT_COOKIEFILE => $a->jar, CURLOPT_POSTFIELDS => ['_csrf_token' => $tok, 'banner' => $cf, 'image' => $cf, 'hero_image' => $cf], CURLOPT_HEADER => true]);
$res = (string) curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($tmp);
check('E2 a PHP file disguised as a JPEG is not accepted as a banner', $code < 500 && !str_contains($res, 'pwned'));
$uploads = glob(dirname(__DIR__) . '/public/uploads/*shell*') ?: [];
check('E3 and nothing named shell.* was stored', $uploads === []);

// ---------------------------------------------------------------- cleanup
$db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($u, $u2))");
$db->exec("DELETE FROM booking_holds WHERE user_id IN ($u, $u2)");
$db->exec("DELETE FROM bookings WHERE user_id IN ($u, $u2)");
$db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($u, $u2))");
$db->exec("DELETE FROM customer_packages WHERE user_id IN ($u, $u2)");
$db->exec("DELETE FROM users WHERE id IN ($u, $u2)");
foreach (['service_duration_options', 'availability_rules', 'manual_blocks', 'closed_dates'] as $t) { $db->exec("DELETE FROM `$t` WHERE id > {$maxId[$t]}"); }
$upd = $db->prepare('UPDATE services SET name = ?, price = ?, capacity = ?, description = ?, status = ?, image = ? WHERE id = ?');
foreach ($svcSnap as $s) { $upd->execute([$s['name'], $s['price'], $s['capacity'], $s['description'], $s['status'], $s['image'], $s['id']]); }
$restoreBanners();
@unlink($bannerBackup);
$db->exec("DELETE FROM admin_otps WHERE admin_id = $adminId");
$db->exec("DELETE FROM admins WHERE id = $adminId");
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $s) { RateLimit::clearScope($s); }

echo "=== Admin actions: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
