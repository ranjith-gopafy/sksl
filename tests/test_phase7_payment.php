<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/support/payment_support.php';

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Models\PaymentModel;
use App\Services\BookingService;
use App\Services\PaymentService;

echo "=======================================================\n";
echo "SKSL — Phase 7 Payment Architecture Verification Tests\n";
echo "=======================================================\n\n";

$db = getDb();
$userModel    = new UserModel();
$serviceModel = new ServiceModel();
$holdModel    = new BookingHoldModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();
$bookingSvc   = new BookingService();
$paymentSvc   = sksl_test_payment_service();

// 1. Setup Test User
$testEmail = 'paytest_' . time() . '@sk-sports-lab.test';
$userId = $userModel->create(
    'Payment Test Athlete',
    $testEmail,
    '9876543210',
    password_hash('Pass1234!', PASSWORD_BCRYPT)
);
assert($userId > 0, 'Failed to create test user.');
echo "[PASS] 1. Test customer created: ID $userId\n";

// 2. Select a service (e.g., Spa, base 999 + 18% GST = 1178.82)
$service = $serviceModel->findBySlug('spa');
assert($service !== null, 'Service "spa" not found.');
$serviceId = (int) $service['id'];
$testDate = date('Y-m-d', strtotime('+3 days'));
// Holds must sit on the published slot grid (open + n × (duration + buffer)),
// so pick real grid times instead of hard-coding clock values.
$spaGrid   = \App\Helpers\BookingRules::gridStarts((int) $service['duration_minutes']);
$startTime = $spaGrid[(int) floor(count($spaGrid) / 4)];   // mid-morning slot
$laterSlot = $spaGrid[(int) floor(count($spaGrid) * 3 / 4)]; // afternoon slot, no overlap

// 3. Create a hold
$holdRes = $bookingSvc->createHold($userId, $serviceId, $testDate, $startTime);
assert($holdRes['success'] === true, 'Failed to create hold: ' . ($holdRes['message'] ?? ''));
$bookingRef = $holdRes['data']['booking_reference'];
echo "[PASS] 2. Booking hold created: Ref $bookingRef\n";

// 4. Create Payment Order
$orderRes = $paymentSvc->createOrder($userId, $bookingRef);
assert($orderRes['success'] === true, 'Failed to create payment order: ' . ($orderRes['message'] ?? ''));
$orderData = $orderRes['data'];
echo "[PASS] 3. Payment order created: Order ID {$orderData['razorpay_order_id']}\n";
assert($orderData['amount_paise'] === 117882, "Expected 117882 paise, got {$orderData['amount_paise']}");
echo "[PASS] 4. Integer paise calculated correctly: {$orderData['amount_paise']} paise (₹1,178.82)\n";

// 5. Test Order Idempotency
$orderRes2 = $paymentSvc->createOrder($userId, $bookingRef);
assert($orderRes2['success'] === true, 'Second order creation failed.');
assert($orderRes2['data']['razorpay_order_id'] === $orderData['razorpay_order_id'], 'Order ID changed on second call.');
echo "[PASS] 5. Order creation is strictly idempotent (same Order ID returned)\n";

// 6. Test Signature Verification / Confirmation
$mockPaymentId = 'pay_' . bin2hex(random_bytes(6));

// 6a. A forged signature must be rejected even in mock mode (fail closed)
$forged = $paymentSvc->verifyPayment($userId, $bookingRef, $orderData['razorpay_order_id'], $mockPaymentId, 'sig_forged');
assert($forged['success'] === false, 'Forged signature must be rejected.');
echo "[PASS] 6a. Forged signature rejected (verification is never skipped)\n";

// 6b. The signature the server issued for the mock checkout must verify
assert(!empty($orderData['mock_signature']) && !empty($orderData['mock_payment_id']), 'Mock order must carry a server-issued signature.');
assert($orderData['mock_signature'] === sksl_test_sign($orderData['razorpay_order_id'], $orderData['mock_payment_id']), 'Server mock signature must be a real HMAC.');

