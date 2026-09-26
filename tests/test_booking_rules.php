<?php
/**
 * Audit "Booking rules disagree with each other" — regression suite.
 *
 * Covers App\Helpers\BookingRules and its use by BookingService,
 * AvailabilityService, BookingModel::findByUser() and expireStalePending():
 *   1. one advance-booking window for picker / availability / hold
 *   2. holds must sit on the published slot grid (duration + buffer)
 *   3. same-day cutoff lead time
 *   4. an athlete cannot hold/book two overlapping sessions of ANY modality
 *   5. per-customer cap on simultaneous unpaid holds
 *   6. "upcoming" excludes sessions that already ended today
 *   7. abandoned pending bookings are expired (cron + admin page housekeeping)
 *
 * Run: php tests/test_booking_rules.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;
use App\Models\BookingHoldModel;
use App\Models\BookingModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\AvailabilityService;
use App\Services\BookingService;

$pass = 0; $fail = 0;
function check(bool $ok, string $label): void {
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

$db           = getDb();
$users        = new UserModel();
$services     = new ServiceModel();
$bookings     = new BookingModel();
$holds        = new BookingHoldModel();
$bookingSvc   = new BookingService();
$availability = new AvailabilityService();

// Deterministic rule values for this run (restored implicitly — CLI process only)
$_ENV['ADVANCE_BOOKING_DAYS']      = '14';
$_ENV['SAME_DAY_CUTOFF_MINUTES']   = '90';
$_ENV['CHECKIN_BUFFER_MINUTES']    = '5';
$_ENV['CHECKOUT_BUFFER_MINUTES']   = '5';
$_ENV['BUFFER_MINUTES']            = '0';
$_ENV['MAX_ACTIVE_HOLDS_PER_USER'] = '2';

$stamp = time();
$uid = $users->create('Rules Tester', "rules_{$stamp}@sk-sports-lab.test", '9000000001', password_hash('Rules@123', PASSWORD_DEFAULT));
$cleanupRefs = [];

$lapPool = $services->findBySlug('lap-pool');   // 45 min, capacity 1
$iceBath = $services->findBySlug('ice-bath');   // 10 min
$spa     = $services->findBySlug('spa');        // 30 min
check($lapPool && $iceBath && $spa, '0. Seed services present (lap-pool, ice-bath, spa)');

// Use a date that is free of other test data: +10 days (inside the 14-day window)
$date = TimeHelper::now()->modify('+10 days')->format('Y-m-d');

echo "\n--- 1. Advance window is one shared rule ---\n";
check(BookingRules::advanceDays() === 14, '1.1 ADVANCE_BOOKING_DAYS read by BookingRules (14)');
check(BookingRules::maxDate() === TimeHelper::now()->modify('+14 days')->format('Y-m-d'), '1.2 maxDate() = today + 14');
$farDate = TimeHelper::now()->modify('+15 days')->format('Y-m-d');
$availFar = $availability->getAvailableSlots((int) $spa['id'], $farDate);
check($availFar['success'] === false && str_contains($availFar['message'], '14 days'), '1.3 availability API rejects day 15');
$holdFar = $bookingSvc->createHold($uid, (int) $spa['id'], $farDate, '06:00');
check($holdFar['success'] === false && str_contains($holdFar['message'], '14 days'), '1.4 hold endpoint rejects day 15 with the same message');
$availOk = $availability->getAvailableSlots((int) $spa['id'], $date);
check($availOk['success'] === true && ($availOk['rules']['advance_booking_days'] ?? 0) === 14, '1.5 availability payload exposes the rule set');

echo "\n--- 2. Grid alignment ---\n";
$grid45 = BookingRules::gridStarts(45);
check($grid45[0] === '06:00' && $grid45[1] === '06:55', '2.1 45-min + 10-min buffer grid steps by 55 (06:00, 06:55, …)');
check(end($grid45) <= '21:15', '2.2 last 45-min slot ends by 22:00');
$slotTimes = array_column($availOk['slots'], 'start_time');
check($slotTimes === BookingRules::gridStarts((int) $spa['duration_minutes']), '2.3 availability slots are exactly BookingRules::gridStarts()');
$offGrid = $bookingSvc->createHold($uid, (int) $lapPool['id'], $date, '06:30');
check($offGrid['success'] === false && str_contains($offGrid['message'], 'not an available slot'), '2.4 off-grid start time (06:30 for lap pool) is refused');
$onGrid = $bookingSvc->createHold($uid, (int) $lapPool['id'], $date, '06:55');
check($onGrid['success'] === true, '2.5 on-grid start time (06:55) is accepted');
if ($onGrid['success']) { $cleanupRefs[] = $onGrid['data']['booking_reference']; }

echo "\n--- 3. Same-day cutoff ---\n";
check(BookingRules::sameDayCutoffMinutes() === 90, '3.1 SAME_DAY_CUTOFF_MINUTES read (90)');
$today = TimeHelper::today();
$nowPlus30  = TimeHelper::now()->modify('+30 minutes')->format('H:i');
$nowPlus120 = TimeHelper::now()->modify('+120 minutes')->format('H:i');
check(BookingRules::isTooLate($today, $nowPlus30) === true, '3.2 a slot 30 min from now is inside the 90-min cutoff');
check(BookingRules::isTooLate($today, $nowPlus120) === false || $nowPlus120 < $nowPlus30, '3.3 a slot 120 min from now is bookable (unless past midnight)');
check(BookingRules::isTooLate($date, '06:00') === false, '3.4 cutoff never applies to future dates');
$todayAvail = $availability->getAvailableSlots((int) $iceBath['id'], $today);
if ($todayAvail['success']) {
    $cutoffStart = BookingRules::earliestStartToday();
    $wrong = array_filter($todayAvail['slots'], static fn ($s) => $s['start_time'] <= $cutoffStart && $s['available']);
    check($wrong === [], '3.5 today\'s availability marks every slot inside the cutoff unavailable');
    $firstInside = null;
    foreach ($todayAvail['slots'] as $s) { if ($s['start_time'] <= $cutoffStart) { $firstInside = $s['start_time']; } }
    if ($firstInside !== null) {
        $late = $bookingSvc->createHold($uid, (int) $iceBath['id'], $today, $firstInside);
        check($late['success'] === false && (str_contains($late['message'], '90 minutes') || str_contains($late['message'], 'passed')), '3.6 hold inside the cutoff is refused with a lead-time message');
    } else {
        echo "[SKIP] 3.6 no slot inside cutoff at this hour\n";
    }
} else {
    echo "[SKIP] 3.5/3.6 facility closed today\n";
}

echo "\n--- 4. One athlete, one session at a time (cross-modality) ---\n";
// Lap pool 06:55–07:40 is held above. Ice bath grid: 06:00, 06:20, 06:40, 07:00 … → 07:00 overlaps.
$overlap = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '07:00');
check($overlap['success'] === false && str_contains($overlap['message'], 'active payment hold'), '4.1 hold on another modality overlapping own hold is refused');
$noOverlap = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '08:00');
check($noOverlap['success'] === true, '4.2 non-overlapping slot on another modality is accepted');
if ($noOverlap['success']) { $cleanupRefs[] = $noOverlap['data']['booking_reference']; }

// Confirmed booking overlap: insert a confirmed spa booking 10:00–10:30 then ask availability for ice bath
$confirmedRef = 'SKSL-RULES-' . strtoupper(substr(md5((string) $stamp), 0, 6));
$bookingId = $bookings->create([
    'booking_reference' => $confirmedRef, 'user_id' => $uid, 'service_id' => (int) $spa['id'],
    'booking_date' => $date, 'start_time' => '10:00:00', 'end_time' => '10:30:00',
    'service_duration_minutes' => 30, 'base_amount' => 100, 'gst_percent' => 18, 'gst_amount' => 18, 'total_amount' => 118,
    'booking_status' => 'confirmed', 'payment_status' => 'paid',
]);
$iceAvail = $availability->getAvailableSlots((int) $iceBath['id'], $date, $uid);
$s1020 = null;
foreach ($iceAvail['slots'] as $s) { if ($s['start_time'] === '10:20') { $s1020 = $s; } }
check($s1020 !== null && $s1020['available'] === false && $s1020['unavailable_reason'] === 'user_conflict', '4.3 availability flags user_conflict on another modality during a confirmed booking');
// (free one hold slot first so the refusal below is about the overlap, not the cap tested in section 5)
$bookingSvc->releaseHold($uid, $cleanupRefs[1]);
$holdDuring = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '10:20');
check($holdDuring['success'] === false && str_contains($holdDuring['message'], 'confirmed booking'), '4.4 hold during own confirmed booking (other modality) is refused');
$reHold = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '08:00');
if ($reHold['success']) { $cleanupRefs[1] = $reHold['data']['booking_reference']; }

echo "\n--- 5. Per-customer active hold cap ---\n";
// user has 2 active holds now (lap pool 06:55, ice bath 08:00); cap is 2
check($holds->countActiveForUser($uid) === 2, '5.1 countActiveForUser() = 2');
$third = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '12:00');
check($third['success'] === false && str_contains($third['message'], '2 slots awaiting payment'), '5.2 third simultaneous hold is refused');
$bookingSvc->releaseHold($uid, $cleanupRefs[1]);
$afterRelease = $bookingSvc->createHold($uid, (int) $iceBath['id'], $date, '12:00');
check($afterRelease['success'] === true, '5.3 releasing one hold frees the cap');
if ($afterRelease['success']) { $cleanupRefs[] = $afterRelease['data']['booking_reference']; }

echo "\n--- 6. Upcoming vs completed classification ---\n";
$pastRef = 'SKSL-RULESP-' . strtoupper(substr(md5((string) ($stamp + 1)), 0, 5));
$endedToday = TimeHelper::now()->modify('-1 hour');
$pastId = $bookings->create([
    'booking_reference' => $pastRef, 'user_id' => $uid, 'service_id' => (int) $spa['id'],
    'booking_date' => $today, 'start_time' => $endedToday->modify('-30 minutes')->format('H:i:s'), 'end_time' => $endedToday->format('H:i:s'),
    'service_duration_minutes' => 30, 'base_amount' => 100, 'gst_percent' => 18, 'gst_amount' => 18, 'total_amount' => 118,
    'booking_status' => 'confirmed', 'payment_status' => 'paid',
]);
$upcomingRefs  = array_column($bookings->findByUser($uid, 'upcoming'), 'booking_reference');
$completedRefs = array_column($bookings->findByUser($uid, 'completed'), 'booking_reference');
check(!in_array($pastRef, $upcomingRefs, true), '6.1 a confirmed session that ended an hour ago is NOT upcoming');
check(in_array($pastRef, $completedRefs, true), '6.2 …and is listed under completed');
check(in_array($confirmedRef, $upcomingRefs, true), '6.3 a confirmed session 10 days out IS upcoming');

echo "\n--- 7. Abandoned pending bookings expire ---\n";
$pendRef = 'SKSL-RULESX-' . strtoupper(substr(md5((string) ($stamp + 2)), 0, 5));
$pendId = $bookings->create([
    'booking_reference' => $pendRef, 'user_id' => $uid, 'service_id' => (int) $spa['id'],
    'booking_date' => $date, 'start_time' => '14:00:00', 'end_time' => '14:30:00',
    'service_duration_minutes' => 30, 'base_amount' => 100, 'gst_percent' => 18, 'gst_amount' => 18, 'total_amount' => 118,
    'booking_status' => 'pending', 'payment_status' => 'pending',
]);
$freshCount = $bookings->expireStalePending();
$stillPending = $db->query("SELECT booking_status FROM bookings WHERE id = {$pendId}")->fetchColumn();
check($stillPending === 'pending', '7.1 a brand-new pending booking is left alone');
$db->exec("UPDATE bookings SET created_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = {$pendId}");
$expired = $bookings->expireStalePending();
$nowStatus = $db->query("SELECT booking_status FROM bookings WHERE id = {$pendId}")->fetchColumn();
check($expired >= 1 && $nowStatus === 'expired', '7.2 pending booking older than the expiry window becomes expired');
check(BookingModel::checkAdminTransition(['booking_status' => 'expired', 'payment_status' => 'pending'], 'cancelled')['allowed'] === false, '7.3 expired is terminal for staff');
check(!in_array($pendRef, array_column($bookings->findByUser($uid), 'booking_reference'), true), '7.4 expired checkouts are hidden from the customer dashboard');
check($bookings->confirmIfPending($pendId) === false, '7.5 a late payment cannot confirm an expired booking (confirmIfPending returns false)');
ob_start();
require dirname(__DIR__) . '/cron/expire-holds.php';
$cron = ob_get_clean();
check(preg_match('/Expired \d+ stale hold\(s\) and \d+ unpaid pending booking\(s\)/', $cron) === 1, '7.6 cron job reports both holds and pending bookings');

// ── cleanup ──
foreach ($cleanupRefs as $ref) { $db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$ref]); }
$db->prepare('DELETE FROM booking_holds WHERE user_id = ?')->execute([$uid]);
$db->prepare('DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = ?)')->execute([$uid]);
$db->prepare('DELETE FROM bookings WHERE user_id = ?')->execute([$uid]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);

echo "\nResults: {$pass} Passed, {$fail} Failed\n";
if ($fail === 0) {
    echo ">>> ALL BOOKING RULES TESTS PASSED <<<\n";
} else {
    echo ">>> {$fail} TESTS FAILED <<<\n";
    exit(1);
}
