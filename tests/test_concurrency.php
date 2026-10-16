<?php
/**
 * Concurrency regression suite.
 *
 * Fires pairs of booking requests from separate PHP processes at the same instant and checks that
 * the locks hold:
 *   1. one athlete, two different services, overlapping times  -> exactly one hold is created
 *   2. two athletes, one capacity-1 slot                       -> exactly one hold is created
 *   3. one athlete, per-customer hold cap of 1, two parallel requests -> exactly one hold is created
 *
 * Run: php tests/test_concurrency.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;

// Safety: this file spawns copies of itself as workers. Any unknown argument must stop here, so a
// worker can never fall through into the suite and spawn workers of its own.
if (isset($argv[1]) && !in_array($argv[1], ['child', 'child-login', 'child-order'], true)) {
    fwrite(STDERR, "Unknown mode '{$argv[1]}'\n");
    exit(2);
}

// ── Child mode: create a payment order for one hold at a shared start time ───────────────
if (($argv[1] ?? '') === 'child-order') {
    [, , $userId, $reference, $at] = $argv;
    while (microtime(true) < (float) $at) {
        usleep(200);
    }
    $gateway = ['key_id' => 'rzp_test_race', 'key_secret' => 'race-secret', 'webhook_secret' => 'race-hook', 'mock' => true];
    $res = (new \App\Services\PaymentService(null, null, null, null, null, null, $gateway))->createOrder((int) $userId, $reference);
    echo json_encode(['ok' => $res['success'], 'order' => $res['data']['razorpay_order_id'] ?? null, 'msg' => $res['message']]);
    exit(0);
}

// ── Child mode: one wrong-password login attempt at a shared start time ──────────────────
if (($argv[1] ?? '') === 'child-login') {
    [, , $email, $at] = $argv;
    while (microtime(true) < (float) $at) {
        usleep(200);
    }
    $res = (new \App\Services\AuthService())->login($email, 'definitely-wrong-password');
    echo json_encode(['msg' => $res['message']]);
    exit(0);
}

// ── Child mode: wait for the shared start time, then try to create one hold ──────────────
if (($argv[1] ?? '') === 'child') {
    [, , $userId, $serviceId, $date, $start, $at, $maxHolds] = $argv;
    $_ENV['MAX_ACTIVE_HOLDS_PER_USER'] = $maxHolds;
    $target = (float) $at;
    while (microtime(true) < $target) {
        usleep(200);
    }
    $res = (new BookingService())->createHold((int) $userId, (int) $serviceId, $date, $start, true, null);
    echo json_encode(['ok' => $res['success'], 'msg' => $res['message']]);
    exit(0);
}

$pass = 0;
$fail = 0;
function check(bool $ok, string $label): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

/** Run requests [userId, serviceId, date, start, maxHolds] in parallel; return decoded results. */
function race(array $jobs): array
{
    $at = microtime(true) + 2.0;
    $procs = [];
    foreach ($jobs as $i => [$uid, $sid, $date, $start, $maxHolds]) {
        $cmd = [PHP_BINARY, __FILE__, 'child', (string) $uid, (string) $sid, $date, $start, (string) $at, (string) $maxHolds];
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
    }
    $out = [];
    foreach ($procs as $i => $p) {
        $out[$i] = json_decode((string) stream_get_contents($GLOBALS['pipes'][$i][1] ?? $pipes[$i][1]), true) ?? ['ok' => false, 'msg' => 'child failed'];
        proc_close($p);
    }
    return $out;
}

$db       = getDb();
$users    = new UserModel();
$services = new ServiceModel();
$stamp    = substr(bin2hex(random_bytes(4)), 0, 8);
$uA = $users->create('Race A', "race_a_{$stamp}@sk-sports-lab.test", '9000000091', password_hash('Race@12345', PASSWORD_DEFAULT));
$uB = $users->create('Race B', "race_b_{$stamp}@sk-sports-lab.test", '9000000092', password_hash('Race@12345', PASSWORD_DEFAULT));
$date = TimeHelper::now()->modify('+18 days')->format('Y-m-d');

$sauna = (int) $services->findBySlug('sauna')['id'];
$steam = (int) $services->findBySlug('steam')['id'];
$lap   = (int) $services->findBySlug('lap-pool')['id'];
$g15   = BookingRules::gridStarts(15);

function countHolds(PDO $db, array $userIds): int
{
    return (int) $db->query('SELECT COUNT(*) FROM booking_holds WHERE status = "active" AND user_id IN (' . implode(',', $userIds) . ')')->fetchColumn();
}

