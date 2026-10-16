<?php

/**
 * Money-flow scenarios beyond the happy path: price splits, wrong-user / wrong-order verification,
 * webhook oddities (amount mismatch, unknown order, ignored events, replays), a payment that arrives
 * after the slot was sold to someone else, and combo-package purchases end to end.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);
require_once __DIR__ . '/support/payment_support.php';

use App\Helpers\BookingRules;
use App\Models\BookingModel;
use App\Models\PaymentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingService;
use App\Services\PackageService;

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

$userModel    = new UserModel();
$serviceModel = new ServiceModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();
$bookingSvc   = new BookingService();
$paymentSvc   = sksl_test_payment_service();
$pkgSvc       = new PackageService($db, null, null, null, null, [
    'key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'mock' => true,
]);

$tag     = 'mf' . substr(bin2hex(random_bytes(3)), 0, 6);
$userIds = [];
$mkUser  = function (string $label) use ($userModel, $tag, &$userIds): int {
    $id = $userModel->create("Money $label", "{$tag}_{$label}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash('Pass1234!', PASSWORD_BCRYPT));
    $userIds[] = $id;
    return $id;
};
$pay = function (string $orderId, string $paymentId, int $paise, string $event = 'payment.captured') use ($paymentSvc): array {
    $body = json_encode(['event' => $event, 'payload' => ['payment' => ['entity' => [
        'id' => $paymentId, 'order_id' => $orderId, 'amount' => $paise, 'currency' => 'INR', 'status' => 'captured',
    ]]]]);
    return $paymentSvc->handleWebhook($body, sksl_test_sign_webhook($body));
};
$rows = fn (string $sql, array $args = []) => (function () use ($db, $sql, $args) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); })();

echo "SKSL — money-flow scenarios\n";

// ---------------------------------------------------------------- A. price splits
$services = array_filter($serviceModel->getAllActive('all'), fn ($s) => (float) $s['price'] > 0);
check('A0 catalogue has priced services', count($services) >= 8);
$splitOk = function (float $inclusive, string $label): void {
    $p = ServiceModel::calculatePricing($inclusive);
    check("A $label base+GST equals the listed price", abs($p['base_price'] + $p['gst_amount'] - $inclusive) < 0.001, json_encode($p));
    check("A $label GST is an even number of paise (CGST = SGST)", ((int) round($p['gst_amount'] * 100)) % 2 === 0, json_encode($p));
    check("A $label total is whole paise", abs($p['total_amount'] * 100 - round($p['total_amount'] * 100)) < 1e-6);
    check("A $label GST is below the total and not negative", $p['gst_amount'] >= 0 && $p['gst_amount'] < $p['total_amount']);
};
foreach ($services as $s) {
    $splitOk((float) $s['price'], $s['slug']);
    foreach ((new \App\Models\DurationOptionModel())->getByServiceId((int) $s['id']) as $opt) {
        $splitOk((float) $opt['price'], $s['slug'] . '@' . $opt['duration_minutes']);
    }
}
foreach ((new \App\Models\PackageModel())->getAll(true) as $pk) {
    $splitOk((float) $pk['price'], 'package ' . $pk['name']);
}
foreach ([0.01, 1.00, 99.99, 100.00, 175.00, 449.99, 1799.00, 99999.99] as $edge) {
    $splitOk($edge, "edge $edge");
}

// ---------------------------------------------------------------- B. booking payments
$grid = BookingRules::gridStarts(15);
$day  = date('Y-m-d', strtotime('+3 days'));
$cycle = $serviceModel->findBySlug('cycle');       // capacity 1: one buyer per slot
$sauna = $serviceModel->findBySlug('sauna');
$alice = $mkUser('alice');
$bob   = $mkUser('bob');
$carol = $mkUser('carol');

$mkOrder = function (int $uid, array $service, string $start) use ($bookingSvc, $paymentSvc, $day): array {
    $h = $bookingSvc->createHold($uid, (int) $service['id'], $day, $start);
    if (!$h['success']) { return ['ok' => false, 'msg' => $h['message']]; }
    $ref = $h['data']['booking_reference'];
    $o = $paymentSvc->createOrder($uid, $ref);
    return $o['success'] ? ['ok' => true, 'ref' => $ref, 'order' => $o['data']] : ['ok' => false, 'msg' => $o['message']];
};

// B1. verification must name the right customer and the right order
$a = $mkOrder($alice, $sauna, $grid[2]);
check('B1 setup hold+order', $a['ok'], $a['msg'] ?? '');
if ($a['ok']) {
    $oid = $a['order']['razorpay_order_id'];
    $pid = 'pay_' . bin2hex(random_bytes(5));
    $sig = sksl_test_sign($oid, $pid);
    $r = $paymentSvc->verifyPayment($bob, $a['ref'], $oid, $pid, $sig);
    check('B1a another customer cannot verify my booking', $r['success'] === false);
    $r = $paymentSvc->verifyPayment($alice, $a['ref'], 'order_other_' . bin2hex(random_bytes(3)), $pid, sksl_test_sign('order_other_x', $pid));
    check('B1b an order id that is not this booking is refused', $r['success'] === false);
    $r = $paymentSvc->verifyPayment($alice, $a['ref'], $oid, $pid, sksl_test_sign($oid, 'pay_someone_else'));
    check('B1c a signature made for a different payment id is refused', $r['success'] === false);
    $r = $paymentSvc->verifyPayment($alice, $a['ref'], $oid, '', $sig);
    check('B1d empty payment id is refused', $r['success'] === false);
    check('B1e booking is still pending after all the refusals', $bookingModel->findByReference($a['ref'])['booking_status'] === 'pending');
    check('B1f payment row is still pending (not failed, so no second order is opened)', $paymentModel->findByOrderId($oid)['status'] === 'pending');

    // B2. webhook with the wrong amount confirms nothing
    $r = $pay($oid, $pid, 100);
    check('B2a amount mismatch is acknowledged (200) but not confirmed', $r['code'] === 200);
    check('B2b booking stays pending after a mismatching webhook', $bookingModel->findByReference($a['ref'])['booking_status'] === 'pending');
    check('B2c payment stays pending after a mismatching webhook', $paymentModel->findByOrderId($oid)['status'] === 'pending');

    // B3. webhook with other event types is ignored
    $r = $pay($oid, $pid, $a['order']['amount_paise'], 'payment.failed');
    check('B3 non-capture events are ignored', $r['code'] === 200 && $bookingModel->findByReference($a['ref'])['booking_status'] === 'pending');

    // B4. webhook first, browser callback second, then webhook replayed: one confirmation, one paid row
    $r = $pay($oid, $pid, $a['order']['amount_paise']);
    check('B4a webhook confirms the booking', $r['code'] === 200 && $bookingModel->findByReference($a['ref'])['booking_status'] === 'confirmed');
    $r = $paymentSvc->verifyPayment($alice, $a['ref'], $oid, $pid, $sig);
    check('B4b the later browser callback is told it was already confirmed', $r['success'] === true && !empty($r['data']['already_confirmed']));
    $r = $pay($oid, $pid, $a['order']['amount_paise'], 'order.paid');
    check('B4c a replayed webhook is harmless', $r['code'] === 200);
    $paidRows = $rows("SELECT COUNT(*) c FROM payments WHERE razorpay_order_id = ? AND status = 'paid'", [$oid]);
    check('B4d exactly one paid payment row', (int) $paidRows[0]['c'] === 1);
    $pe = $paymentModel->findByOrderId($oid);
    check('B4e payment amount equals the booking total', abs((float) $pe['amount'] - (float) $bookingModel->findByReference($a['ref'])['total_amount']) < 0.001);
}

// B5. webhook for an unknown order is acknowledged and does nothing
$r = $pay('order_does_not_exist_' . bin2hex(random_bytes(3)), 'pay_x' . bin2hex(random_bytes(3)), 12345);
check('B5 unknown order is acknowledged', $r['code'] === 200);

// B6. webhook with a broken body
$r = $paymentSvc->handleWebhook('not json', sksl_test_sign_webhook('not json'));
check('B6a signed non-JSON body is rejected', $r['code'] === 400);
$body = json_encode(['event' => 'payment.captured', 'payload' => []]);
$r = $paymentSvc->handleWebhook($body, sksl_test_sign_webhook($body));
check('B6b captured event with no payment entity is acknowledged', $r['code'] === 200);

// B7. payment arrives after the hold expired and another customer bought the only slot
$slot = $grid[6];
$bobOrder = $mkOrder($bob, $cycle, $slot);
check('B7 setup first hold+order', $bobOrder['ok'], $bobOrder['msg'] ?? '');
if ($bobOrder['ok']) {
    $db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$bobOrder['ref']]); // hold lapsed
    $carolOrder = $mkOrder($carol, $cycle, $slot);
    check('B7a the lapsed slot can be held by someone else', $carolOrder['ok'], $carolOrder['msg'] ?? '');
    if ($carolOrder['ok']) {
        $cpid = 'pay_' . bin2hex(random_bytes(5));
        $r = $paymentSvc->verifyPayment($carol, $carolOrder['ref'], $carolOrder['order']['razorpay_order_id'], $cpid, sksl_test_sign($carolOrder['order']['razorpay_order_id'], $cpid));
        check('B7b the second customer is confirmed', $r['success'] === true);
        $bpid = 'pay_' . bin2hex(random_bytes(5));
        $bOid = $bobOrder['order']['razorpay_order_id'];
        $r = $paymentSvc->verifyPayment($bob, $bobOrder['ref'], $bOid, $bpid, sksl_test_sign($bOid, $bpid));
        check('B7c the late payer is told to contact SKSL, not confirmed', $r['success'] === false && str_contains($r['message'], $bpid), $r['message']);
        $bb = $bookingModel->findByReference($bobOrder['ref']);
        check('B7d the late booking is closed (not confirmed)', $bb['booking_status'] !== 'confirmed', $bb['booking_status']);
        check('B7e the money is recorded and flagged for refund', $bb['payment_status'] === 'paid' && str_contains((string) $bb['notes'], 'Refund'), json_encode([$bb['payment_status'], $bb['notes']]));
        $conf = (int) $rows("SELECT COUNT(*) c FROM bookings WHERE service_id = ? AND booking_date = ? AND start_time = ? AND booking_status = 'confirmed' AND user_id IN (?, ?)", [(int) $cycle['id'], $day, $slot . ':00', $bob, $carol])[0]['c'];
        check('B7f the capacity-1 slot has exactly one confirmed booking', $conf === 1, (string) $conf);
    }
}

// ---------------------------------------------------------------- C. combo packages
$dave = $mkUser('dave');
$erin = $mkUser('erin');
$pkgs = array_values(array_filter((new \App\Models\PackageModel())->getAll(true), fn ($p) => (float) $p['price'] > 0));
check('C0 there are active packages', count($pkgs) >= 1);
foreach ($pkgs as $pk) {
    $o = $pkgSvc->createPurchaseOrder($dave, (int) $pk['id']);
    check("C1 order opens for '{$pk['name']}'", $o['success'], $o['message'] ?? '');
    if (!$o['success']) { continue; }
    $d = $o['data'];
    check("C1a amount is exactly the package price ({$pk['name']})", $d['amount_paise'] === (int) round((float) $pk['price'] * 100), (string) $d['amount_paise']);
    $o2 = $pkgSvc->createPurchaseOrder($dave, (int) $pk['id']);
    check("C1b clicking Buy twice reuses the same order ({$pk['name']})", $o2['success'] && $o2['data']['order_id'] === $d['order_id']);

    $pid = 'pay_pkg_' . bin2hex(random_bytes(4));
    $r = $pkgSvc->verifyPurchase($dave, $d['order_id'], $pid, 'forged');
    check("C2a forged signature refused ({$pk['name']})", $r['success'] === false);
    $good = hash_hmac('sha256', $d['order_id'] . '|' . $pid, SKSL_TEST_KEY_SECRET);
    $r = $pkgSvc->verifyPurchase($erin, $d['order_id'], $pid, $good);
    check("C2b another customer cannot claim the purchase ({$pk['name']})", $r['success'] === false);
    check("C2c still unpaid after refusals ({$pk['name']})", $paymentModel->findByOrderId($d['order_id'])['status'] === 'pending');

    $badPay = $pay($d['order_id'], $pid, 1);
    check("C3 wrong-amount webhook confirms nothing ({$pk['name']})", $badPay['code'] === 200 && $paymentModel->findByOrderId($d['order_id'])['status'] === 'pending');

    $r = $pkgSvc->verifyPurchase($dave, $d['order_id'], $pid, $good);
    check("C4a correct signature settles ({$pk['name']})", $r['success'] === true, $r['message'] ?? '');
    $cp = $rows('SELECT * FROM customer_packages WHERE id = ?', [$d['customer_package_id']])[0];
    check("C4b purchase is paid and active ({$pk['name']})", $cp['payment_status'] === 'paid' && $cp['status'] === 'active');
    check("C4c expiry is the end of day, {$pk['validity_days']} days out", substr((string) $cp['expires_at'], 0, 10) === date('Y-m-d', strtotime('+' . (int) $pk['validity_days'] . ' days')) && str_ends_with((string) $cp['expires_at'], '23:59:59'), (string) $cp['expires_at']);
    $credits = $rows('SELECT service_id, duration_minutes, allocated_quantity, used_quantity, remaining_quantity FROM customer_package_credits WHERE customer_package_id = ? ORDER BY service_id', [$d['customer_package_id']]);
    $items   = $rows('SELECT service_id, duration_minutes, quantity FROM package_items WHERE package_id = ? ORDER BY service_id', [(int) $pk['id']]);
    check("C4d one credit per package item with the item's minutes ({$pk['name']})", count($credits) === count($items)
        && array_map(fn ($c) => [(int) $c['service_id'], (int) $c['duration_minutes'], (int) $c['allocated_quantity']], $credits) === array_map(fn ($i) => [(int) $i['service_id'], (int) $i['duration_minutes'], (int) $i['quantity']], $items), json_encode([$credits, $items]));
    check("C4e nothing used yet ({$pk['name']})", array_sum(array_column($credits, 'used_quantity')) === 0);

    // replays must not double the credits
    $pkgSvc->verifyPurchase($dave, $d['order_id'], $pid, $good);
    $pay($d['order_id'], $pid, $d['amount_paise']);
    $again = (int) $rows('SELECT SUM(allocated_quantity) s FROM customer_package_credits WHERE customer_package_id = ?', [$d['customer_package_id']])[0]['s'];
    check("C5 replays do not duplicate credits ({$pk['name']})", $again === array_sum(array_column($credits, 'allocated_quantity')), (string) $again);
}

// C6. webhook-first settlement of a package
if ($pkgs) {
    $o = $pkgSvc->createPurchaseOrder($erin, (int) $pkgs[0]['id']);
    $d = $o['data'] ?? [];
    $r = $pay($d['order_id'] ?? 'x', 'pay_pkgwh_' . bin2hex(random_bytes(4)), (int) ($d['amount_paise'] ?? 0));
    $cp = $rows('SELECT payment_status FROM customer_packages WHERE id = ?', [(int) ($d['customer_package_id'] ?? 0)]);
    check('C6 a captured webhook alone settles a package purchase', $r['code'] === 200 && ($cp[0]['payment_status'] ?? '') === 'paid');
}

// C7. an inactive / unknown package cannot be bought
$inactive = $rows("SELECT id FROM packages WHERE status <> 'active' LIMIT 1");
if ($inactive) {
    check('C7a inactive package refused', $pkgSvc->createPurchaseOrder($erin, (int) $inactive[0]['id'])['success'] === false);
}
check('C7b unknown package refused', $pkgSvc->createPurchaseOrder($erin, 999999)['success'] === false);
check('C7c unknown user refused', $pkgSvc->createPurchaseOrder(999999999, (int) $pkgs[0]['id'])['success'] === false);

// ---------------------------------------------------------------- cleanup
if ($userIds) {
    $in = implode(',', array_map('intval', $userIds));
    $db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in)) OR customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
    $db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
    $db->exec("DELETE FROM customer_packages WHERE user_id IN ($in)");
    $db->exec("DELETE FROM booking_holds WHERE user_id IN ($in)");
    $db->exec("DELETE FROM bookings WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}

echo "=== Money flows: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
