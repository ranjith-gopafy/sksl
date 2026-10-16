<?php

/**
 * What happens when something breaks: the mail server is unreachable, or the database fails half-way
 * through confirming a payment. The customer's money and booking must stay correct and consistent,
 * nothing may crash, and the problem must be recorded for staff.
 *
 * The mail server is simulated with a closed local port (nothing leaves this computer).
 */

declare(strict_types=1);

// Point SMTP at a port nobody listens on BEFORE the app loads its settings (.env never overrides these).
$_ENV['MAIL_DRIVER']        = 'smtp';
$_ENV['SMTP_HOST']          = '127.0.0.1';
$_ENV['SMTP_PORT']          = '1';
$_ENV['SMTP_ENCRYPTION']    = '';
$_ENV['MAIL_FROM_ADDRESS']  = 'no-reply@sk-sports-lab.test';
$_ENV['ADMIN_EMAIL']        = 'admin@sk-sports-lab.test';
putenv('SKSL_TEST_REAL_MAIL=1'); // keep the smtp driver; the "server" is a closed port

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);
require_once __DIR__ . '/support/payment_support.php';

use App\Helpers\BookingRules;
use App\Models\AdminModel;
use App\Models\BookingHoldModel;
use App\Models\BookingModel;
use App\Models\PackageModel;
use App\Models\PaymentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\AdminAuthService;
use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\EmailService;
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

/** A payment table that fails on demand (like a deadlock or a lost connection). */
class FlakyPaymentModel extends PaymentModel
{
    public bool $fail = true;
    public function markPaid(int $id, string $paymentId, string $signature, ?string $gatewayResponse = null): bool
    {
        if ($this->fail) { throw new \PDOException('simulated: lost connection during markPaid'); }
        return parent::markPaid($id, $paymentId, $signature, $gatewayResponse);
    }
}
/** A hold table that fails after the payment row was already written inside the transaction. */
class FlakyHoldModel extends BookingHoldModel
{
    public bool $fail = true;
    public function release(string $reference): bool
    {
        if ($this->fail) { throw new \PDOException('simulated: deadlock while releasing the hold'); }
        return parent::release($reference);
    }
}

$q = function (string $sql, array $a = []) use ($db): array { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };
$tag = 'fm' . substr(bin2hex(random_bytes(3)), 0, 6);
$userIds = [];
$mkUser = function (string $l) use ($tag, &$userIds): int {
    $id = (new UserModel())->create("Fail $l", "{$tag}_{$l}@sk-sports-lab.test", '98765' . random_int(10000, 99999), password_hash('Pass1234!x', PASSWORD_BCRYPT));
    $userIds[] = $id;
    return $id;
};
$svcModel = new ServiceModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();
$bookingSvc = new BookingService();
$grid = BookingRules::gridStarts(15);
$day = date('Y-m-d', strtotime('+5 days'));
$sauna = $svcModel->findBySlug('sauna');
$slot = 4;
$timed = function (callable $fn): array { $t = microtime(true); $r = $fn(); return [$r, microtime(true) - $t]; };

echo "SKSL — failure modes\n";

// ---------------------------------------------------------------- A. the mail server is down
$mail = new EmailService();
[$ok, $secs] = $timed(fn () => $mail->send("{$tag}_probe@sk-sports-lab.test", 'Probe', 'Probe', '<p>x</p>', 'x', null, null, 'failure_probe'));
check('A1 a send to an unreachable mail server returns false (no exception)', $ok === false);
check('A2 …quickly (under 10 seconds)', $secs < 10, round($secs, 1) . 's');
$logs = (new \App\Models\EmailLogModel())->findByRecipient("{$tag}_probe@sk-sports-lab.test", 'failure_probe', 5);
check('A3 …and the failure is recorded in the email log', count($logs) === 1 && $logs[0]['status'] === 'failed', json_encode($logs));
check('A4 the recorded error does not contain the message body', !str_contains((string) ($logs[0]['error_message'] ?? ''), '<p>x</p>'));

