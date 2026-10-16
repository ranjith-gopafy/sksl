<?php
/**
 * Test helper for the browser (Playwright) suite: a throwaway customer with confirmed bookings.
 *
 *   php tests/support/customer_session.php create                         -> {"id":..,"email":..,"password":..}
 *   php tests/support/customer_session.php booking <userId> <hoursAhead> [paid|package|centre|offline]
 *                                                                          -> {"ref":..}
 *   php tests/support/customer_session.php status <ref>                   -> booking row as JSON
 *   php tests/support/customer_session.php cleanup <userId>               -> removes the customer and everything of theirs
 *
 * Command line only, and refuses to run when APP_ENV is production.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\TimeHelper;
use App\Models\BookingModel;
use App\Models\ServiceModel;

$db      = getDb();
$command = $argv[1] ?? '';

switch ($command) {
    case 'create':
        $email = 'e2e_cust_' . bin2hex(random_bytes(4)) . '@sk-sports-lab.test';
        $password = 'E2eCustomer!' . random_int(1000, 9999);
        $id = (new \App\Models\UserModel())->create('E2E Customer', $email, '98765' . random_int(10000, 99999), password_hash($password, PASSWORD_BCRYPT));
        echo json_encode(['id' => $id, 'email' => $email, 'password' => $password]);
        break;

    case 'booking':
        $userId = (int) ($argv[2] ?? 0);
        $hours  = (int) ($argv[3] ?? 48);
        $kind   = (string) ($argv[4] ?? 'paid');
        $svc    = (new ServiceModel())->findBySlug($kind === 'centre' ? 'physiotherapy' : 'sauna');
        $start  = TimeHelper::now()->modify("+$hours hours");
        $start  = $start->setTime((int) $start->format('H'), (int) $start->format('i'), 0);
        $end    = $start->modify('+15 minutes');
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) { $end = $start->setTime(23, 59, 59); }
        $ref    = 'SKSL-' . $start->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $split  = ServiceModel::calculatePricing(599.00);
        $creditId = null;
        if ($kind === 'package') {
            $svc = (new ServiceModel())->findBySlug('cycle');
            $db->prepare("INSERT INTO customer_packages (user_id, package_id, purchase_reference, package_name_snapshot, validity_days_snapshot, base_amount, gst_percent, gst_amount, total_amount, payment_status, purchased_at, expires_at, status) VALUES (?, 8, ?, 'E2E', 30, 100, 18, 18, 118, 'paid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')")
               ->execute([$userId, 'SKSL-PKG-' . strtoupper(bin2hex(random_bytes(3)))]);
            $cp = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO customer_package_credits (customer_package_id, service_id, duration_minutes, allocated_quantity, used_quantity, remaining_quantity) VALUES (?, ?, 15, 1, 1, 0)')
               ->execute([$cp, (int) $svc['id']]);
            $creditId = (int) $db->lastInsertId();
        }
        (new BookingModel())->create([
            'booking_reference' => $ref, 'user_id' => $userId, 'service_id' => (int) $svc['id'],
            'booking_type' => $kind === 'package' ? 'package' : ($kind === 'centre' ? 'professional' : 'standard'),
            'payment_mode' => in_array($kind, ['paid', 'offline'], true) ? 'payment_required' : ($kind === 'package' ? 'package_credit' : 'payment_not_required'),
            'payment_channel' => $kind === 'offline' ? 'offline' : 'online',
            'payment_due_at' => $kind === 'offline' ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null,
            'package_credit_id' => $creditId,
            'booking_date' => $start->format('Y-m-d'), 'start_time' => $start->format('H:i:s'), 'end_time' => $end->format('H:i:s'),
            'service_name_snapshot' => $svc['name'], 'service_duration_minutes' => 15,
            'base_amount' => in_array($kind, ['paid', 'offline'], true) ? $split['base_price'] : 0, 'gst_amount' => in_array($kind, ['paid', 'offline'], true) ? $split['gst_amount'] : 0,
            'total_amount' => in_array($kind, ['paid', 'offline'], true) ? $split['total_amount'] : 0,
            'booking_status' => $kind === 'offline' ? 'pending' : 'confirmed', 'payment_status' => in_array($kind, ['centre', 'offline'], true) ? 'pending' : 'paid',
        ]);
        echo json_encode(['ref' => $ref]);
        break;

    case 'status':
        $s = $db->prepare('SELECT booking_status, payment_status, payment_channel, payment_method, payment_reference, cancelled_by, cancellation_fee, refund_amount FROM bookings WHERE booking_reference = ?');
        $s->execute([(string) ($argv[2] ?? '')]);
        echo json_encode($s->fetch(PDO::FETCH_ASSOC));
        break;

    case 'cleanup':
        $id = (int) ($argv[2] ?? 0);
        if ($id > 0) {
            $db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id = {$id})");
            $db->exec("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = {$id}) OR customer_package_id IN (SELECT id FROM customer_packages WHERE user_id = {$id})");
            $db->exec("DELETE FROM booking_holds WHERE user_id = {$id}");
            $db->exec("DELETE FROM bookings WHERE user_id = {$id}");
            $db->exec("DELETE FROM customer_packages WHERE user_id = {$id}");
            $db->exec("DELETE FROM users WHERE id = {$id}");
        }
        echo 'ok';
        break;

    default:
        fwrite(STDERR, "Unknown command\n");
        exit(2);
}
