<?php

/**
 * Booking-engine edge cases: dates, times, facility hours, closures and blocks, capacity,
 * per-athlete limits, duration options, hold expiry and combo-package credit bookings.
 * Every check goes through BookingService, the same code the website and the API use.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;
use App\Models\BookingHoldModel;
use App\Models\BookingModel;
use App\Models\CustomerPackageModel;
use App\Models\DurationOptionModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;

$db = getDb();
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++;
    echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$svcModel = new ServiceModel();
$holds    = new BookingHoldModel();
$bookings = new BookingModel();
$cpModel  = new CustomerPackageModel();
$optModel = new DurationOptionModel();
$book     = new BookingService();

$tag = 'be' . substr(bin2hex(random_bytes(3)), 0, 6);
$userIds = [];
$mkUser = function (string $label) use ($tag, &$userIds): int {
    $id = (new UserModel())->create("Edge $label", "{$tag}_{$label}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash('Pass1234!', PASSWORD_BCRYPT));
    $userIds[] = $id;
    return $id;
};
$q = function (string $sql, array $args = []) use ($db): array { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); };
$refused = fn (array $r, string $needle = '') => $r['success'] === false && ($needle === '' || stripos($r['message'], $needle) !== false);

// Pick open days (not closed, no existing blocks) so the dev data cannot disturb the scenarios.
$days = [];
for ($n = 2; $n <= 28 && count($days) < 8; $n++) {
    $d = date('Y-m-d', strtotime("+$n days"));
    $closed = $q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [$d])[0]['c'];
    $blocks = $q("SELECT COUNT(*) c FROM manual_blocks WHERE status = 'active' AND (specific_date = ? OR (block_type = 'recurring_weekday' AND weekday = ?))", [$d, (int) date('w', strtotime($d))])[0]['c'];
    if ((int) $closed === 0 && (int) $blocks === 0) { $days[] = $d; }
}
check('setup: found open days', count($days) >= 6);
[$D1, $D2, $D3, $D4, $D5, $D6] = $days + array_fill(0, 6, date('Y-m-d', strtotime('+2 days')));

$massage = $svcModel->findBySlug('massages');   // cap 4, 30 min + options
$sauna   = $svcModel->findBySlug('sauna');      // cap 4, 15 min
$cycle   = $svcModel->findBySlug('cycle');      // cap 1, 15 min
$tread   = $svcModel->findBySlug('treadmill');  // cap 1
$lap     = $svcModel->findBySlug('lap-pool');   // cap 1, 45 min
$physio  = $svcModel->findBySlug('physiotherapy');
$g15 = BookingRules::gridStarts(15);
$g30 = BookingRules::gridStarts(30);
$g45 = BookingRules::gridStarts(45);

echo "SKSL — booking edge cases\n";

// ---------------------------------------------------------------- A. grid and facility hours
check('A1 grid starts exactly at opening time', $g15[0] === '06:00' && $g30[0] === '06:00' && $g45[0] === '06:00', $g15[0]);
check('A2 no slot starts after 20:00', max($g15) <= '20:00' && max($g30) <= '20:00' && max($g45) <= '20:00');
foreach ([['15', $g15, 15], ['30', $g30, 30], ['45', $g45, 45]] as [$lbl, $g, $dur]) {
    $lastEnd = (int) substr(end($g), 0, 2) * 60 + (int) substr(end($g), 3, 2) + $dur;
    check("A3 last $lbl-minute slot ends by 21:00", $lastEnd <= 21 * 60, (string) $lastEnd);
    $dupe = count($g) === count(array_unique($g));
    check("A4 $lbl-minute grid has no duplicate or overlapping starts", $dupe);
    $step = BookingRules::slotStep($dur);
    $ok = true;
    for ($i = 1; $i < count($g); $i++) {
        $a = (int) substr($g[$i - 1], 0, 2) * 60 + (int) substr($g[$i - 1], 3, 2);
        $b = (int) substr($g[$i], 0, 2) * 60 + (int) substr($g[$i], 3, 2);
        if ($b - $a !== $step || $b < $a + $dur) { $ok = false; }
    }
    check("A5 $lbl-minute slots are evenly spaced and never overlap", $ok);
}

$u1 = $mkUser('u1');
$r = $book->createHold($u1, (int) $sauna['id'], $D1, '05:45');
check('A6 start before opening refused', $refused($r, 'outside facility hours'), $r['message']);
$r = $book->createHold($u1, (int) $sauna['id'], $D1, '20:15');
check('A7 start after the last slot refused', $r['success'] === false, $r['message']);
$r = $book->createHold($u1, (int) $sauna['id'], $D1, '21:00');
check('A8 start at closing time refused', $r['success'] === false);
$r = $book->createHold($u1, (int) $sauna['id'], $D1, '06:07');
check('A9 off-grid start refused', $refused($r, 'not an available slot'), $r['message']);
foreach (['6:00', '0600', '24:00', '09:60', 'ab:cd', '', '06:00:00', "06:00'; DROP TABLE x;--"] as $bad) {
    $r = $book->createHold($u1, (int) $sauna['id'], $D1, $bad);
    check('A10 malformed time refused: ' . json_encode($bad), $r['success'] === false);
}
$r = $book->createHold($u1, (int) $sauna['id'], $D1, $g15[0]);
check('A11 first slot of the day is bookable', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
$r = $book->createHold($u1, (int) $sauna['id'], $D1, end($g15));
check('A12 last slot of the day is bookable', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
// a 45-minute lap-pool slot must not run past closing
$lastLap = end($g45);
$r = $book->createHold($u1, (int) $lap['id'], $D1, $lastLap);
check('A13 last 45-minute slot is bookable and ends by 21:00', $r['success'] === true && $r['data']['end_time'] <= '21:00', json_encode($r));
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }

// ---------------------------------------------------------------- B. dates
$bad = ['2020-01-01', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+' . (BookingRules::advanceDays() + 1) . ' days')), '2026-02-30', '2026-13-01', 'tomorrow', '', '2026/10/12', '12-10-2026', '2026-1-5', "2026-10-12' OR 1=1"];
foreach ($bad as $d) {
    $r = $book->createHold($u1, (int) $sauna['id'], $d, $g15[1]);
    check('B1 bad date refused: ' . json_encode($d), $r['success'] === false);
}
$lastOk = BookingRules::maxDate();
$lastOkClosed = (int) $q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [$lastOk])[0]['c'];
if ($lastOkClosed === 0) {
    $r = $book->createHold($u1, (int) $sauna['id'], $lastOk, $g15[1]);
    check('B2 the last day inside the booking window is accepted', $r['success'] === true, $r['message']);
    if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
}

// closed date
$db->prepare('INSERT IGNORE INTO closed_dates (closed_date, reason) VALUES (?, ?)')->execute([$D2, 'edge test']);
$r = $book->createHold($u1, (int) $sauna['id'], $D2, $g15[1]);
check('B3 a closed date cannot be booked', $refused($r, 'closed'), $r['message']);
$db->prepare("DELETE FROM closed_dates WHERE closed_date = ? AND reason = 'edge test'")->execute([$D2]);
$r = $book->createHold($u1, (int) $sauna['id'], $D2, $g15[1]);
check('B4 reopening the date makes it bookable again', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }

// today: past times and the same-day cutoff
$nowMin = (int) TimeHelper::now()->format('H') * 60 + (int) TimeHelper::now()->format('i');
$today = TimeHelper::today();
$pastSlot = null;
foreach ($g15 as $s) { if ((int) substr($s, 0, 2) * 60 + (int) substr($s, 3, 2) < $nowMin - 1) { $pastSlot = $s; } }
if ($pastSlot !== null) {
    $r = $book->createHold($u1, (int) $sauna['id'], $today, $pastSlot);
    check('B5 a slot that already started today is refused', $r['success'] === false, $r['message']);
}
$futureSlot = null;
foreach ($g15 as $s) { if ((int) substr($s, 0, 2) * 60 + (int) substr($s, 3, 2) > $nowMin + BookingRules::sameDayCutoffMinutes() + 2) { $futureSlot = $s; break; } }
if ($futureSlot !== null) {
    $r = $book->createHold($u1, (int) $sauna['id'], $today, $futureSlot);
    check('B6 a later slot today is bookable', $r['success'] === true, $r['message']);
    if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
}

// ---------------------------------------------------------------- C. manual blocks
$db->prepare("INSERT INTO manual_blocks (service_id, block_type, specific_date, start_time, end_time, reason, status) VALUES (?, 'specific_date', ?, '09:00:00', '10:00:00', 'edge test', 'active')")->execute([(int) $sauna['id'], $D3]);
$blockId = (int) $db->lastInsertId();
$mins = fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
$inBlock = null; $beforeBlock = null;
foreach ($g15 as $s) {
    if ($mins($s) >= 9 * 60 && $mins($s) < 10 * 60 && $inBlock === null) { $inBlock = $s; }
    if ($mins($s) + 15 <= 9 * 60) { $beforeBlock = $s; }
}
$inBlockMassage = null;
foreach ($g30 as $s) { if ($mins($s) >= 9 * 60 && $mins($s) < 10 * 60) { $inBlockMassage = $s; break; } }
$r = $book->createHold($u1, (int) $sauna['id'], $D3, $inBlock);
check('C1 a slot inside a block is refused', $refused($r, 'blocked'), $r['message']);
$r = $book->createHold($u1, (int) $sauna['id'], $D3, $beforeBlock);
check('C2 a slot ending before the block starts is allowed', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
$r = $book->createHold($u1, (int) $massage['id'], $D3, $inBlockMassage);
check('C3 a block on one modality does not affect another', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
$db->prepare("UPDATE manual_blocks SET status = 'inactive' WHERE id = ?")->execute([$blockId]);
$r = $book->createHold($u1, (int) $sauna['id'], $D3, $inBlock);
check('C4 an inactive block no longer stops bookings', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($u1, $r['data']['booking_reference']); }
$db->prepare('DELETE FROM manual_blocks WHERE id = ?')->execute([$blockId]);

// ---------------------------------------------------------------- D. capacity and per-athlete rules
$slotM = $g30[3];
$cap = (int) $massage['capacity'];
$many = [];
for ($i = 0; $i < $cap + 1; $i++) { $many[] = $mkUser("cap$i"); }
$okCount = 0; $lastRes = null;
foreach ($many as $uid) {
    $r = $book->createHold($uid, (int) $massage['id'], $D4, $slotM);
    if ($r['success']) { $okCount++; } else { $lastRes = $r; }
}
check("D1 exactly $cap athletes can hold a capacity-$cap slot", $okCount === $cap && $lastRes !== null, "got $okCount");
check('D2 the extra athlete is told it is fully booked', $lastRes !== null && stripos($lastRes['message'], 'fully booked') !== false, $lastRes['message'] ?? '');
// partially overlapping start on a different grid point does not hide the full slot
$r = $book->createHold($many[$cap], (int) $massage['id'], $D4, $g30[4]);
check('D3 the next slot is still free for the same athlete', $r['success'] === true, $r['message']);
if ($r['success']) { $book->releaseHold($many[$cap], $r['data']['booking_reference']); }
// releasing a hold frees the place immediately
$first = $q('SELECT booking_reference FROM booking_holds WHERE user_id = ? AND status = \'active\'', [$many[0]])[0]['booking_reference'] ?? null;
check('D4 release of an own hold works', $first !== null && $book->releaseHold($many[0], $first) === true);
check("D5 another athlete cannot release someone else's hold", (function () use ($book, $many, $q) {
    $ref = $q("SELECT booking_reference FROM booking_holds WHERE user_id = ? AND status = 'active'", [$many[1]])[0]['booking_reference'] ?? '';
    return $ref !== '' && $book->releaseHold($many[2], $ref) === false;
})());
$r = $book->createHold($many[$cap], (int) $massage['id'], $D4, $slotM);
check('D6 a released place can be taken straight away', $r['success'] === true, $r['message']);

// per-athlete: hold cap, overlap across modalities, adjacency
$p = $mkUser('limits');
$maxHolds = BookingRules::maxActiveHoldsPerUser();
$made = [];
for ($i = 0; $i < $maxHolds + 1; $i++) {
    $r = $book->createHold($p, (int) $sauna['id'], $D5, $g15[$i * 2]);
    if ($r['success']) { $made[] = $r['data']['booking_reference']; } else { $capMsg = $r['message']; }
}
check("D7 an athlete may hold at most $maxHolds slots", count($made) === $maxHolds && isset($capMsg) && stripos($capMsg, 'awaiting payment') !== false, (string) ($capMsg ?? ''));
foreach ($made as $ref) { $book->releaseHold($p, $ref); }
$r1 = $book->createHold($p, (int) $sauna['id'], $D5, $g15[0]);
$r2 = $book->createHold($p, (int) $massage['id'], $D5, '06:00');
check('D8 the same athlete cannot hold two modalities at the same time', $r1['success'] === true && $refused($r2), $r2['message']);
$r3 = $book->createHold($p, (int) $massage['id'], $D5, $g30[2]);
check('D9 a later, non-overlapping session is fine', $r3['success'] === true, $r3['message']);
foreach ([$r1, $r3] as $r) { if ($r['success']) { $book->releaseHold($p, $r['data']['booking_reference']); } }

// ---------------------------------------------------------------- E. services and duration options
$r = $book->createHold($p, 999999, $D5, $g15[0]);
check('E1 unknown service refused', $r['success'] === false);
$r = $book->createHold($p, (int) $physio['id'], $D5, '09:00');
check('E2 an appointment service cannot be held for online payment', $refused($r, 'no online payment'), $r['message']);
$svcModel->updateStatus((int) $sauna['id'], 'inactive');
$r = $book->createHold($p, (int) $sauna['id'], $D5, $g15[0]);
$svcModel->updateStatus((int) $sauna['id'], 'active');
check('E3 an inactive service cannot be held', $refused($r, 'inactive'), $r['message']);

$opts = $optModel->getByServiceId((int) $massage['id']);
check('E4 massage has duration options', count($opts) >= 2);
foreach ($opts as $opt) {
    $u = $mkUser('opt' . $opt['duration_minutes']);
    $grid = BookingRules::gridStarts((int) $opt['duration_minutes']);
    $r = $book->createHold($u, (int) $massage['id'], $D6, $grid[1], true, (int) $opt['id']);
    check("E5 {$opt['duration_minutes']}-minute option holds the right length", $r['success'] === true && $r['data']['duration_minutes'] === (int) $opt['duration_minutes'], $r['message'] ?? '');
    if ($r['success']) {
        $exp = ServiceModel::calculatePricing((float) $opt['price']);
        check("E6 {$opt['duration_minutes']}-minute hold is priced from the option", abs($r['data']['pricing']['total_amount'] - $exp['total_amount']) < 0.001, json_encode($r['data']['pricing']));
        $row = $q('SELECT end_time, health_declared_at FROM booking_holds WHERE booking_reference = ?', [$r['data']['booking_reference']])[0];
        $endMin = (int) substr($grid[1], 0, 2) * 60 + (int) substr($grid[1], 3, 2) + (int) $opt['duration_minutes'];
        check("E7 stored end time matches ({$opt['duration_minutes']} min)", $row['end_time'] === sprintf('%02d:%02d:00', intdiv($endMin, 60), $endMin % 60), $row['end_time']);
        check('E8 health declaration is stored when ticked', $row['health_declared_at'] !== null);
        $book->releaseHold($u, $r['data']['booking_reference']);
    }
}
$saunaOpt = $optModel->getByServiceId((int) $sauna['id']);
$other = $opts[0];
$r = $book->createHold($p, (int) $sauna['id'], $D6, $g15[0], false, (int) $other['id']);
check("E9 another modality's duration option is refused", $refused($r, 'Invalid duration'), $r['message']);
$r = $book->createHold($p, (int) $massage['id'], $D6, $g30[0], false, 99999999);
check('E10 unknown duration option refused', $refused($r, 'Invalid duration'), $r['message']);
$db->prepare("UPDATE service_duration_options SET status = 'inactive' WHERE id = ?")->execute([(int) $other['id']]);
$r = $book->createHold($p, (int) $massage['id'], $D6, $g30[0], false, (int) $other['id']);
$db->prepare("UPDATE service_duration_options SET status = 'active' WHERE id = ?")->execute([(int) $other['id']]);
check('E11 inactive duration option refused', $refused($r, 'Invalid duration'), $r['message']);
$r = $book->createHold($p, (int) $massage['id'], $D6, '06:00', false, (int) end($opts)['id']);
if ($r['success']) { $book->releaseHold($p, $r['data']['booking_reference']); }
// a 90-minute massage must not start so late it runs past closing
$longOpt = null;
foreach ($opts as $o) { if ((int) $o['duration_minutes'] >= 90) { $longOpt = $o; } }
if ($longOpt) {
    $r = $book->createHold($p, (int) $massage['id'], $D6, '20:00', false, (int) $longOpt['id']);
    check('E12 a long session that would run past closing is refused', $r['success'] === false, $r['message'] ?? '');
}

// ---------------------------------------------------------------- F. hold expiry
$e1 = $mkUser('exp1');
$e2 = $mkUser('exp2');
$slotC = $g15[5];
$h = $book->createHold($e1, (int) $cycle['id'], $D6, $slotC);
check('F1 capacity-1 slot held', $h['success'] === true, $h['message'] ?? '');
$r = $book->createHold($e2, (int) $cycle['id'], $D6, $slotC);
check('F2 a second athlete is blocked while the hold is alive', $refused($r, 'fully booked'), $r['message']);
$db->prepare('UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE booking_reference = ?')->execute([$h['data']['booking_reference']]);
check('F3 an expired hold is no longer returned as active', $book->getActiveHold($h['data']['booking_reference']) === null);
$r = $book->createHold($e2, (int) $cycle['id'], $D6, $slotC);
check('F4 an expired hold frees the slot for others without waiting for the cron', $r['success'] === true, $r['message']);
$stale = $holds->expireStale();
check('F5 housekeeping marks the expired hold', $stale >= 1);
$st = $q('SELECT status FROM booking_holds WHERE booking_reference = ?', [$h['data']['booking_reference']])[0]['status'] ?? '';
check('F6 the stale hold row is now "expired"', $st === 'expired', $st);
$r2 = $book->createHold($e1, (int) $cycle['id'], $D6, $g15[7]);
check('F7 the athlete whose hold expired can book again', $r2['success'] === true, $r2['message']);

// ---------------------------------------------------------------- G. combo-package credit bookings
$mkPaidPackage = function (int $uid, int $packageId, ?string $expires = null, string $pay = 'paid') use ($db, $q): int {
    $pk = $q('SELECT * FROM packages WHERE id = ?', [$packageId])[0];
    $split = ServiceModel::calculatePricing((float) $pk['price']);
    $expires ??= date('Y-m-d 23:59:59', strtotime('+' . (int) $pk['validity_days'] . ' days'));
    $db->prepare("INSERT INTO customer_packages (user_id, package_id, purchase_reference, package_name_snapshot, validity_days_snapshot, base_amount, gst_percent, gst_amount, total_amount, payment_status, purchased_at, expires_at, status) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?, 'active')")
       ->execute([$uid, $packageId, 'SKSL-PKG-EDGE-' . strtoupper(bin2hex(random_bytes(3))), $pk['name'], $pk['validity_days'], $split['base_price'], 18, $split['gst_amount'], $split['total_amount'], $pay, $expires]);
    $id = (int) $db->lastInsertId();
    foreach ($q('SELECT * FROM package_items WHERE package_id = ?', [$packageId]) as $it) {
        $db->prepare('INSERT INTO customer_package_credits (customer_package_id, service_id, duration_minutes, allocated_quantity, used_quantity, remaining_quantity) VALUES (?,?,?,?,0,?)')
           ->execute([$id, $it['service_id'], $it['duration_minutes'], $it['quantity'], $it['quantity']]);
    }
    return $id;
};
$credit = fn (int $cpId, int $svcId) => $q('SELECT * FROM customer_package_credits WHERE customer_package_id = ? AND service_id = ?', [$cpId, $svcId])[0];

$pa = $mkUser('pkgA');
$pb = $mkUser('pkgB');
$hydro = $mkPaidPackage($pa, 8);          // cycle + treadmill + walker, 15 min each, 1 each
$tw = $mkUser('pkgC');

$r = $book->createPackageBooking($pa, $hydro, (int) $cycle['id'], $D1, $g15[4], true);
check('G1 a package credit books a session', $r['success'] === true, $r['message'] ?? '');
$bk = $r['booking'] ?? [];
check('G2 the booking is confirmed, paid, free and linked to the credit', ($bk['booking_status'] ?? '') === 'confirmed' && ($bk['payment_status'] ?? '') === 'paid' && (float) ($bk['total_amount'] ?? 1) === 0.0 && ($bk['payment_mode'] ?? '') === 'package_credit' && !empty($bk['package_credit_id']));
check('G3 the session length is the package item length (15 min)', ($bk['service_duration_minutes'] ?? 0) == 15 && substr((string) ($bk['end_time'] ?? ''), 0, 5) === sprintf('%02d:%02d', intdiv((int) substr($g15[4], 0, 2) * 60 + (int) substr($g15[4], 3, 2) + 15, 60), ((int) substr($g15[4], 3, 2) + 15) % 60), json_encode([$bk['start_time'] ?? '', $bk['end_time'] ?? '']));
$c = $credit($hydro, (int) $cycle['id']);
check('G4 the credit is used (0 left, 1 used)', (int) $c['remaining_quantity'] === 0 && (int) $c['used_quantity'] === 1);
$r = $book->createPackageBooking($pa, $hydro, (int) $cycle['id'], $D1, $g15[8]);
check('G5 no second session once the credit is spent', $refused($r, 'credit') || $refused($r, 'remaining'), $r['message']);
$r = $book->createPackageBooking($pa, $hydro, (int) $tread['id'], $D1, $g15[4]);
check('G6 the same athlete cannot use a second credit at the same time', $r['success'] === false, $r['message']);
$r = $book->createPackageBooking($pa, $hydro, (int) $tread['id'], $D1, $g15[9]);
check('G7 a different credit at a different time works', $r['success'] === true, $r['message'] ?? '');
$r = $book->createPackageBooking($pa, $hydro, (int) $massage['id'], $D1, $g30[1]);
check('G8 a modality that is not in the package is refused', $refused($r, 'not included'), $r['message']);
$r = $book->createPackageBooking($pb, $hydro, (int) $svcModel->findBySlug('walker')['id'], $D1, $g15[2]);
check("G9 nobody can spend another athlete's package", $refused($r, 'not active'), $r['message']);
check('G10 …and the credit stayed untouched', (int) $credit($hydro, (int) $svcModel->findBySlug('walker')['id'])['remaining_quantity'] === 1);

// capacity: the capacity-1 cycle slot is now taken by the package booking
$r = $book->createHold($pb, (int) $cycle['id'], $D1, $g15[4]);
check('G11 a package booking uses up the slot for paying customers too', $refused($r, 'fully booked'), $r['message']);

// unpaid / expired / out-of-window packages
$unpaid = $mkPaidPackage($pb, 8, null, 'pending');
$r = $book->createPackageBooking($pb, $unpaid, (int) $cycle['id'], $D2, $g15[1]);
check('G12 an unpaid package cannot be used', $r['success'] === false, $r['message']);
$short = $mkPaidPackage($pb, 8, date('Y-m-d 23:59:59', strtotime("$D2 +0 day")));
$r = $book->createPackageBooking($pb, $short, (int) $cycle['id'], $D3, $g15[1]);
check('G13 a session after the package expires is refused', $refused($r, 'expires before'), $r['message']);
$r = $book->createPackageBooking($pb, $short, (int) $cycle['id'], $D2, $g15[1]);
check('G14 a session on the very last valid day is accepted', $r['success'] === true, $r['message'] ?? '');
$gone = $mkPaidPackage($tw, 8, date('Y-m-d H:i:s', strtotime('-1 minute')));
$r = $book->createPackageBooking($tw, $gone, (int) $cycle['id'], $D2, $g15[1]);
check('G15 an already expired package is refused', $r['success'] === false, $r['message']);

// package credit with a 10-minute modality (combo "Sauna & Ice Bath")
$contrast = $mkPaidPackage($pb, 4);
$g10 = BookingRules::gridStarts(10);
$r = $book->createPackageBooking($pb, $contrast, (int) $sauna['id'], $D3, $g10[3]);
check('G16 a 10-minute package session books on the 10-minute grid', $r['success'] === true && ($r['booking']['service_duration_minutes'] ?? 0) == 10, $r['message'] ?? '');
$r = $book->createPackageBooking($pb, $contrast, (int) $svcModel->findBySlug('ice-bath')['id'], $D3, '06:03');
check('G17 an off-grid time is refused for package bookings too', $r['success'] === false);

// restoring a credit (admin cancel / no-show) is idempotent and never goes past what was used
$cid = (int) $credit($hydro, (int) $cycle['id'])['id'];
$a1 = $cpModel->restoreCredit($cid);
$a2 = $cpModel->restoreCredit($cid);
$c = $credit($hydro, (int) $cycle['id']);
check('G18 restoring a credit works once and cannot exceed the allocation', $a1['success'] === true && $a2['success'] === false && (int) $c['remaining_quantity'] === 1 && (int) $c['used_quantity'] === 0, json_encode($c));

// ---------------------------------------------------------------- H. professional appointments
$ru = $mkUser('pro');
$weekdayWithRules = null;
foreach ($days as $d) {
    $w = (new \App\Models\AvailabilityRuleModel())->getAvailableWindows((int) $physio['id'], $d);
    if ($w) { $weekdayWithRules = [$d, $w[0]]; break; }
}
if ($weekdayWithRules) {
    [$pd, [$ws, $we]] = $weekdayWithRules;
    $r = $book->createProfessionalBooking($ru, (int) $physio['id'], $pd, substr($ws, 0, 5));
    check('H1 an appointment inside working hours is confirmed', $r['success'] === true, $r['message'] ?? '');
    $r = $book->createProfessionalBooking($mkUser('pro2'), (int) $physio['id'], $pd, substr($ws, 0, 5));
    check('H2 the practitioner cannot be double-booked', $r['success'] === false, $r['message'] ?? '');
    $r = $book->createProfessionalBooking($ru, (int) $physio['id'], $pd, '05:00');
    check('H3 an appointment outside working hours is refused', $r['success'] === false);
}
$r = $book->createProfessionalBooking($ru, (int) $sauna['id'], $D1, $g15[1]);
check('H4 a recovery modality is not bookable as an appointment', $r['success'] === false, $r['message'] ?? '');

// ---------------------------------------------------------------- cleanup
if ($userIds) {
    $in = implode(',', array_map('intval', $userIds));
    $db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
    $db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in)) OR customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ($in)");
    $db->exec("DELETE FROM bookings WHERE user_id IN ($in)");
    $db->exec("DELETE FROM customer_packages WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
$db->exec("DELETE FROM manual_blocks WHERE reason = 'edge test'");
$db->exec("DELETE FROM closed_dates WHERE reason = 'edge test'");
$svcModel->updateStatus((int) $sauna['id'], 'active');
$db->exec("UPDATE service_duration_options SET status = 'active' WHERE service_id = " . (int) $massage['id'] . " AND status = 'inactive' AND 0");

echo "=== Booking edges: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