$paySvc = sksl_test_payment_service();
$u = $mkUser('pay');
$hold = $bookingSvc->createHold($u, (int) $sauna['id'], $day, $grid[$slot]);
check('A5 setup: a hold is created', $hold['success'] === true, $hold['message'] ?? '');
$ref = $hold['data']['booking_reference'];
$order = $paySvc->createOrder($u, $ref)['data'];
$pid = 'pay_' . bin2hex(random_bytes(5));
[$res, $secs] = $timed(fn () => $paySvc->verifyPayment($u, $ref, $order['razorpay_order_id'], $pid, sksl_test_sign($order['razorpay_order_id'], $pid)));
check('A6 a paid booking is confirmed even though every email fails', $res['success'] === true, json_encode($res));
$b = $bookingModel->findByReference($ref);
check('A7 booking confirmed, payment paid', $b['booking_status'] === 'confirmed' && $b['payment_status'] === 'paid');
check('A8 the hold was released', (new BookingHoldModel())->findActiveByReference($ref) === null);
check('A9 the confirmation email attempts are logged as failed (for staff to chase)', (int) $q("SELECT COUNT(*) c FROM email_logs WHERE booking_id = ? AND status = 'failed'", [(int) $b['id']])[0]['c'] >= 1);
check('A10 the whole confirmation finished in a sensible time', $secs < 30, round($secs, 1) . 's');

// webhook path
$u2 = $mkUser('hook');
$hold2 = $bookingSvc->createHold($u2, (int) $sauna['id'], $day, $grid[$slot + 3]);
$ref2 = $hold2['data']['booking_reference'];
$order2 = $paySvc->createOrder($u2, $ref2)['data'];
$body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_' . bin2hex(random_bytes(5)), 'order_id' => $order2['razorpay_order_id'], 'amount' => $order2['amount_paise'], 'currency' => 'INR', 'status' => 'captured']]]]);
$wr = $paySvc->handleWebhook($body, sksl_test_sign_webhook($body));
check('A11 the webhook confirms the booking and answers 200 although mail is down', $wr['code'] === 200 && $bookingModel->findByReference($ref2)['booking_status'] === 'confirmed', json_encode($wr));

// customer cancellation
$can = $bookingSvc->cancelByCustomer($u, $ref);   // session is >24 h away
check('A12 a customer cancellation succeeds although mail is down', $can['success'] === true, $can['message']);
check('A13 …with the refund amount recorded', (float) $bookingModel->findByReference($ref)['refund_amount'] > 0);

// password reset / registration / admin sign-in
$auth = new AuthService();
foreach (['cust_forgot', 'cust_forgot_ip', 'cust_register', 'cust_register_ip'] as $s) { \App\Helpers\RateLimit::clearScope($s); }
$known = $auth->forgotPassword("{$tag}_pay@sk-sports-lab.test");
$unknown = $auth->forgotPassword("{$tag}_ghost@sk-sports-lab.test");
check('A14 forgot-password answers the same for a known and an unknown account when mail is down', $known === $unknown && $known['success'] === true, json_encode([$known, $unknown]));
check('A15 …and the reset link was still created', (int) $q('SELECT COUNT(*) c FROM password_resets WHERE user_id = ?', [$u])[0]['c'] >= 1);
$dupe = $auth->register(['name' => 'Dup Person', 'email' => "{$tag}_pay@sk-sports-lab.test", 'mobile' => '9876543210', 'password' => 'Sprint!' . random_int(1000, 9999), 'password_confirmation' => '', 'terms' => '1']);
check('A16 registration with an existing e-mail does not crash when mail is down', is_array($dupe));
$admins = new AdminModel();
$adminId = $admins->create('Fail Admin', "{$tag}_adm@sk-sports-lab.test", 'active');
foreach (['admin_otp_req', 'admin_otp_req_ip'] as $s) { \App\Helpers\RateLimit::clearScope($s); }
$adm = new AdminAuthService();
$r1 = $adm->requestOtp("{$tag}_adm@sk-sports-lab.test");
$r2 = $adm->requestOtp("{$tag}_nobody@sk-sports-lab.test");
check('A17 admin sign-in code request does not crash when mail is down, and answers like an unknown e-mail', $r1['success'] === true && $r1['message'] === $r2['message'], json_encode([$r1, $r2]));
check('A18 the code was still stored (the admin can use it once mail recovers)', (int) $q('SELECT COUNT(*) c FROM admin_otps WHERE admin_id = ?', [$adminId])[0]['c'] === 1);