for ($round = 1; $round <= 3; $round++) {
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ({$uA}, {$uB})");

    // 1. same athlete, two services, identical start time (15-min grids coincide)
    $start = $g15[4 + $round];
    $r = race([[$uA, $sauna, $date, $start, 3], [$uA, $steam, $date, $start, 3]]);
    $won = count(array_filter($r, static fn ($x) => $x['ok']));
    check($won === 1 && countHolds($db, [$uA]) === 1, "R{$round}.1 one athlete cannot hold two overlapping services at once (succeeded: {$won})");
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ({$uA}, {$uB})");

    // 2. two athletes, capacity-1 slot
    $lapStart = BookingRules::gridStarts(45)[2 + $round];
    $r = race([[$uA, $lap, $date, $lapStart, 3], [$uB, $lap, $date, $lapStart, 3]]);
    $won = count(array_filter($r, static fn ($x) => $x['ok']));
    check($won === 1 && countHolds($db, [$uA, $uB]) === 1, "R{$round}.2 two athletes cannot both take the last capacity (succeeded: {$won})");
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ({$uA}, {$uB})");

    // 3. per-customer hold cap of 1, two parallel requests at different times
    $r = race([[$uA, $sauna, $date, $g15[10 + $round], 1], [$uA, $steam, $date, $g15[20 + $round], 1]]);
    $won = count(array_filter($r, static fn ($x) => $x['ok']));
    check($won === 1 && countHolds($db, [$uA]) === 1, "R{$round}.3 the per-customer hold cap cannot be beaten by parallel requests (succeeded: {$won})");
}

// 4. a burst of parallel wrong-password logins for one account must hit the lockout after 5
$loginEmail = "race_a_{$stamp}@sk-sports-lab.test";
\App\Helpers\RateLimit::clearScope('cust_login');
\App\Helpers\RateLimit::clearScope('cust_login_ip');
$at = microtime(true) + 2.5;
$procs = [];
$pipesAll = [];
for ($i = 0; $i < 12; $i++) {
    $procs[$i] = proc_open([PHP_BINARY, __FILE__, 'child-login', $loginEmail, (string) $at], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesAll[$i]);
}
$locked = 0; $wrong = 0;
foreach ($procs as $i => $p) {
    $r = json_decode((string) stream_get_contents($pipesAll[$i][1]), true);
    proc_close($p);
    if (str_contains((string) ($r['msg'] ?? ''), 'Too many')) { $locked++; } else { $wrong++; }
}
check($wrong <= 5 && $locked >= 7, "R4 12 parallel wrong-password logins: at most 5 are evaluated, the rest are locked out (evaluated {$wrong}, locked {$locked})");
\App\Helpers\RateLimit::clearScope('cust_login');
\App\Helpers\RateLimit::clearScope('cust_login_ip');

// 5. a double click on "pay": two simultaneous order requests for one hold -> one booking, one order
$holdRes = (new BookingService())->createHold($uB, $sauna, $date, $g15[30], true, null);
if ($holdRes['success']) {
    $ref = $holdRes['data']['booking_reference'];
    $at = microtime(true) + 2.5;
    $procs = [];
    $pipesOrd = [];
    for ($i = 0; $i < 4; $i++) {
        $procs[$i] = proc_open([PHP_BINARY, __FILE__, 'child-order', (string) $uB, $ref, (string) $at], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesOrd[$i]);
    }
    $orders = [];
    foreach ($procs as $i => $p) {
        $r = json_decode((string) stream_get_contents($pipesOrd[$i][1]), true);
        proc_close($p);
        if (!empty($r['order'])) { $orders[] = $r['order']; }
    }
    $bookingRows = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE booking_reference = " . $db->quote($ref))->fetchColumn();
    $paymentRows = (int) $db->query("SELECT COUNT(*) FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.booking_reference = " . $db->quote($ref))->fetchColumn();
    check($bookingRows === 1 && $paymentRows === 1 && count(array_unique($orders)) === 1 && count($orders) === 4, "R5 4 simultaneous order requests for one hold create 1 booking and 1 payment order, all callers get the same order (bookings {$bookingRows}, payments {$paymentRows}, distinct orders " . count(array_unique($orders)) . ')');
    $db->exec("DELETE p FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.booking_reference = " . $db->quote($ref));
    $db->exec("DELETE FROM bookings WHERE booking_reference = " . $db->quote($ref));
} else {
    check(false, 'R5 could not create the hold for the order race: ' . $holdRes['message']);
}

$db->exec("DELETE FROM booking_holds WHERE user_id IN ({$uA}, {$uB})");
$db->exec("DELETE FROM users WHERE id IN ({$uA}, {$uB})");

echo "\n=== Concurrency: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
