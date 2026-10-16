<?php
/**
 * Phase 2 audit (9 Oct 2026) — regression suite.
 *
 *   C1  bookings are written with a visit_status the enum accepts (checked under STRICT SQL mode)
 *   C2  duration-option prices are ex-GST: checkout total equals the advertised price
 *   H1  package payments settle from the webhook as well as the browser callback, once
 *   H3  consumeCredit has no id-confusion fallback; a package only books its own modalities
 *   H4  professional / package bookings obey hours, grid, rules and capacity like holds do
 *   H5  visit / no-show are once-only, so a package credit can never be restored twice
 *   M4  admin input parsers reject malformed times, dates, enums
 *   M7  free bookings never get a tax invoice
 *
 * Run: php tests/test_phase2_audit_fixes.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\AdminInput;
use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;
use App\Models\BookingModel;
use App\Models\CustomerPackageModel;
use App\Models\PackageModel;
use App\Models\PaymentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;
use App\Services\InvoiceService;
use App\Services\InvoiceNotAvailableException;
use App\Services\PackageService;
use App\Services\PaymentService;

$pass = 0;
$fail = 0;
function check(bool $ok, string $label): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

$db = getDb();
// The whole point of C1: production servers run strict mode, the dev server does not.
$db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

$_ENV['SAME_DAY_CUTOFF_MINUTES'] = '0';
$_ENV['ADVANCE_BOOKING_DAYS']    = '30';

$users    = new UserModel();
$services = new ServiceModel();
$bookings = new BookingModel();
$stamp    = substr(bin2hex(random_bytes(4)), 0, 8);
$uid      = $users->create('Audit Fix Tester', "auditfix_{$stamp}@sk-sports-lab.test", '9000000077', password_hash('Audit@12345', PASSWORD_DEFAULT));
$uid2     = $users->create('Audit Fix Other', "auditfix2_{$stamp}@sk-sports-lab.test", '9000000078', password_hash('Audit@12345', PASSWORD_DEFAULT));

$bookingSvc = new BookingService();
$date       = TimeHelper::now()->modify('+12 days')->format('Y-m-d');
$sauna      = $services->findBySlug('sauna');
$massages   = $services->findBySlug('massages');
$iceBath    = $services->findBySlug('ice-bath');
$physio     = $services->findBySlug('physiotherapy');
$g15        = BookingRules::gridStarts(15);

echo "\n--- Service catalogue filter (re-audit) ---\n";
$allActive = $services->getAllActive('all');
$cats = array_unique(array_column($allActive, 'category'));
sort($cats);
check($cats === ['modality', 'professional'], "S1 getAllActive('all') returns every active category, not none (" . implode(',', $cats) . ')');
check(count($services->getAllActive()) === count($services->getModalities()) && count($allActive) > count($services->getModalities()), 'S2 the default call still returns modalities only');

echo "\n--- Price list: GST-inclusive, charged exactly ---\n";
$priceList = ['sauna' => [599, 15], 'steam' => [450, 15], 'ice-bath' => [399, 15], 'endless-pool' => [599, 30],
              'cycle' => [175, 15], 'walker' => [175, 15], 'treadmill' => [175, 15], 'lap-pool' => [499, 45], 'massages' => [999, 30]];
foreach ($priceList as $slug => [$listed, $mins]) {
    $s = $services->findBySlug($slug);
    $p = ServiceModel::calculatePricing((float) $s['price'], (float) $s['gst_percent']);
    check((int) $s['duration_minutes'] === $mins && abs($p['total_amount'] - $listed) < 0.0001, "P1 {$slug}: {$mins} min, customer pays exactly ₹{$listed}");
    $half = round($p['gst_amount'] / 2, 2);
    check(abs($half - ($p['gst_amount'] - $half)) < 0.0001 && abs($p['base_price'] + $p['gst_amount'] - $listed) < 0.0001, "P1b {$slug}: CGST and SGST are identical halves ({$half} + {$half}) and base + GST = ₹{$listed}");
}
$optPrices = [];
foreach ($optModel0 = (new \App\Models\DurationOptionModel())->getByServiceId((int) $services->findBySlug('massages')['id'], true) as $o) {
    $optPrices[(int) $o['duration_minutes']] = ServiceModel::calculatePricing((float) $o['price'], 18.0)['total_amount'];
}
check($optPrices === [30 => 999.0, 60 => 1499.0, 90 => 1799.0], 'P2 massage 30/60/90 min cost exactly 999 / 1499 / 1799 (' . json_encode($optPrices) . ')');
$combos = ['sauna-ice-bath-contrast' => [499, ['sauna' => 10, 'ice-bath' => 10]],
           'steam-ice-bath-flush' => [399, ['steam' => 10, 'ice-bath' => 10]],
           'massage-steam-ice-reset' => [1399, ['massages' => 30, 'steam' => 10, 'ice-bath' => 5]],
           'massage-shower-ice-protocol' => [1299, ['massages' => 30, 'ice-bath' => 10]],
           'hydrotherapy-circuit-45m' => [499, ['cycle' => 15, 'walker' => 15, 'treadmill' => 15]]];
foreach ($combos as $slug => [$listed, $expectItems]) {
    $pk = (new PackageModel())->findBySlug($slug);
    $split = ServiceModel::calculatePricing((float) $pk['price'], (float) $pk['gst_percent']);
    $got = [];
    foreach ($pk['items'] as $it) { $got[$it['service_slug']] = (int) $it['duration_minutes']; }
    ksort($got); ksort($expectItems);
    check(abs($split['total_amount'] - $listed) < 0.0001 && $got === $expectItems, "P3 combo {$slug}: ₹{$listed} and session lengths " . json_encode($got));
}
check(facility_time_label('open') === '6:00 AM' && facility_time_label('close') === '9:00 PM' && facility_time_label('last_slot') === '8:00 PM', 'P4 hours copy is rendered from config: 6:00 AM – 9:00 PM, last slot 8:00 PM');

echo "\n--- C2. Option prices are GST-inclusive ---\n";
$optModel = new \App\Models\DurationOptionModel();
$opt60 = null;
foreach ($optModel->getByServiceId((int) $massages['id'], true) as $o) {
    if ((int) $o['duration_minutes'] === 60) { $opt60 = $o; }
}
check($opt60 !== null, 'C2.0 massage 60-minute option exists');
$hold = $bookingSvc->createHold($uid, (int) $massages['id'], $date, BookingRules::gridStarts(60)[3], true, (int) $opt60['id']);
check($hold['success'] === true, 'C2.1 hold with a duration option succeeds (' . ($hold['message'] ?? '') . ')');
check(abs(($hold['data']['pricing']['total_amount'] ?? 0) - 1499.00) < 0.011, 'C2.2 60-min massage totals the advertised ₹1,499 (got ' . ($hold['data']['pricing']['total_amount'] ?? 'n/a') . ')');
$holdSauna = $bookingSvc->createHold($uid, (int) $sauna['id'], $date, $g15[20], true, null);
check(abs(($holdSauna['data']['pricing']['total_amount'] ?? 0) - 599.00) < 0.011, 'C2.3 sauna totals the advertised ₹599 (got ' . ($holdSauna['data']['pricing']['total_amount'] ?? 'n/a') . ')');
foreach ([$hold, $holdSauna] as $h) {
    if (!empty($h['data']['booking_reference'])) { $bookingSvc->releaseHold($uid, $h['data']['booking_reference']); }
}

echo "\n--- L1. Professional services are never paid holds ---\n";
$profHold = $bookingSvc->createHold($uid, (int) $physio['id'], $date, '10:00', true, null);
check($profHold['success'] === false, 'L1.1 a hold on a pay-at-centre professional service is refused');

echo "\n--- C1 / H4. Professional booking under strict SQL mode ---\n";
$bad = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $date, '03:00', true, null);
check($bad['success'] === false, 'H4.1 03:00 appointment is refused (outside facility hours)');
$late = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $date, '23:50', true, null);
check($late['success'] === false, 'H4.2 23:50 appointment cannot spill past midnight');
$badTime = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $date, '25:99', true, null);
check($badTime['success'] === false, 'H4.3 malformed time is refused');
$badOpt = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $date, '10:00', true, 999999);
check($badOpt['success'] === false && str_contains($badOpt['message'], 'duration option'), 'H4.4 an invalid duration option is refused, not ignored');

// Find a bookable day/time from the practitioner's rules
$availability = new \App\Services\AvailabilityService();
$proDate = null; $proStart = null;
for ($i = 3; $i <= 20 && $proStart === null; $i++) {
    $d = TimeHelper::now()->modify("+{$i} days")->format('Y-m-d');
    $res = $availability->getAvailableSlots((int) $physio['id'], $d, $uid);
    foreach (($res['slots'] ?? []) as $s) {
        if ($s['available']) { $proDate = $d; $proStart = $s['start_time']; break; }
    }
}
check($proStart !== null, 'H4.5 found an open practitioner slot (' . ($proDate ?? '-') . ' ' . ($proStart ?? '-') . ')');
$proBooking = null;
if ($proStart !== null) {
    $proRes = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $proDate, $proStart, true, null);
    check($proRes['success'] === true, 'C1.1 professional booking inserts under STRICT mode (' . ($proRes['message'] ?? '') . ')');
    $proBooking = $proRes['booking'] ?? null;
    check(($proBooking['visit_status'] ?? '') === 'pending', 'C1.2 visit_status is "pending", not blank');
    check(($proBooking['payment_mode'] ?? '') === 'payment_not_required', 'M7.0 professional booking is payment_not_required');

    $dup = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $proDate, $proStart, true, null);
    check($dup['success'] === false, 'H4.6 the same slot cannot be booked twice (capacity / overlap)');

    if ($proBooking) {
        try {
            (new InvoiceService())->getInvoiceData((string) $proBooking['booking_reference']);
            check(false, 'M7.1 a free appointment must not get a tax invoice');
        } catch (InvoiceNotAvailableException $e) {
            check(true, 'M7.1 a free appointment gets no tax invoice');
        }
    }
}

echo "\n--- Package purchase: H1 webhook settlement, once only ---\n";
$gateway = ['key_id' => 'rzp_test_audit', 'key_secret' => 'audit-secret', 'webhook_secret' => 'audit-webhook', 'mock' => true];
$pkgSvc  = new PackageService(null, null, null, null, null, $gateway);
$pkg     = (new PackageModel())->findBySlug('sauna-ice-bath-contrast');
check($pkg !== null && $pkg['status'] === 'active', 'H1.0 seeded package available');

$order = $pkgSvc->createPurchaseOrder($uid, (int) $pkg['id']);
check($order['success'] === true, 'H1.1 package order created (' . ($order['message'] ?? '') . ')');
$orderId = (string) $order['data']['order_id'];
$cpId    = (int) $order['data']['customer_package_id'];
$payRow  = (new PaymentModel())->findByOrderId($orderId);
check(abs((float) $payRow['amount'] - 499.00) < 0.011, 'H1.2 payments.amount is in rupees (' . $payRow['amount'] . '), matching bookings');

$again = $pkgSvc->createPurchaseOrder($uid, (int) $pkg['id']);
check(($again['data']['order_id'] ?? '') === $orderId, 'M3.1 a repeat click reuses the pending order');

$cpModel = new CustomerPackageModel();
check(count($cpModel->getCredits($cpId)) === 0, 'H1.3 no credits before payment');

// Customer closes the tab: only the webhook arrives.
$payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
    'id' => 'pay_audit_' . $stamp, 'order_id' => $orderId, 'amount' => 49900, 'currency' => 'INR', 'status' => 'captured',
]]]]);
$sig  = hash_hmac('sha256', $payload, 'audit-webhook');
$paySvc = new PaymentService(null, null, null, null, null, null, $gateway);
$wh = $paySvc->handleWebhook($payload, $sig);
check($wh['code'] === 200 && str_contains($wh['message'], 'processed'), 'H1.4 webhook settles the package (' . $wh['message'] . ')');
$credits = $cpModel->getCredits($cpId);
check(count($credits) === 2, 'H1.5 credits provisioned from the webhook alone');
$cp = $cpModel->findById($cpId);
check($cp['payment_status'] === 'paid', 'H1.6 purchase marked paid');
check(strtotime((string) $cp['expires_at']) >= strtotime('+29 days 23 hours'), 'H1.7 validity runs from payment time');
check(substr((string) $cp['expires_at'], 11) === '23:59:59', 'H1.7b "valid until <date>" means the whole of that day (expires_at ' . $cp['expires_at'] . ')');

// Use one credit, then replay both settlement paths: nothing may be reset.
$sauCredit = null;
foreach ($credits as $c) { if ((int) $c['service_id'] === (int) $sauna['id']) { $sauCredit = $c; } }
$bookPkg = $bookingSvc->createPackageBooking($uid, $cpId, (int) $sauna['id'], $date, $g15[8], true);
check($bookPkg['success'] === true, 'H4.7 package booking succeeds under strict mode (' . ($bookPkg['message'] ?? '') . ')');
$wh2 = $paySvc->handleWebhook($payload, $sig);
$sigCb = hash_hmac('sha256', $orderId . '|pay_audit_' . $stamp, 'audit-secret');
$cb2 = $pkgSvc->verifyPurchase($uid, $orderId, 'pay_audit_' . $stamp, $sigCb);
check($wh2['code'] === 200 && $cb2['success'] === true, 'H1.8 replayed webhook / browser callback are accepted harmlessly');
$remaining = 0;
foreach ($cpModel->getCredits($cpId) as $c) { if ((int) $c['service_id'] === (int) $sauna['id']) { $remaining = (int) $c['remaining_quantity']; } }
check($remaining === 0, 'H1.9 replays do not reset used credits (sauna credit still spent)');

$otherUserCb = $pkgSvc->verifyPurchase($uid2, $orderId, 'pay_audit_' . $stamp, $sigCb);
check($otherUserCb['success'] === false, 'H1.10 another customer cannot claim the purchase');

echo "\n--- H3 / H4. Package booking rules ---\n";
$notInPkg = $bookingSvc->createPackageBooking($uid, $cpId, (int) $massages['id'], $date, '08:00', true);
check($notInPkg['success'] === false && str_contains($notInPkg['message'], 'not included'), 'H3.1 a modality outside the package cannot be booked');
$noFallback = $cpModel->consumeCredit($cpId, (int) $massages['id']);
check($noFallback['success'] === false, 'H3.2 consumeCredit has no id-confusion fallback');
$avCredit = $availability->getAvailableSlots((int) $sauna['id'], $date, $uid, null, 10);
check(array_column($avCredit['slots'] ?? [], 'start_time') === BookingRules::gridStarts(10) && (int) ($avCredit['service']['duration_minutes'] ?? 0) === 10, 'H3.3 availability for a 10-minute package credit offers the 10-minute grid, not the 15-minute one');
$avNormal = $availability->getAvailableSlots((int) $sauna['id'], $date, $uid);
check(array_column($avNormal['slots'] ?? [], 'start_time') === BookingRules::gridStarts(15), 'H3.4 a normal booking still uses the service\'s own 15-minute grid');
$offHours = $bookingSvc->createPackageBooking($uid, $cpId, (int) $iceBath['id'], $date, '03:00', true);
check($offHours['success'] === false, 'H4.8 package booking at 03:00 is refused');
$offGrid = $bookingSvc->createPackageBooking($uid, $cpId, (int) $iceBath['id'], $date, '09:07', true);
check($offGrid['success'] === false, 'H4.9 package booking off the slot grid is refused');
$pastValidity = $bookingSvc->createPackageBooking($uid, $cpId, (int) $iceBath['id'], TimeHelper::now()->modify('+31 days')->format('Y-m-d'), '09:00', true);
check($pastValidity['success'] === false, 'H4.10 a date beyond the booking window / package validity is refused');
$overlapSame = $bookingSvc->createPackageBooking($uid, $cpId, (int) $iceBath['id'], $date, $g15[8], true);
check($overlapSame['success'] === false && str_contains($overlapSame['message'], 'confirmed booking'), 'H4.11 athlete cannot be in two sessions at once (cross-modality overlap)');

echo "\n--- H5. Visit / no-show are once-only ---\n";
$pkgBooking = $bookPkg['booking'] ?? null;
if ($pkgBooking) {
    $bid = (int) $pkgBooking['id'];
    check($bookings->markNoShow($bid, 0) === false && $bookings->markVisited($bid, 0) === false, 'H5.0 a session that has not started yet cannot be marked no-show or visited');
    // pretend the session was yesterday so attendance can be recorded
    $db->exec("UPDATE bookings SET booking_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id = {$bid}");
    check($bookings->markNoShow($bid, 0) === true, 'H5.1 first no-show applies');
    check(\App\Models\BookingModel::checkAdminTransition(array_merge($pkgBooking, ['visit_status' => 'no_show']), 'completed')['allowed'] === false, 'H5.1b a no-show booking cannot also be completed');
    check($bookings->markNoShow($bid, 0) === false, 'H5.2 second no-show is a no-op');
    check($bookings->markVisited($bid, 0) === false, 'H5.3 an already-marked booking cannot be flipped to visited');
    $r1 = $cpModel->restoreCredit((int) $pkgBooking['package_credit_id']);
    check($r1['success'] === true, 'H5.4 credit restored once');
    $r2 = $cpModel->restoreCredit((int) $pkgBooking['package_credit_id']);
    check($r2['success'] === false, 'H5.5 a second restore finds nothing to restore (used_quantity is 0)');
}
if ($proBooking) {
    $bookings->updateStatus((int) $proBooking['id'], 'cancelled');
    check($bookings->markVisited((int) $proBooking['id'], 0) === false, 'H5.6 a cancelled booking cannot be marked visited');
    check($bookings->markNoShow((int) $proBooking['id'], 0) === false, 'H5.7 a cancelled booking cannot be marked no-show');
}

echo "\n--- M4. Admin input parsers ---\n";
check(AdminInput::time('09:30') === '09:30:00', 'M4.1 HH:MM normalises to HH:MM:SS');
check(AdminInput::time('24:00') === null && AdminInput::time('9:5') === null && AdminInput::time("09:30'; DROP") === null, 'M4.2 malformed times rejected');
check(AdminInput::date('2026-02-30') === null && AdminInput::date('2026-10-09') === '2026-10-09', 'M4.3 impossible dates rejected');
check(AdminInput::weekday('7') === null && AdminInput::weekday('0') === 0 && AdminInput::weekday('') === null, 'M4.4 weekday must be 0-6');
check(AdminInput::oneOf('evil', ['a', 'b']) === null, 'M4.5 enum values are whitelisted');

echo "\n--- Availability windows (re-audit) ---\n";
$ruleModel = new \App\Models\AvailabilityRuleModel();
// Next Monday: physio works 09:00-18:00 (seeded recurring rule)
$monday = TimeHelper::now()->modify('next monday')->format('Y-m-d');
$base = $ruleModel->getAvailableWindows((int) $physio['id'], $monday);
check($base === [['09:00:00', '18:00:00']], 'W1 recurring window is returned (' . json_encode($base) . ')');
$exId = $ruleModel->create([
    'service_id' => (int) $physio['id'], 'rule_type' => 'specific_date', 'specific_date' => $monday,
    'start_time' => '12:00:00', 'end_time' => '14:00:00', 'is_available' => 0, 'notes' => 'audit test', 'status' => 'active',
]);
$cut = $ruleModel->getAvailableWindows((int) $physio['id'], $monday);
check($cut === [['09:00:00', '12:00:00'], ['14:00:00', '18:00:00']], 'W2 an unavailable exception removes only its window (' . json_encode($cut) . ')');
$slotsCut = $availability->getAvailableSlots((int) $physio['id'], $monday, null)['slots'] ?? [];
$offered = array_column($slotsCut, 'start_time');
check($offered !== [] && !array_filter($offered, static fn ($t) => $t > '11:15' && $t < '14:00'), 'W3 no slot is offered inside the excluded window');
$inGap = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $monday, '12:30', true, null);
check($inGap['success'] === false, 'W4 a booking inside the excluded window is refused');
$ruleModel->delete($exId);
// A professional service with no rules at all must not fall back to the open grid
$db->exec("UPDATE availability_rules SET status = 'inactive' WHERE service_id = " . (int) $physio['id']);
$noRules = $availability->getAvailableSlots((int) $physio['id'], $monday, null);
check(($noRules['slots'] ?? []) === [], 'W5 professional service with no rules offers no slots');
$noRulesBook = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $monday, '10:00', true, null);
check($noRulesBook['success'] === false, 'W6 professional booking with no rules is refused');
$db->exec("UPDATE availability_rules SET status = 'active' WHERE service_id = " . (int) $physio['id']);
$offGridPro = $bookingSvc->createProfessionalBooking($uid, (int) $physio['id'], $monday, '09:07', true, null);
check($offGridPro['success'] === false, 'W7 a practitioner appointment must sit on the schedule spacing (09:07 refused)');
// a standing weekly lunch break is reopened by special hours for one specific date
$lunchId = $ruleModel->create(['service_id' => (int) $physio['id'], 'rule_type' => 'recurring_weekday', 'weekday' => 1, 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'is_available' => 0, 'notes' => 'audit lunch', 'status' => 'active']);
$withLunch = $ruleModel->getAvailableWindows((int) $physio['id'], $monday);
$specialId = $ruleModel->create(['service_id' => (int) $physio['id'], 'rule_type' => 'specific_date', 'specific_date' => $monday, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_available' => 1, 'notes' => 'audit special', 'status' => 'active']);
$withSpecial = $ruleModel->getAvailableWindows((int) $physio['id'], $monday);
check($withLunch === [['09:00:00', '12:00:00'], ['13:00:00', '18:00:00']] && $withSpecial === [['09:00:00', '18:00:00']], 'W8 special hours for one date override the weekly lunch break (' . json_encode($withLunch) . ' then ' . json_encode($withSpecial) . ')');
$ruleModel->delete($lunchId);
$ruleModel->delete($specialId);
check(\App\Helpers\BookingRules::facilityMinutes() === [360, 1260] && \App\Helpers\BookingRules::lastSlotMinutes() === 1200, 'W9 facility hours resolve to 06:00-21:00 with last start 20:00');
$db->exec("UPDATE availability_rules SET status = 'active' WHERE service_id = " . (int) $physio['id']);

echo "\n--- Late payment must not oversell a slot ---\n";
$lapPool = $services->findBySlug('lap-pool');                       // capacity 1
$lateDate = TimeHelper::now()->modify('+16 days')->format('Y-m-d');
$lateStart = BookingRules::gridStarts(45)[5];
$paySvc2 = new PaymentService(null, null, null, null, null, null, $gateway);
$sign = static fn (string $order, string $pay): string => PaymentService::signCheckout($order, $pay, 'audit-secret');

// Athlete A holds the only slot and opens checkout, then the hold lapses before A pays.
$holdA = $bookingSvc->createHold($uid, (int) $lapPool['id'], $lateDate, $lateStart, true, null);
check($holdA['success'] === true, 'L1 A holds the capacity-1 slot (' . ($holdA['message'] ?? '') . ')');
$refA = $holdA['data']['booking_reference'];
$orderA = $paySvc2->createOrder($uid, $refA);
check($orderA['success'] === true, 'L2 A opens a payment order');
$db->exec("UPDATE booking_holds SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE booking_reference = '{$refA}'");

// Athlete B takes the freed slot and pays.
$holdB = $bookingSvc->createHold($uid2, (int) $lapPool['id'], $lateDate, $lateStart, true, null);
check($holdB['success'] === true, 'L3 B can hold the slot once A\'s hold lapsed');
$refB = $holdB['data']['booking_reference'];
$orderB = $paySvc2->createOrder($uid2, $refB);
$payB = 'pay_late_b_' . $stamp;
$resB = $paySvc2->verifyPayment($uid2, $refB, $orderB['data']['razorpay_order_id'], $payB, $sign($orderB['data']['razorpay_order_id'], $payB));
check($resB['success'] === true, 'L4 B pays and is confirmed');

// A's payment finally lands.
$alertsBefore = (int) $db->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'admin_payment_after_close'")->fetchColumn();
$payA = 'pay_late_a_' . $stamp;
$sigA = $sign($orderA['data']['razorpay_order_id'], $payA);
$resA = $paySvc2->verifyPayment($uid, $refA, $orderA['data']['razorpay_order_id'], $payA, $sigA);
$confirmedInSlot = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE service_id = " . (int) $lapPool['id'] . " AND booking_date = '{$lateDate}' AND start_time = '{$lateStart}:00' AND booking_status = 'confirmed'")->fetchColumn();
$statusA = (string) $db->query("SELECT booking_status FROM bookings WHERE booking_reference = '{$refA}'")->fetchColumn();
check($confirmedInSlot === 1, 'L5 only one booking is confirmed for the capacity-1 slot (got ' . $confirmedInSlot . ')');
check($resA['success'] === false && $statusA === 'expired', 'L6 A\'s late payment is NOT confirmed; booking closed, customer told to contact SKSL (status ' . $statusA . ')');
$payRowA = (new PaymentModel())->findByOrderId($orderA['data']['razorpay_order_id']);
check($payRowA['status'] === 'paid', 'L7 the late payment is still recorded as paid so staff can refund it');
$alertsAfterFirst = (int) $db->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'admin_payment_after_close'")->fetchColumn();
check($alertsAfterFirst === $alertsBefore + 1, 'L8 staff are alerted once');
$paySvc2->verifyPayment($uid, $refA, $orderA['data']['razorpay_order_id'], $payA, $sigA);
$paySvc2->verifyPayment($uid, $refA, $orderA['data']['razorpay_order_id'], $payA, $sigA);
$alertsAfterRetries = (int) $db->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'admin_payment_after_close'")->fetchColumn();
check($alertsAfterRetries === $alertsAfterFirst, 'L9 retrying the verify call does not send more alerts');

// A forged signature must not mark a real order failed (that would open a second payable order).
$holdC = $bookingSvc->createHold($uid, (int) $services->findBySlug('sauna')['id'], $lateDate, $g15[3], true, null);
if ($holdC['success']) {
    $orderC1 = $paySvc2->createOrder($uid, $holdC['data']['booking_reference']);
    $paySvc2->verifyPayment($uid, $holdC['data']['booking_reference'], $orderC1['data']['razorpay_order_id'], 'pay_junk', 'deadbeef');
    $orderC2 = $paySvc2->createOrder($uid, $holdC['data']['booking_reference']);
    check(($orderC2['data']['razorpay_order_id'] ?? '') === $orderC1['data']['razorpay_order_id'], 'L10 a junk signature does not cause a second order to be opened');
}

echo "\n--- Mock payments and host header (re-audit) ---\n";
check(payments_mock_allowed('cli') === true, 'E1 mock gateway is available to CLI tests in a local environment');
check(payments_mock_allowed('apache2handler', 'localhost:8080') === true, 'E2 mock gateway is allowed on localhost');
check(payments_mock_allowed('apache2handler', 'sarakineticsportslab.com') === false, 'E3 mock gateway is refused on a real domain even if APP_ENV was left as local');
check(payments_mock_allowed('apache2handler', 'evil.example.localhost.attacker.com') === false, 'E4 a lookalike host does not unlock the mock gateway');
$child = static function (array $env, string $code): string {
    $root = dirname(__DIR__);
    $p = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env + ['SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows']);
    $out = (string) stream_get_contents($pipes[1]);
    proc_close($p);
    return trim($out);
};
$dbEnv = ['DB_HOST' => '127.0.0.1', 'DB_DATABASE' => 'sksl', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => ''];
$o = $child(['APP_ENV' => 'Production', 'APP_URL' => 'http://localhost:9'] + $dbEnv, 'require "bootstrap.php"; $_SERVER["HTTP_HOST"]="evil.example"; echo config("app.env"), "|", app_base_url();');
check($o === 'production|http://localhost:9' && !str_contains($o, 'evil'), 'E5 production ignores the Host header even when APP_URL is a localhost value (got "' . $o . '")');
$o = $child(['APP_ENV' => 'prod', 'APP_URL' => 'https://sarakineticsportslab.com'] + $dbEnv, 'require "bootstrap.php"; echo config("app.env"), "|", app_base_url(), "|", payments_mock_allowed("apache2handler", "localhost") ? "mock" : "nomock";');
check($o === 'production|https://sarakineticsportslab.com|nomock', 'E6 an unknown APP_ENV value ("prod") is treated as production and mock payments stay off (got "' . $o . '")');

echo "\n--- Admin login hardening (re-audit) ---\n";
$adminModel = new \App\Models\AdminModel();
$adm = $adminModel->create('Audit Admin', "auditfix_admin_{$stamp}@sk-sports-lab.test", 'active');
$adminModel->createOtp($adm, password_hash('123456', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));
$otp = $adminModel->getLatestValidOtp($adm);
$spent = 0;
for ($i = 0; $i < 9; $i++) { if ($adminModel->reserveOtpAttempt((int) $otp['id'])) { $spent++; } }
check($spent === 5, 'A1 an OTP allows exactly 5 guesses however many requests arrive (got ' . $spent . ')');
$adminModel->createOtp($adm, password_hash('123456', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));
$otp2 = $adminModel->getLatestValidOtp($adm);
check($adminModel->consumeOtp((int) $otp2['id']) === true && $adminModel->consumeOtp((int) $otp2['id']) === false, 'A2 a correct code can be consumed once; a parallel replay is refused');

$sessionProbe = static function (int $adminId) use ($child, $dbEnv): string {
    return $child(['APP_ENV' => 'local'] + $dbEnv, 'require "bootstrap.php"; $_SESSION["admin_id"] = ' . $adminId . '; \App\Middleware\AdminAuth::handle(); echo "ALLOWED";');
};
check(str_contains($sessionProbe($adm), 'ALLOWED'), 'A3 an active admin session is accepted');
$db->exec("UPDATE admins SET status = 'inactive' WHERE id = {$adm}");
check(!str_contains($sessionProbe($adm), 'ALLOWED'), 'A4 an admin set to inactive loses access on the very next request');

$csrfProbe = static function (string $uri) use ($child, $dbEnv): string {
    return $child(['APP_ENV' => 'local'] + $dbEnv, 'require "bootstrap.php"; $_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["SCRIPT_NAME"]="/sksl/public/index.php"; $_SERVER["REQUEST_URI"]=' . var_export($uri, true) . '; $_POST=[]; \App\Middleware\CsrfMiddleware::handle(); echo "NOT-BLOCKED";');
};
check(str_contains($csrfProbe('/sksl/api/payment/webhook'), 'NOT-BLOCKED'), 'A5 the Razorpay webhook is CSRF-exempt when the app runs from a sub-folder (/sksl/...)');
check(str_contains($csrfProbe('/api/payment/webhook'), 'NOT-BLOCKED'), 'A6 ...and from the domain root');
check(!str_contains($csrfProbe('/sksl/api/payment/verify'), 'NOT-BLOCKED'), 'A7 other POST endpoints still require a CSRF token');
$db->exec("DELETE FROM admin_otps WHERE admin_id = {$adm}");
$db->exec("DELETE FROM admins WHERE id = {$adm}");

echo "\n--- Pending package cleanup ---\n";
$stale = $pkgSvc->createPurchaseOrder($uid2, (int) $pkg['id']);
$db->exec("UPDATE customer_packages SET created_at = NOW() - INTERVAL 3 DAY WHERE id = " . (int) $stale['data']['customer_package_id']);
check($cpModel->expireStalePending(1440) >= 1, 'H1.11 unpaid package purchases older than 24h are cancelled');

// ── Cleanup ──────────────────────────────────────────────────────────────
$db->exec("DELETE p FROM payments p JOIN customer_packages cp ON cp.id = p.customer_package_id WHERE cp.user_id IN ({$uid}, {$uid2})");
$db->exec("DELETE p FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.user_id IN ({$uid}, {$uid2})");
$db->exec("DELETE FROM email_logs WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ({$uid}, {$uid2}))");
$db->exec("DELETE FROM bookings WHERE user_id IN ({$uid}, {$uid2})");
$db->exec("DELETE FROM booking_holds WHERE user_id IN ({$uid}, {$uid2})");
$db->exec("DELETE FROM customer_packages WHERE user_id IN ({$uid}, {$uid2})");
$db->exec("DELETE FROM rate_limits WHERE rl_key LIKE 'professional_booking:%' OR rl_key LIKE 'package_purchase:%'");
$db->exec("DELETE FROM users WHERE id IN ({$uid}, {$uid2})");

echo "\n=== Phase 2 audit fixes: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