$mockSignature = sksl_test_sign($orderData['razorpay_order_id'], $mockPaymentId);

$verifyRes = $paymentSvc->verifyPayment(
    $userId,
    $bookingRef,
    $orderData['razorpay_order_id'],
    $mockPaymentId,
    $mockSignature
);
assert($verifyRes['success'] === true, 'Payment verification failed: ' . ($verifyRes['message'] ?? ''));
echo "[PASS] 6. Payment verified and session confirmed!\n";

// 7. Verify Database State Transitions
$confirmedBooking = $bookingModel->findByReference($bookingRef);
assert($confirmedBooking['booking_status'] === 'confirmed', 'Booking status is not confirmed.');
assert($confirmedBooking['payment_status'] === 'paid', 'Payment status is not paid.');
echo "[PASS] 7. Booking status updated to 'confirmed' and payment_status to 'paid'\n";

$paymentRecord = $paymentModel->findByOrderId($orderData['razorpay_order_id']);
assert($paymentRecord['status'] === 'paid', 'Payment record status is not paid.');
assert($paymentRecord['razorpay_payment_id'] === $mockPaymentId, 'Payment ID does not match.');
echo "[PASS] 8. Payment record status updated to 'paid' with payment ID\n";

// 8. Verify Hold Released
$activeHold = $holdModel->findActiveByReference($bookingRef);
assert($activeHold === null, 'Hold should have been released upon confirmation.');
echo "[PASS] 9. Hold was released atomically\n";

// 9. Verify Confirmation Idempotency
$verifyRes2 = $paymentSvc->verifyPayment(
    $userId,
    $bookingRef,
    $orderData['razorpay_order_id'],
    $mockPaymentId,
    $mockSignature
);
assert($verifyRes2['success'] === true, 'Second verify failed.');
assert(!empty($verifyRes2['data']['already_confirmed']), 'Idempotency flag missing.');
echo "[PASS] 10. Payment verification is idempotent (already_confirmed handled safely)\n";

// 10. Test Webhook handling
$webhookPayload = json_encode([
    'event' => 'payment.captured',
    'payload' => [
        'payment' => [
            'entity' => [
                'id' => $mockPaymentId,
                'order_id' => $orderData['razorpay_order_id'],
                'amount' => 117882,
                'currency' => 'INR',
                'status' => 'captured',
            ]
        ]
    ]
]);

// 10a. Missing / wrong webhook signature must be rejected
$noSig = $paymentSvc->handleWebhook($webhookPayload, '');
assert($noSig['success'] === false && $noSig['code'] === 400, 'Webhook without signature must be rejected.');
$badSig = $paymentSvc->handleWebhook($webhookPayload, 'deadbeef');
assert($badSig['success'] === false && $badSig['code'] === 400, 'Webhook with wrong signature must be rejected.');
echo "[PASS] 11a. Webhook rejects missing and forged signatures\n";

// 10b. Webhook with no secret configured must answer 503 and confirm nothing
$noSecretSvc = new PaymentService(null, null, null, null, null, null, [
    'key_id' => 'rzp_test_offline', 'key_secret' => SKSL_TEST_KEY_SECRET, 'webhook_secret' => '', 'mock' => true,
]);
$noSecretRes = $noSecretSvc->handleWebhook($webhookPayload, sksl_test_sign_webhook($webhookPayload));
assert($noSecretRes['success'] === false && $noSecretRes['code'] === 503, 'Webhook without configured secret must be 503.');
echo "[PASS] 11b. Webhook fails closed when RAZORPAY_WEBHOOK_SECRET is missing\n";

