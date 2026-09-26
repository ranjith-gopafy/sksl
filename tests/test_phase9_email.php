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
use App\Services\InvoiceService;
use App\Services\EmailService;

echo "=======================================================\n";
echo "SKSL — Phase 9 Transactional Email System Verification\n";
echo "=======================================================\n\n";

$db = getDb();
$userModel    = new UserModel();
$serviceModel = new ServiceModel();
$bookingModel = new BookingModel();
$holdModel    = new BookingHoldModel();
$paymentModel = new PaymentModel();
$bookingSvc   = new BookingService();
$paymentSvc   = sksl_test_payment_service();
$invoiceSvc   = new InvoiceService();
$emailSvc     = new EmailService();

$mailLogFile = dirname(__DIR__) . '/storage/logs/mail.log';
$initialLogSize = file_exists($mailLogFile) ? filesize($mailLogFile) : 0;

// 1. Create Test Athlete
$testEmail = 'mailtest_' . time() . '@sk-sports-lab.test';
$userId = $userModel->create(
    'Email Test Athlete',
    $testEmail,
    '9876543210',
    password_hash('Pass1234!', PASSWORD_BCRYPT)
);
assert($userId > 0, 'Failed to create athlete');
echo "[PASS] 1. Test athlete created (ID: $userId, Email: $testEmail)\n";

// 2. Select Modality & Create Hold
$service = $serviceModel->findBySlug('steam');
assert($service !== null, 'Service "steam" not found');
$serviceId = (int) $service['id'];
$date = date('Y-m-d', strtotime('+3 days'));
$startTime = '14:00';

$holdRes = $bookingSvc->createHold($userId, $serviceId, $date, $startTime);
assert($holdRes['success'] === true, 'Failed to create hold: ' . ($holdRes['message'] ?? ''));
$bookingRef = $holdRes['data']['booking_reference'];
echo "[PASS] 2. Booking hold established: $bookingRef\n";

// 3. Create Gateway Order
$orderRes = $paymentSvc->createOrder($userId, $bookingRef);
assert($orderRes['success'] === true, 'Failed to create payment order');
$orderId = $orderRes['data']['razorpay_order_id'];
echo "[PASS] 3. Gateway order issued: $orderId\n";

// 4. Verify Payment — This triggers post-commit invoice PDF generation + 2 emails (customer + admin)
$mockPaymentId = 'pay_mail_' . bin2hex(random_bytes(6));
$mockSignature = sksl_test_sign($orderId, $mockPaymentId);

$verifyRes = $paymentSvc->verifyPayment($userId, $bookingRef, $orderId, $mockPaymentId, $mockSignature);
assert($verifyRes['success'] === true, 'Payment verify failed: ' . ($verifyRes['message'] ?? ''));
echo "[PASS] 4. Payment verified and booking confirmed\n";

// 5. Verify PDF Invoice was generated on disk
$pdfPath = dirname(__DIR__) . '/storage/invoices/' . $bookingRef . '.pdf';
assert(file_exists($pdfPath), 'Invoice PDF was not generated post-payment');
$pdfSize = filesize($pdfPath);
assert($pdfSize > 1000, "Generated PDF too small ($pdfSize bytes)");
echo "[PASS] 5. Invoice PDF auto-generated on disk post-payment ($pdfSize bytes)\n";

// 6. Verify Emails were logged to storage/logs/mail.log
assert(file_exists($mailLogFile), 'Mail log file does not exist');
$currentLogSize = filesize($mailLogFile);
assert($currentLogSize > $initialLogSize, 'No new entries written to mail log');

$mailLogContent = file_get_contents($mailLogFile);

// Check Customer confirmation email in log
assert(str_contains($mailLogContent, $testEmail), "Customer email $testEmail not found in mail log");
assert(str_contains($mailLogContent, $bookingRef), "Booking reference $bookingRef not found in mail log");
assert(str_contains($mailLogContent, $pdfPath), "Invoice PDF attachment $pdfPath not referenced in customer email");
echo "[PASS] 6. Customer booking confirmation email logged with attached PDF invoice\n";

// Check Admin alert in log
assert(str_contains($mailLogContent, '[New Booking] Steam — Email Test Athlete'), "Admin notification subject not found in mail log");
echo "[PASS] 7. Admin new booking notification email logged\n";

// 7. Cleanup
@unlink($pdfPath);
$db->prepare('DELETE FROM payments WHERE razorpay_order_id = ?')->execute([$orderId]);
$db->prepare('DELETE FROM bookings WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 8. Test data cleaned up successfully\n\n";

echo ">>> ALL PHASE 9 TRANSACTIONAL EMAIL TESTS PASSED! <<<\n";
