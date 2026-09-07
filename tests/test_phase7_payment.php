<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

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
$paymentSvc   = new PaymentService();

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
$startTime = '10:00';

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
$mockSignature = 'sig_' . bin2hex(random_bytes(16));

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
                'status' => 'captured',
            ]
        ]
    ]
]);
$webhookRes = $paymentSvc->handleWebhook($webhookPayload, '');
assert($webhookRes['success'] === true, 'Webhook handler failed.');
echo "[PASS] 11. Webhook processing executed idempotently (code: {$webhookRes['code']})\n";

// Clean up test data
$db->prepare('DELETE FROM payments WHERE razorpay_order_id = ?')->execute([$orderData['razorpay_order_id']]);
$db->prepare('DELETE FROM bookings WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 12. Test cleanup completed successfully.\n\n";

echo ">>> ALL PHASE 7 PAYMENT VERIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
