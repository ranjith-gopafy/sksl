<?php

/**
 * Customer cancellation: allowed up to CANCELLATION_NOTICE_HOURS (24) before the start, a
 * CANCELLATION_FEE_PERCENT (10%) charge on online payments, manual refund of the rest, package
 * credits returned, slots released, and every refusal path (late, someone else's, repeated, final states).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;
use App\Models\BookingModel;
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
$bookings = new BookingModel();
$book     = new BookingService();
$tag = 'cx' . substr(bin2hex(random_bytes(3)), 0, 6);
$userIds = [];
$mkUser = function (string $label) use ($tag, &$userIds): int {
    $id = (new UserModel())->create("Cancel $label", "{$tag}_{$label}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash('Pass1234!', PASSWORD_BCRYPT));
    $userIds[] = $id;
    return $id;
};
$q = function (string $sql, array $args = []) use ($db): array { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); };
$row = fn (string $ref) => $q('SELECT * FROM bookings WHERE booking_reference = ?', [$ref])[0];

/** Insert a booking that starts $minutesAhead minutes from now. */
$mk = function (int $uid, string $slug, int $minutesAhead, array $o = []) use ($bookings, $svcModel): string {
    $svc = $svcModel->findBySlug($slug);
    $start = TimeHelper::now()->modify("+$minutesAhead minutes")->setTime((int) TimeHelper::now()->modify("+$minutesAhead minutes")->format('H'), (int) TimeHelper::now()->modify("+$minutesAhead minutes")->format('i'), 0);
    $end = $start->modify('+15 minutes');
    if ($end->format('Y-m-d') !== $start->format('Y-m-d')) { $end = $start->setTime(23, 59, 59); } // a session never crosses midnight
    $ref = 'SKSL-' . $start->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $total = (float) ($o['total'] ?? 599.00);
    $split = ServiceModel::calculatePricing($total);
    $bookings->create([
        'booking_reference' => $ref, 'user_id' => $uid, 'service_id' => (int) $svc['id'],
        'booking_type' => $o['type'] ?? 'standard', 'payment_mode' => $o['mode'] ?? 'payment_required',
        'package_credit_id' => $o['credit'] ?? null,
        'booking_date' => $start->format('Y-m-d'), 'start_time' => $start->format('H:i:s'), 'end_time' => $end->format('H:i:s'),
        'service_name_snapshot' => $svc['name'], 'service_duration_minutes' => 15,
        'base_amount' => $split['base_price'], 'gst_amount' => $split['gst_amount'], 'total_amount' => ($o['mode'] ?? 'payment_required') === 'payment_required' ? $split['total_amount'] : 0,
        'booking_status' => $o['status'] ?? 'confirmed', 'payment_status' => $o['pay'] ?? 'paid', 'visit_status' => $o['visit'] ?? 'pending',
    ]);
    return $ref;
};

echo "SKSL — customer cancellation\n";
check('rule: notice period is 24 hours by default', BookingRules::cancellationNoticeHours() === 24, (string) BookingRules::cancellationNoticeHours());
check('rule: charge is 10% by default', BookingRules::cancellationFeePercent() === 10.0);

$u = $mkUser('a');
$v = $mkUser('b');
$day = 24 * 60;

// ---------------------------------------------------------------- A. money
$ref = $mk($u, 'sauna', $day + 120);                       // 26 hours ahead, paid 599
$r = $book->cancelByCustomer($u, $ref);
check('A1 a paid session more than 24 h ahead can be cancelled', $r['success'] === true, $r['message']);
$b = $row($ref);
check('A2 status is cancelled, by the customer, with a timestamp', $b['booking_status'] === 'cancelled' && $b['cancelled_by'] === 'customer' && $b['cancelled_at'] !== null);
check('A3 charge is 10% (59.90) and the refund 539.10', (float) $b['cancellation_fee'] === 59.90 && (float) $b['refund_amount'] === 539.10, json_encode([$b['cancellation_fee'], $b['refund_amount']]));
check('A4 the payment stays "paid" until SKSL refunds it manually', $b['payment_status'] === 'paid');
check('A5 the note records the charge and refund for staff', str_contains((string) $b['notes'], 'refund due 539.10'), (string) $b['notes']);
check('A6 the response tells the customer the amounts', $r['data']['fee'] === 59.90 && $r['data']['refund'] === 539.10 && $r['data']['paid_online'] === true);

foreach ([175.00, 199.00, 450.00, 399.00, 999.00, 1799.00, 1399.00, 0.10, 449.99, 99999.99] as $amt) {
    $ref = $mk($u, 'sauna', $day + 300, ['total' => $amt]);
    $book->cancelByCustomer($u, $ref);
    $b = $row($ref);
    $okSum = abs((float) $b['cancellation_fee'] + (float) $b['refund_amount'] - $amt) < 0.0001;
    $okFee = abs((float) $b['cancellation_fee'] - round($amt * 0.10, 2)) < 0.0001;
    check("A7 amount $amt: charge + refund equals the amount paid, charge is 10%", $okSum && $okFee, json_encode([$b['cancellation_fee'], $b['refund_amount']]));
}

