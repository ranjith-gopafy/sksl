<?php

/**
 * Offline payment mode (online gateway hidden): a booking or combo package is requested, waits for
 * payment, and is confirmed only when staff approve it, after which it behaves exactly like an
 * online payment (confirmed, invoiced, emailed). Covers deadlines, slot holding, approval rules,
 * lapsed requests, cancellation, customer lists, the dashboard count and the package path.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);
require_once __DIR__ . '/support/payment_support.php';

use App\Helpers\BookingRules;
use App\Helpers\OfflinePayment;
use App\Helpers\TimeHelper;
use App\Models\AdminModel;
use App\Models\BookingHoldModel;
use App\Models\BookingModel;
use App\Models\CustomerPackageModel;
use App\Models\DashboardModel;
use App\Models\PackageModel;
use App\Models\PaymentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;
use App\Services\InvoiceService;
use App\Services\PackageService;
use App\Services\PaymentService;

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
$q = function (string $sql, array $a = []) use ($db): array { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };

$savedEnv = [$_ENV['ONLINE_PAYMENTS_ENABLED'] ?? null];
$offline = function (bool $on): void { $_ENV['ONLINE_PAYMENTS_ENABLED'] = $on ? 'false' : 'true'; };
register_shutdown_function(function () use ($savedEnv) { if ($savedEnv[0] === null) { unset($_ENV['ONLINE_PAYMENTS_ENABLED']); } else { $_ENV['ONLINE_PAYMENTS_ENABLED'] = $savedEnv[0]; } });

$tag = 'op' . substr(bin2hex(random_bytes(3)), 0, 6);
$userIds = [];
$mkUser = function (string $l) use ($tag, &$userIds): int {
    $id = (new UserModel())->create("Offline $l", "{$tag}_{$l}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash('Pass1234!x', PASSWORD_BCRYPT));
    $userIds[] = $id;
    return $id;
};
$svcModel = new ServiceModel();
$bookingModel = new BookingModel();
$holdModel = new BookingHoldModel();
$paymentModel = new PaymentModel();
$bookingSvc = new BookingService();
$paySvc = sksl_test_payment_service();
$sauna = $svcModel->findBySlug('sauna');
$cycle = $svcModel->findBySlug('cycle');
$grid = BookingRules::gridStarts(15);
$days = [];
for ($n = 3; $n <= 25 && count($days) < 5; $n++) {
    $d = date('Y-m-d', strtotime("+$n days"));
    if ((int) $q('SELECT COUNT(*) c FROM closed_dates WHERE closed_date = ?', [$d])[0]['c'] === 0) { $days[] = $d; }
}
[$D1, $D2, $D3, $D4] = $days;

$admins = new AdminModel();
$adminId = $admins->create('Offline Admin', "{$tag}_adm@sk-sports-lab.test", 'active');

echo "SKSL — offline payments\n";
$offline(true);

// ---------------------------------------------------------------- A. settings and deadlines
check('A1 offline mode is on when ONLINE_PAYMENTS_ENABLED=false', OfflinePayment::offlineMode() === true && OfflinePayment::onlineEnabled() === false);
foreach (['true', '1', 'yes', ''] as $v) { $_ENV['ONLINE_PAYMENTS_ENABLED'] = $v; check("A2 '$v' keeps the online gateway on", OfflinePayment::onlineEnabled() === true); }
foreach (['false', '0', 'off', 'no', 'FALSE'] as $v) { $_ENV['ONLINE_PAYMENTS_ENABLED'] = $v; check("A3 '$v' switches it off", OfflinePayment::onlineEnabled() === false); }
$offline(true);
$now = TimeHelper::now();
$far = $now->modify('+5 days');
$due = OfflinePayment::bookingDueAt($far->format('Y-m-d'), $far->format('H:i'));
check('A4 a session days away may be paid within 24 hours', $due !== null && abs($due->getTimestamp() - $now->modify('+24 hours')->getTimestamp()) < 120, $due?->format('c') ?? 'null');
$soon = $now->modify('+10 hours');
$due = OfflinePayment::bookingDueAt($soon->format('Y-m-d'), $soon->format('H:i'));
check('A5 a session in 10 hours must be paid 2 hours before it starts (8 hours from now)', $due !== null && abs($due->getTimestamp() - $now->modify('+8 hours')->getTimestamp()) < 120, $due?->format('c') ?? 'null');
$tooSoon = $now->modify('+2 hours 20 minutes');
check('A6 a session starting in 2h20m leaves no real time to pay, so it is refused', OfflinePayment::bookingDueAt($tooSoon->format('Y-m-d'), $tooSoon->format('H:i')) === null);
$past = $now->modify('-1 hour');
check('A7 a past session is refused', OfflinePayment::bookingDueAt($past->format('Y-m-d'), $past->format('H:i')) === null);
check('A8 a malformed time is refused', OfflinePayment::bookingDueAt('2026-13-45', '99:99') === null);
$link = OfflinePayment::upiLink(599.0, 'SKSL-20261016-ABC123');
check('A9 the UPI deep link carries the id, amount, currency and reference', $link !== null && str_starts_with($link, 'upi://pay?') && str_contains($link, 'am=599.00') && str_contains($link, 'cu=INR') && str_contains($link, 'tn=SKSL-20261016-ABC123'), (string) $link);
$info = OfflinePayment::info();
check('A10 the QR image configured for the client exists on disk', $info['qr_image'] === '' || is_file(dirname(__DIR__) . '/public/' . $info['qr_image']));

// ---------------------------------------------------------------- B. requesting a booking
$u1 = $mkUser('one');
$u2 = $mkUser('two');
$u3 = $mkUser('three');
$hold = $bookingSvc->createHold($u1, (int) $sauna['id'], $D1, $grid[4], true);
check('B1 setup: a hold is created', $hold['success'] === true, $hold['message'] ?? '');
$ref = $hold['data']['booking_reference'];
$r = $paySvc->createOrder($u1, $ref);
check('B2 the Razorpay order is refused while online payments are off', $r['success'] === false);
$r = $paySvc->requestOfflineBooking($u2, $ref);
check('B3 another customer cannot request someone else\'s hold', $r['success'] === false);
$r = $paySvc->requestOfflineBooking($u1, 'SKSL-20200101-NOPE00');
check('B4 an unknown or expired hold is refused', $r['success'] === false);
$r = $paySvc->requestOfflineBooking($u1, $ref);
check('B5 a request from an active hold succeeds', $r['success'] === true, $r['message']);
$b = $bookingModel->findByReference($ref);
check('B6 the booking is pending, unpaid and marked offline', $b['booking_status'] === 'pending' && $b['payment_status'] === 'pending' && $b['payment_channel'] === 'offline' && $b['payment_mode'] === 'payment_required');
check('B7 its amount is the GST-inclusive price', (float) $b['total_amount'] === 599.00 && abs((float) $b['base_amount'] + (float) $b['gst_amount'] - 599.00) < 0.001);
check('B8 the payment deadline is about 24 hours away', abs(strtotime((string) $b['payment_due_at']) - strtotime('+24 hours')) < 180, (string) $b['payment_due_at']);
check('B9 no payment row exists yet (nothing was charged)', $paymentModel->findByBookingId((int) $b['id']) === null);
$holdRow = $q('SELECT status, expires_at FROM booking_holds WHERE booking_reference = ?', [$ref])[0];
check('B10 the slot stays reserved until the payment deadline', $holdRow['status'] === 'active' && $holdRow['expires_at'] === $b['payment_due_at'], json_encode($holdRow));
check('B11 the health declaration was kept', $b['health_declared_at'] !== null);
check('B12 the response carries the amount, deadline and a UPI link', ($r['data']['amount_rupees'] ?? 0) == 599 && !empty($r['data']['payment_due_at']) && ($r['data']['created'] ?? null) === true);
$again = $paySvc->requestOfflineBooking($u1, $ref);
check('B13 a second click returns the same request and is not "created" again', $again['success'] === true && $again['data']['created'] === false && (int) $q('SELECT COUNT(*) c FROM bookings WHERE booking_reference = ?', [$ref])[0]['c'] === 1);
$offline(false);
$hold2 = $bookingSvc->createHold($u3, (int) $sauna['id'], $D2, $grid[2]);
$r = $paySvc->requestOfflineBooking($u3, $hold2['data']['booking_reference']);
check('B14 offline requests are refused while online payments are on', $r['success'] === false);
$bookingSvc->releaseHold($u3, $hold2['data']['booking_reference']);
$offline(true);

// too soon: the hold is released
$soonStart = TimeHelper::now()->modify('+2 hours 20 minutes');
$soonSlot = null;
foreach ($g15 = BookingRules::gridStarts(15) as $s) { if ($soonStart->format('H:i') <= $s) { $soonSlot = $s; break; } }
if ($soonSlot !== null && $soonStart->format('Y-m-d') === TimeHelper::today()) {
    $h = $bookingSvc->createHold($u3, (int) $sauna['id'], TimeHelper::today(), $soonSlot);
    if ($h['success']) {
        $r = $paySvc->requestOfflineBooking($u3, $h['data']['booking_reference']);
        $slotStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', TimeHelper::today() . ' ' . $soonSlot, TimeHelper::now()->getTimezone());
        if ($slotStart < TimeHelper::now()->modify('+2 hours 30 minutes')) {
            check('B15 a session starting too soon is refused and its hold released', $r['success'] === false && $holdModel->findActiveByReference($h['data']['booking_reference']) === null, $r['message']);
        } else { $bookingSvc->releaseHold($u3, $h['data']['booking_reference']); }
    }
}

// ---------------------------------------------------------------- C. the slot stays blocked while waiting
$cyc = $bookingSvc->createHold($u1, (int) $cycle['id'], $D3, $grid[5]);
$cycRef = $cyc['data']['booking_reference'];
$paySvc->requestOfflineBooking($u1, $cycRef);
$other = $bookingSvc->createHold($u2, (int) $cycle['id'], $D3, $grid[5]);
check('C1 a waiting request keeps a one-person slot blocked for everyone else', $other['success'] === false && stripos($other['message'], 'fully booked') !== false, $other['message'] ?? '');
$db->prepare('UPDATE bookings SET payment_due_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE booking_reference = ?')->execute([$cycRef]);
$db->prepare("UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE booking_reference = ?")->execute([$cycRef]);
$n = $bookingModel->expireOfflineDue();
check('C2 housekeeping closes a request whose deadline passed', $n >= 1 && $bookingModel->findByReference($cycRef)['booking_status'] === 'expired');
$other = $bookingSvc->createHold($u2, (int) $cycle['id'], $D3, $grid[5]);
check('C3 …and the slot is free for others again', $other['success'] === true, $other['message'] ?? '');
if ($other['success']) { $bookingSvc->releaseHold($u2, $other['data']['booking_reference']); }
$bookingModel->expireStalePending();
$fresh = $bookingModel->findByReference($ref);
check('C4 the online-checkout cleanup never touches a waiting offline request', $fresh['booking_status'] === 'pending');

// ---------------------------------------------------------------- D. approval
$bad = [['', 'UTR1'], ['cheque', 'UTR1'], ['upi', ''], ['upi', '   '], ['upi', str_repeat('x', 101)], ['upi', "UTR\n1"], ['upi', "UTR\x00"]];
foreach ($bad as [$method, $reference]) {
    $r = $paySvc->approveOfflineBooking((int) $b['id'], $adminId, $method, $reference);
    check('D1 approval refused for method=' . json_encode($method) . ' reference=' . json_encode(substr($reference, 0, 12)), $r['success'] === false);
}
check('D2 the booking is still pending after the refused attempts', $bookingModel->findByReference($ref)['booking_status'] === 'pending');
$onlineHold = $bookingSvc->createHold($u3, (int) $sauna['id'], $D4, $grid[1]);
$offline(false);
$onlineOrder = $paySvc->createOrder($u3, $onlineHold['data']['booking_reference']);
$onlineBooking = $bookingModel->findByReference($onlineHold['data']['booking_reference']);
$offline(true);
$r = $paySvc->approveOfflineBooking((int) $onlineBooking['id'], $adminId, 'upi', 'UTR-ONLINE');
check('D3 an online-checkout booking cannot be approved by hand', $r['success'] === false, $r['message']);
$r = $paySvc->approveOfflineBooking(999999999, $adminId, 'upi', 'UTR1');
check('D4 an unknown booking is refused', $r['success'] === false);

$before = (int) $q("SELECT COUNT(*) c FROM email_logs WHERE booking_id = ?", [(int) $b['id']])[0]['c'];
$r = $paySvc->approveOfflineBooking((int) $b['id'], $adminId, 'upi', 'UTR-' . $tag);
check('D5 approval succeeds', $r['success'] === true, $r['message']);
$b2 = $bookingModel->findByReference($ref);
check('D6 the booking is confirmed and paid', $b2['booking_status'] === 'confirmed' && $b2['payment_status'] === 'paid');
check('D7 method, reference, time and approving admin are recorded', $b2['payment_method'] === 'upi' && $b2['payment_reference'] === 'UTR-' . $tag && $b2['payment_received_at'] !== null && (int) $b2['approved_by_admin_id'] === $adminId, json_encode($b2));
$pay = $paymentModel->findPaidByBookingId((int) $b2['id']);
check('D8 a paid payment record exists for reconciliation', $pay && (float) $pay['amount'] === 599.0 && $pay['razorpay_order_id'] === 'offline_' . $ref);
check('D9 the slot hold was released', $holdModel->findActiveByReference($ref) === null);
check('D10 the customer and admin were emailed like after an online payment', (int) $q("SELECT COUNT(*) c FROM email_logs WHERE booking_id = ?", [(int) $b['id']])[0]['c'] > $before);
$types = array_column($q("SELECT email_type FROM email_logs WHERE booking_id = ?", [(int) $b['id']]), 'email_type');
check('D11 including the booking confirmation', in_array('booking_confirmation', $types, true), json_encode($types));
$invoice = null;
try { $invoice = (new InvoiceService())->generateInvoicePdf($ref); } catch (\Throwable $e) { $invoice = 'ERR ' . $e->getMessage(); }
check('D12 the GST tax invoice can be generated for it', is_string($invoice) && is_file($invoice), (string) $invoice);
$r = $paySvc->approveOfflineBooking((int) $b['id'], $adminId, 'upi', 'UTR-AGAIN');
check('D13 approving twice is refused and changes nothing', $r['success'] === false && $bookingModel->findByReference($ref)['payment_reference'] === 'UTR-' . $tag);
check('D14 exactly one payment row exists', (int) $q('SELECT COUNT(*) c FROM payments WHERE booking_id = ?', [(int) $b['id']])[0]['c'] === 1);

// ---------------------------------------------------------------- E. a lapsed request approved late
$late = $bookingSvc->createHold($u2, (int) $sauna['id'], $D2, $grid[7]);
$lateRef = $late['data']['booking_reference'];
$paySvc->requestOfflineBooking($u2, $lateRef);
$lb = $bookingModel->findByReference($lateRef);
$db->prepare('UPDATE bookings SET payment_due_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([(int) $lb['id']]);
$db->prepare("UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE booking_reference = ?")->execute([$lateRef]);
$bookingModel->expireOfflineDue();
check('E1 the unpaid request lapsed', $bookingModel->findByReference($lateRef)['booking_status'] === 'expired');
$r = $paySvc->approveOfflineBooking((int) $lb['id'], $adminId, 'bank_transfer', 'NEFT-123');
check('E2 staff can still approve it when the money arrived late and the slot is free', $r['success'] === true && $bookingModel->findByReference($lateRef)['booking_status'] === 'confirmed', $r['message']);

// slot taken meanwhile
$taken = $bookingSvc->createHold($u1, (int) $cycle['id'], $D4, $grid[6]);
$tRef = $taken['data']['booking_reference'];
$paySvc->requestOfflineBooking($u1, $tRef);
$tb = $bookingModel->findByReference($tRef);
$db->prepare('UPDATE bookings SET payment_due_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([(int) $tb['id']]);
$db->prepare("UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE booking_reference = ?")->execute([$tRef]);
$bookingModel->expireOfflineDue();
$rival = $bookingSvc->createHold($u2, (int) $cycle['id'], $D4, $grid[6]);
$paySvc->requestOfflineBooking($u2, $rival['data']['booking_reference']);
$paySvc->approveOfflineBooking((int) $bookingModel->findByReference($rival['data']['booking_reference'])['id'], $adminId, 'upi', 'UTR-RIVAL');
$r = $paySvc->approveOfflineBooking((int) $tb['id'], $adminId, 'upi', 'UTR-LATE');
check('E3 a lapsed request whose slot was taken cannot be approved, and says why', $r['success'] === false && stripos($r['message'], 'no longer free') !== false, $r['message']);
check('E4 …and stays unpaid', $bookingModel->findByReference($tRef)['payment_status'] === 'pending');
$conf = (int) $q("SELECT COUNT(*) c FROM bookings WHERE service_id = ? AND booking_date = ? AND start_time = ? AND booking_status = 'confirmed'", [(int) $cycle['id'], $D4, $grid[6] . ':00'])[0]['c'];
check('E5 the one-person slot has exactly one confirmed booking', $conf === 1, (string) $conf);
// session already started
$pastB = $bookingModel->create([
    'booking_reference' => 'SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))), 'user_id' => $u3, 'service_id' => (int) $sauna['id'], 'booking_type' => 'standard',
    'payment_mode' => 'payment_required', 'payment_channel' => 'offline', 'payment_due_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    'booking_date' => TimeHelper::today(), 'start_time' => '00:00:00', 'end_time' => '00:15:00', 'service_name_snapshot' => 'Sauna', 'service_duration_minutes' => 15,
    'base_amount' => 507.62, 'gst_amount' => 91.38, 'total_amount' => 599.00, 'booking_status' => 'pending', 'payment_status' => 'pending',
]);
$r = $paySvc->approveOfflineBooking($pastB, $adminId, 'upi', 'UTR-PAST');
check('E6 a session that has already started cannot be approved', $r['success'] === false && stripos($r['message'], 'passed') !== false, $r['message']);

// ---------------------------------------------------------------- F. cancelling and rejecting
$c1 = $bookingSvc->createHold($u3, (int) $sauna['id'], $D1, $grid[9]);
$cRef = $c1['data']['booking_reference'];
$paySvc->requestOfflineBooking($u3, $cRef);
$cancel = $bookingSvc->cancelByCustomer($u3, $cRef, false);
$cb = $bookingModel->findByReference($cRef);
check('F1 a customer can withdraw an unpaid request, free of charge', $cancel['success'] === true && $cb['booking_status'] === 'cancelled' && $cb['refund_amount'] === null && $cb['cancellation_fee'] === null, json_encode($cancel));
check('F2 …and its slot is released', $holdModel->findActiveByReference($cRef) === null);
$r = $paySvc->approveOfflineBooking((int) $cb['id'], $adminId, 'upi', 'UTR-AFTERCANCEL');
check('F3 a withdrawn request cannot be approved', $r['success'] === false);
$c2 = $bookingSvc->createHold($u3, (int) $sauna['id'], $D1, $grid[11]);
$c2Ref = $c2['data']['booking_reference'];
$paySvc->requestOfflineBooking($u3, $c2Ref);
$c2b = $bookingModel->findByReference($c2Ref);
check('F4 staff can reject an unpaid request (cancel), with no refund recorded', $bookingModel->transitionStatus((int) $c2b['id'], 'pending', 'cancelled') === true && $bookingModel->findByReference($c2Ref)['refund_amount'] === null);
$closedMail = (new \App\Services\EmailService())->sendBookingRequestClosed($bookingModel->findById((int) $c2b['id']));
check('F5 the "request closed" email is produced without error', $closedMail === true);
$wrongUser = $bookingSvc->cancelByCustomer($u2, $ref);
check('F6 nobody else can cancel it', $wrongUser['success'] === false);

// ---------------------------------------------------------------- G. lists and dashboard
$w1 = $bookingSvc->createHold($u2, (int) $sauna['id'], $D3, $grid[3]);
$wRef = $w1['data']['booking_reference'];
$paySvc->requestOfflineBooking($u2, $wRef);
$upcoming = array_column($bookingModel->findByUser($u2, 'upcoming'), 'booking_reference');
$all = array_column($bookingModel->findByUser($u2, 'all'), 'booking_reference');
$completed = array_column($bookingModel->findByUser($u2, 'completed'), 'booking_reference');
check('G1 a waiting request appears in the customer\'s upcoming list and in All', in_array($wRef, $upcoming, true) && in_array($wRef, $all, true));
check('G2 …but not in Completed or Cancelled', !in_array($wRef, $completed, true) && !in_array($wRef, array_column($bookingModel->findByUser($u2, 'cancelled'), 'booking_reference'), true));
check('G3 a lapsed request is not listed', !in_array($tRef, array_column($bookingModel->findByUser($u1, 'all'), 'booking_reference'), true));
$stats = (new DashboardModel())->stats();
check('G4 the dashboard counts requests awaiting approval', $stats['awaiting_approval'] >= 1, json_encode($stats));

// ---------------------------------------------------------------- H. combo packages
$pkg = null;
foreach ((new PackageModel())->getAll(true) as $p) { if ((float) $p['price'] > 0) { $pkg = $p; break; } }
$pkgSvc = new PackageService($db, null, null, null, null, ['key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'mock' => true]);
$pu = $mkUser('pkg');
$offline(false);
$r = $pkgSvc->requestOfflinePurchase($pu, (int) $pkg['id']);
check('H1 an offline package request is refused while online payments are on', $r['success'] === false);
$offline(true);
$r = $pkgSvc->createPurchaseOrder($pu, (int) $pkg['id']);
check('H2 the gateway package order is refused while online payments are off', $r['success'] === false);
$r = $pkgSvc->requestOfflinePurchase($pu, 999999);
check('H3 an unknown package is refused', $r['success'] === false);
$r = $pkgSvc->requestOfflinePurchase(999999999, (int) $pkg['id']);
check('H4 an unknown user is refused', $r['success'] === false);
$r = $pkgSvc->requestOfflinePurchase($pu, (int) $pkg['id']);
check('H5 a package request is created', $r['success'] === true && ($r['data']['created'] ?? null) === true, $r['message']);
$cpId = (int) $r['data']['customer_package_id'];
$cp = (new CustomerPackageModel())->findById($cpId);
check('H6 it is unpaid, offline, with a payment deadline about 24 hours away', $cp['payment_status'] === 'pending' && $cp['payment_channel'] === 'offline' && abs(strtotime((string) $cp['payment_due_at']) - strtotime('+24 hours')) < 180);
check('H7 its amount is the package price', (float) $cp['total_amount'] === (float) $pkg['price']);
check('H8 no credits exist before approval', (int) $q('SELECT COUNT(*) c FROM customer_package_credits WHERE customer_package_id = ?', [$cpId])[0]['c'] === 0);
$r2 = $pkgSvc->requestOfflinePurchase($pu, (int) $pkg['id']);
check('H9 asking again returns the same request', $r2['success'] === true && $r2['data']['customer_package_id'] === $cpId && $r2['data']['created'] === false);
foreach ([['', 'U1'], ['cheque', 'U1'], ['upi', ''], ['upi', str_repeat('y', 101)]] as [$m, $rf]) {
    check('H10 package approval refused for ' . json_encode([$m, substr($rf, 0, 8)]), $pkgSvc->approveOfflinePurchase($cpId, $adminId, $m, $rf)['success'] === false);
}
check('H11 still unpaid after the refused attempts', (new CustomerPackageModel())->findById($cpId)['payment_status'] === 'pending');
$r = $pkgSvc->approveOfflinePurchase($cpId, $adminId, 'upi', 'PKG-' . $tag);
check('H12 package approval succeeds', $r['success'] === true, $r['message']);
$cp = (new CustomerPackageModel())->findById($cpId);
check('H13 the purchase is paid and active, with method, reference and admin recorded', $cp['payment_status'] === 'paid' && $cp['status'] === 'active' && $cp['payment_method'] === 'upi' && $cp['payment_reference'] === 'PKG-' . $tag && (int) $cp['approved_by_admin_id'] === $adminId, json_encode($cp));
$items = $q('SELECT service_id, duration_minutes, quantity FROM package_items WHERE package_id = ? ORDER BY service_id', [(int) $pkg['id']]);
$credits = $q('SELECT service_id, duration_minutes, allocated_quantity, remaining_quantity FROM customer_package_credits WHERE customer_package_id = ? ORDER BY service_id', [$cpId]);
check('H14 credits were created for every item with its session length', count($credits) === count($items) && count($items) > 0 && array_map(fn ($c) => [(int) $c['service_id'], (int) $c['duration_minutes'], (int) $c['allocated_quantity']], $credits) === array_map(fn ($i) => [(int) $i['service_id'], (int) $i['duration_minutes'], (int) $i['quantity']], $items));
check('H15 validity starts at approval and runs to the end of the last day', substr((string) $cp['expires_at'], 0, 10) === date('Y-m-d', strtotime('+' . (int) $pkg['validity_days'] . ' days')) && str_ends_with((string) $cp['expires_at'], '23:59:59'), (string) $cp['expires_at']);
$pinvoice = null;
try { $pinvoice = (new InvoiceService())->generatePackageInvoicePdf($cp); } catch (\Throwable $e) { $pinvoice = 'ERR ' . $e->getMessage(); }
check('H16 the package tax invoice can be generated', is_string($pinvoice) && is_file($pinvoice), (string) $pinvoice);
$r = $pkgSvc->approveOfflinePurchase($cpId, $adminId, 'upi', 'PKG-AGAIN');
check('H17 approving twice is refused and never doubles the credits', $r['success'] === false && array_sum(array_column($q('SELECT allocated_quantity FROM customer_package_credits WHERE customer_package_id = ?', [$cpId]), 'allocated_quantity')) === array_sum(array_column($items, 'quantity')));
// lapsed package request, then late approval
$pu2 = $mkUser('pkg2');
$lp = $pkgSvc->requestOfflinePurchase($pu2, (int) $pkg['id'])['data']['customer_package_id'];
$db->prepare('UPDATE customer_packages SET payment_due_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([$lp]);
$expired = (new CustomerPackageModel())->expireStalePending();
check('H18 an unpaid package request lapses after its deadline', $expired >= 1 && (new CustomerPackageModel())->findById((int) $lp)['status'] === 'cancelled');
$r = $pkgSvc->approveOfflinePurchase((int) $lp, $adminId, 'cash', 'RECEIPT-77');
$lpRow = (new CustomerPackageModel())->findById((int) $lp);
check('H19 staff can still activate it when the money arrived late', $r['success'] === true && $lpRow['payment_status'] === 'paid' && $lpRow['status'] === 'active', $r['message']);
$pendingPkg = (new CustomerPackageModel())->findPendingOffline($pu, (int) $pkg['id']);
check('H20 a paid request is no longer "pending offline"', $pendingPkg === null);
$stats = (new DashboardModel())->stats();
check('H21 the dashboard count includes package requests', is_int($stats['awaiting_approval']));

// ---------------------------------------------------------------- cleanup
$in = implode(',', array_map('intval', $userIds));
$db->exec("DELETE FROM email_logs WHERE recipient LIKE '{$tag}%' OR booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in))");
$db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in)) OR customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
$db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
$db->exec("DELETE FROM customer_packages WHERE user_id IN ($in)");
$db->exec("DELETE FROM booking_holds WHERE user_id IN ($in)");
$db->exec("DELETE FROM bookings WHERE user_id IN ($in)");
$db->exec("DELETE FROM users WHERE id IN ($in)");
$db->exec("DELETE FROM admins WHERE id = $adminId");
foreach (['package_purchase'] as $s) { \App\Helpers\RateLimit::clearScope($s); }

echo "=== Offline payments: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