// combo package purchase
$pkg = (new PackageModel())->getAll(true)[0];
$u3 = $mkUser('pkg');
$pkgSvc = new PackageService($db, null, null, null, null, ['key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'mock' => true]);
$po = $pkgSvc->createPurchaseOrder($u3, (int) $pkg['id'])['data'];
$ppid = 'pay_pk_' . bin2hex(random_bytes(4));
$pr = $pkgSvc->verifyPurchase($u3, $po['order_id'], $ppid, hash_hmac('sha256', $po['order_id'] . '|' . $ppid, SKSL_TEST_KEY_SECRET));
check('A19 a package purchase is provisioned although mail is down', $pr['success'] === true, json_encode($pr));
check('A20 …credits exist', (int) $q('SELECT COUNT(*) c FROM customer_package_credits WHERE customer_package_id = ?', [$po['customer_package_id']])[0]['c'] >= 1);

// ---------------------------------------------------------------- B. the database fails while confirming a payment
$u4 = $mkUser('db1');
$h = $bookingSvc->createHold($u4, (int) $sauna['id'], $day, $grid[$slot + 6]);
$refB = $h['data']['booking_reference'];
$flaky = new FlakyPaymentModel();
$svc = new PaymentService(null, $flaky, null, null, null, null, ['key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'webhook_secret' => SKSL_TEST_WEBHOOK_SECRET, 'mock' => true]);
$ord = $svc->createOrder($u4, $refB)['data'];
$pidB = 'pay_' . bin2hex(random_bytes(5));
$sig = sksl_test_sign($ord['razorpay_order_id'], $pidB);
$res = $svc->verifyPayment($u4, $refB, $ord['razorpay_order_id'], $pidB, $sig);
check('B1 a database failure during confirmation is reported as a failure, not a crash', $res['success'] === false && str_contains($res['message'], $pidB), json_encode($res));
$bk = $bookingModel->findByReference($refB);
check('B2 nothing was half-done: booking still pending, payment still pending', $bk['booking_status'] === 'pending' && $paymentModel->findByOrderId($ord['razorpay_order_id'])['status'] === 'pending');
check('B3 the hold is still active so the customer can retry', (new BookingHoldModel())->findActiveByReference($refB) !== null);
$bodyB = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => $pidB, 'order_id' => $ord['razorpay_order_id'], 'amount' => $ord['amount_paise'], 'currency' => 'INR', 'status' => 'captured']]]]);
$wh = $svc->handleWebhook($bodyB, sksl_test_sign_webhook($bodyB));
check('B4 the webhook answers 500 so Razorpay retries later', $wh['code'] === 500, json_encode($wh));
$flaky->fail = false;
$wh2 = $svc->handleWebhook($bodyB, sksl_test_sign_webhook($bodyB));
check('B5 once the database recovers, the retried webhook confirms the booking', $wh2['code'] === 200 && $bookingModel->findByReference($refB)['booking_status'] === 'confirmed', json_encode($wh2));
$again = $svc->verifyPayment($u4, $refB, $ord['razorpay_order_id'], $pidB, $sig);
check('B6 the customer\'s own retry afterwards is told it is already confirmed', $again['success'] === true && !empty($again['data']['already_confirmed']));
check('B7 exactly one paid payment row', (int) $q("SELECT COUNT(*) c FROM payments WHERE razorpay_order_id = ? AND status = 'paid'", [$ord['razorpay_order_id']])[0]['c'] === 1);