// 10c. Correctly signed webhook is accepted and idempotent
$webhookRes = $paymentSvc->handleWebhook($webhookPayload, sksl_test_sign_webhook($webhookPayload));
assert($webhookRes['success'] === true && $webhookRes['code'] === 200, 'Signed webhook handler failed.');
echo "[PASS] 11c. Signed webhook processed idempotently (code: {$webhookRes['code']})\n";

// 11. A paid/confirmed booking must not get a second gateway order
$holdModel->create([
    'booking_reference' => $bookingRef, 'user_id' => $userId, 'service_id' => $serviceId,
    'booking_date' => $testDate, 'start_time' => '10:00:00', 'end_time' => $confirmedBooking['end_time'],
    'expires_at' => date('Y-m-d H:i:s', time() + 600),
]);
$reorder = $paymentSvc->createOrder($userId, $bookingRef);
assert($reorder['success'] === false, 'Second order for a confirmed booking must be refused.');
assert(str_contains($reorder['message'], 'already'), 'Refusal message must explain the booking is settled.');
echo "[PASS] 12. No second Razorpay order is opened for a confirmed booking\n";

// 12. A cancelled booking must never be re-confirmed by a late payment callback
$hold2 = $bookingSvc->createHold($userId, $serviceId, $testDate, $laterSlot);
assert($hold2['success'] === true, 'Second hold failed: ' . ($hold2['message'] ?? ''));
$ref2 = $hold2['data']['booking_reference'];
$order2 = $paymentSvc->createOrder($userId, $ref2);
assert($order2['success'] === true, 'Second order failed.');
$orderId2 = $order2['data']['razorpay_order_id'];
$booking2 = $bookingModel->findByReference($ref2);
$bookingModel->updateStatus((int) $booking2['id'], 'cancelled');   // cancelled before gateway callback

$latePayload = json_encode([
    'event' => 'payment.captured',
    'payload' => ['payment' => ['entity' => [
        'id' => 'pay_late_' . bin2hex(random_bytes(4)), 'order_id' => $orderId2,
        'amount' => (int) round(((float) $booking2['total_amount']) * 100), 'currency' => 'INR', 'status' => 'captured',
    ]]],
]);
$lateRes = $paymentSvc->handleWebhook($latePayload, sksl_test_sign_webhook($latePayload));
assert($lateRes['code'] === 200, 'Late webhook must be acknowledged.');
$afterLate = $bookingModel->findByReference($ref2);
assert($afterLate['booking_status'] === 'cancelled', 'Late webhook must not resurrect a cancelled booking.');
assert($afterLate['payment_status'] === 'paid', 'Captured money must still be recorded as paid.');
assert(str_contains((string) $afterLate['notes'], 'Refund required'), 'Booking must be flagged for refund.');
$payment2 = $paymentModel->findByOrderId($orderId2);
assert($payment2['status'] === 'paid', 'Payment row must be marked paid for reconciliation.');
echo "[PASS] 13. Cancelled booking stays cancelled after a late payment webhook; flagged for refund\n";

// 13. Production-like service with empty keys must refuse to create orders
$prodNoKeys = new PaymentService(null, null, null, null, null, null, [
    'key_id' => '', 'key_secret' => '', 'webhook_secret' => '', 'mock' => false,
]);
$refused = $prodNoKeys->createOrder($userId, $bookingRef);
assert($refused['success'] === false, 'Order creation without keys (non-mock) must be refused.');
echo "[PASS] 14. Order creation refuses to run without gateway keys outside mock mode\n";

// Clean up test data
$db->prepare('DELETE FROM payments WHERE razorpay_order_id IN (?, ?)')->execute([$orderData['razorpay_order_id'], $orderId2]);
$db->prepare('DELETE FROM bookings WHERE booking_reference IN (?, ?)')->execute([$bookingRef, $ref2]);
$db->prepare('DELETE FROM booking_holds WHERE booking_reference IN (?, ?)')->execute([$bookingRef, $ref2]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 15. Test cleanup completed successfully.\n\n";

echo ">>> ALL PHASE 7 PAYMENT VERIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