// ---------------------------------------------------------------- B. the 24-hour boundary
$late = $mk($u, 'sauna', $day - 30);                       // 23.5 h ahead
$r = $book->cancelByCustomer($u, $late);
check('B1 less than 24 h before the start cannot be cancelled online', $r['success'] === false && str_contains($r['message'], '24 hours'), $r['message']);
$b = $row($late);
check('B2 …and the booking is untouched', $b['booking_status'] === 'confirmed' && $b['cancelled_at'] === null && $b['refund_amount'] === null);
$edge = $mk($u, 'sauna', $day - 1);                        // 23 h 59 min ahead
check('B3 one minute inside the limit is refused', $book->cancelByCustomer($u, $edge)['success'] === false);
$ok = $mk($u, 'sauna', $day + 2);                          // 24 h 2 min ahead
check('B4 two minutes outside the limit is accepted', $book->cancelByCustomer($u, $ok)['success'] === true);
$soon = $mk($u, 'sauna', 30);
check('B5 a session starting in 30 minutes cannot be cancelled', $book->cancelByCustomer($u, $soon)['success'] === false);
$past = $mk($u, 'sauna', -120);
check('B6 a session that already started cannot be cancelled', $book->cancelByCustomer($u, $past)['success'] === false);

// ---------------------------------------------------------------- C. who and what
$mine = $mk($u, 'sauna', $day + 600);
$r = $book->cancelByCustomer($v, $mine);
check("C1 nobody can cancel someone else's booking", $r['success'] === false && $r['message'] === 'Booking not found.', $r['message']);
check('C2 …and it stays confirmed', $row($mine)['booking_status'] === 'confirmed');
foreach (['', 'x', "SKSL-1' OR 1=1 --", str_repeat('A', 80), 'SKSL-00000000-NOPE00'] as $bad) {
    check('C3 bad reference refused: ' . json_encode(substr($bad, 0, 20)), $book->cancelByCustomer($u, $bad)['success'] === false);
}
$again = $book->cancelByCustomer($u, $mine);
$twice = $book->cancelByCustomer($u, $mine);
check('C4 cancelling twice: the second attempt is refused', $again['success'] === true && $twice['success'] === false && str_contains($twice['message'], 'already cancelled'), $twice['message']);
$bb = $row($mine);
check('C5 the first cancellation figures are kept', (float) $bb['refund_amount'] === 539.10 && substr_count((string) $bb['notes'], 'Cancelled by the customer') === 1);
foreach ([['completed', 'paid', 'pending'], ['pending', 'pending', 'pending'], ['expired', 'pending', 'pending'], ['confirmed', 'paid', 'visited'], ['confirmed', 'paid', 'no_show']] as [$st, $pay, $visit]) {
    $r2 = $mk($u, 'sauna', $day + 900, ['status' => $st, 'pay' => $pay, 'visit' => $visit]);
    check("C6 a $st/$visit booking cannot be cancelled", $book->cancelByCustomer($u, $r2)['success'] === false);
    check("C7 …and stays $st", $row($r2)['booking_status'] === $st);
}

// ---------------------------------------------------------------- D. no money involved
$pc = $mk($u, 'physiotherapy', $day + 700, ['mode' => 'payment_not_required', 'type' => 'professional', 'pay' => 'pending']);
$r = $book->cancelByCustomer($u, $pc);
$b = $row($pc);
check('D1 a pay-at-centre appointment can be cancelled with nothing to refund', $r['success'] === true && $b['refund_amount'] === null && $b['cancellation_fee'] === null && $r['data']['paid_online'] === false);

// package credit comes back
$cyc = $svcModel->findBySlug('cycle');
$db->prepare("INSERT INTO customer_packages (user_id, package_id, purchase_reference, package_name_snapshot, validity_days_snapshot, base_amount, gst_percent, gst_amount, total_amount, payment_status, purchased_at, expires_at, status) VALUES (?, 8, ?, 'Edge', 30, 100, 18, 18, 118, 'paid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')")->execute([$u, 'SKSL-PKG-' . strtoupper(bin2hex(random_bytes(3)))]);
$cpId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO customer_package_credits (customer_package_id, service_id, duration_minutes, allocated_quantity, used_quantity, remaining_quantity) VALUES (?, ?, 15, 1, 1, 0)')->execute([$cpId, (int) $cyc['id']]);
$creditId = (int) $db->lastInsertId();
$pk = $mk($u, 'cycle', $day + 800, ['mode' => 'package_credit', 'type' => 'package', 'credit' => $creditId]);
$r = $book->cancelByCustomer($u, $pk);
$c = $q('SELECT remaining_quantity, used_quantity FROM customer_package_credits WHERE id = ?', [$creditId])[0];
check('D2 cancelling a package session returns the credit', $r['success'] === true && $r['data']['credit_returned'] === true && (int) $c['remaining_quantity'] === 1 && (int) $c['used_quantity'] === 0, json_encode([$r, $c]));
check('D3 a package session has no charge or refund amount', $row($pk)['refund_amount'] === null);
$book->cancelByCustomer($u, $pk);
$c = $q('SELECT remaining_quantity FROM customer_package_credits WHERE id = ?', [$creditId])[0];
check('D4 a repeat cancel cannot return the credit twice', (int) $c['remaining_quantity'] === 1);