// failure AFTER the payment row was written but before commit: everything rolls back together
$u5 = $mkUser('db2');
$h = $bookingSvc->createHold($u5, (int) $sauna['id'], $day, $grid[$slot + 9]);
$refC = $h['data']['booking_reference'];
$flakyHold = new FlakyHoldModel();
$svc2 = new PaymentService(null, null, null, $flakyHold, null, null, ['key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'webhook_secret' => SKSL_TEST_WEBHOOK_SECRET, 'mock' => true]);
$ordC = $svc2->createOrder($u5, $refC)['data'];
$pidC = 'pay_' . bin2hex(random_bytes(5));
$res = $svc2->verifyPayment($u5, $refC, $ordC['razorpay_order_id'], $pidC, sksl_test_sign($ordC['razorpay_order_id'], $pidC));
check('C1 a failure late in the confirmation is reported as a failure', $res['success'] === false, json_encode($res));
check('C2 the payment row was rolled back with it (still pending, no payment id stored)', ($p = $paymentModel->findByOrderId($ordC['razorpay_order_id']))['status'] === 'pending' && empty($p['razorpay_payment_id']), json_encode($p));
check('C3 the booking stayed pending', $bookingModel->findByReference($refC)['booking_status'] === 'pending');
$flakyHold->fail = false;
$res = $svc2->verifyPayment($u5, $refC, $ordC['razorpay_order_id'], $pidC, sksl_test_sign($ordC['razorpay_order_id'], $pidC));
check('C4 the retry then succeeds', $res['success'] === true && $bookingModel->findByReference($refC)['booking_status'] === 'confirmed', json_encode($res));

// ---------------------------------------------------------------- D. housekeeping job survives bad data
$u6 = $mkUser('cron');
$db->prepare("INSERT INTO booking_holds (booking_reference, user_id, service_id, booking_date, start_time, end_time, expires_at, status) VALUES (?, ?, ?, ?, '10:00:00', '10:15:00', DATE_SUB(NOW(), INTERVAL 20 MINUTE), 'active')")->execute(['SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))), $u6, (int) $sauna['id'], $day]);
ob_start();
$cronExit = 0;
try { include dirname(__DIR__) . '/cron/expire-holds.php'; } catch (\Throwable $e) { $cronExit = 1; }
$out = ob_get_clean();
check('D1 the expiry job runs through and reports what it did', $cronExit === 0 && str_contains($out, 'Expired'), substr($out, 0, 160));
check('D2 the stale hold was expired by it', (int) $q("SELECT COUNT(*) c FROM booking_holds WHERE user_id = ? AND status = 'active'", [$u6])[0]['c'] === 0);

// ---------------------------------------------------------------- cleanup
$in = implode(',', array_map('intval', $userIds));
$db->exec("DELETE FROM email_logs WHERE recipient LIKE '{$tag}%' OR booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in))");
$db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id IN ($in)) OR customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
$db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN ($in))");
$db->exec("DELETE FROM customer_packages WHERE user_id IN ($in)");
$db->exec("DELETE FROM booking_holds WHERE user_id IN ($in)");
$db->exec("DELETE FROM bookings WHERE user_id IN ($in)");
$db->exec("DELETE FROM password_resets WHERE user_id IN ($in)");
$db->exec("DELETE FROM users WHERE id IN ($in)");
$db->exec("DELETE FROM admin_otps WHERE admin_id = $adminId");
$db->exec("DELETE FROM admins WHERE id = $adminId");
foreach (['cust_forgot', 'cust_forgot_ip', 'cust_register', 'cust_register_ip', 'admin_otp_req', 'admin_otp_req_ip'] as $s) { \App\Helpers\RateLimit::clearScope($s); }

echo "=== Failure modes: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