// ---------------------------------------------------------------- E. the slot is released
$g = BookingRules::gridStarts(15);
$slotDay = date('Y-m-d', strtotime('+4 days'));
$free = $q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [$slotDay])[0]['c'] == 0;
if ($free) {
    $w = $mkUser('w');
    $x = $mkUser('x');
    $ref = 'SKSL-' . date('Ymd', strtotime($slotDay)) . '-' . strtoupper(bin2hex(random_bytes(3)));
    $bookings->create([
        'booking_reference' => $ref, 'user_id' => $w, 'service_id' => (int) $cyc['id'], 'booking_type' => 'standard', 'payment_mode' => 'payment_required',
        'booking_date' => $slotDay, 'start_time' => $g[3] . ':00', 'end_time' => date('H:i:s', strtotime($g[3] . ' +15 minutes')),
        'service_name_snapshot' => 'Cycle', 'service_duration_minutes' => 15, 'base_amount' => 148.31, 'gst_amount' => 26.69, 'total_amount' => 175.00,
        'booking_status' => 'confirmed', 'payment_status' => 'paid',
    ]);
    $r = $book->createHold($x, (int) $cyc['id'], $slotDay, $g[3]);
    check('E1 the capacity-1 slot is taken before cancelling', $r['success'] === false);
    $book->cancelByCustomer($w, $ref);
    $r = $book->createHold($x, (int) $cyc['id'], $slotDay, $g[3]);
    check('E2 after the customer cancels, someone else can book that slot at once', $r['success'] === true, $r['message'] ?? '');
}

// ---------------------------------------------------------------- F. configurable rules
$orig = [$_ENV['CANCELLATION_FEE_PERCENT'] ?? null, $_ENV['CANCELLATION_NOTICE_HOURS'] ?? null];
$sample = ['booking_status' => 'confirmed', 'payment_mode' => 'payment_required', 'payment_status' => 'paid', 'total_amount' => '1000.00', 'visit_status' => 'pending',
           'booking_date' => date('Y-m-d', strtotime('+5 days')), 'start_time' => '10:00:00'];
foreach (['0' => [0.0, 1000.0], '25' => [250.0, 750.0], '100' => [1000.0, 0.0], 'abc' => [100.0, 900.0], '' => [100.0, 900.0], '-5' => [0.0, 1000.0], '250' => [1000.0, 0.0], '12.5' => [125.0, 875.0]] as $pct => [$fee, $refund]) {
    $_ENV['CANCELLATION_FEE_PERCENT'] = (string) $pct;
    $qt = BookingRules::cancellationQuote($sample);
    check("F1 fee setting '$pct' gives charge $fee / refund $refund", $qt['allowed'] && $qt['fee'] === $fee && $qt['refund'] === $refund, json_encode($qt));
}
$_ENV['CANCELLATION_FEE_PERCENT'] = '10';
foreach (['0' => true, '48' => true, '200' => false] as $hrs => $allowed) {
    $_ENV['CANCELLATION_NOTICE_HOURS'] = (string) $hrs;
    check("F2 notice of $hrs hours for a session 5 days away -> " . ($allowed ? 'allowed' : 'refused'), BookingRules::cancellationQuote($sample)['allowed'] === $allowed);
}
foreach ($orig as $i => $val) {
    $key = $i === 0 ? 'CANCELLATION_FEE_PERCENT' : 'CANCELLATION_NOTICE_HOURS';
    if ($val === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $val; }
}

// ---------------------------------------------------------------- G. admin cancellation is recorded too
$paidAdmin = $mk($u, 'sauna', $day + 1000);
$id = (int) $row($paidAdmin)['id'];
check('G1 an admin cancellation succeeds once', $bookings->transitionStatus($id, 'confirmed', 'cancelled') === true && $bookings->transitionStatus($id, 'confirmed', 'cancelled') === false);
$b = $row($paidAdmin);
check('G2 …is recorded as by the admin with the full amount to refund', $b['cancelled_by'] === 'admin' && (float) $b['refund_amount'] === 599.00 && $b['cancellation_fee'] === null, json_encode($b));
$freeAdmin = $mk($u, 'physiotherapy', $day + 1100, ['mode' => 'payment_not_required', 'type' => 'professional', 'pay' => 'pending']);
$bookings->transitionStatus((int) $row($freeAdmin)['id'], 'confirmed', 'cancelled');
check('G3 an admin cancellation with no payment has no refund amount', $row($freeAdmin)['refund_amount'] === null);

// ---------------------------------------------------------------- cleanup
if ($userIds) {
    $in = implode(',', array_map('intval', $userIds));
    $db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
    $db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in))");
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ($in)");
    $db->exec("DELETE FROM bookings WHERE user_id IN ($in)");
    $db->exec("DELETE FROM customer_packages WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}

echo "=== Cancellation: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
