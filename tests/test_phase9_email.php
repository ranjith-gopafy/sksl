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
// Development mail driver: messages land in storage/logs/mail.log and are recorded in email_logs as 'logged'
$_ENV['MAIL_DRIVER'] = 'log';
$emailLogModel = new \App\Models\EmailLogModel();
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
$steamGrid = \App\Helpers\BookingRules::gridStarts((int) $service['duration_minutes']);
$startTime = $steamGrid[(int) floor(count($steamGrid) / 2)]; // a real grid slot around midday

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

// 6b. Both deliveries are recorded in email_logs, linked to the booking, without bodies
$bookingRow = $bookingModel->findByReference($bookingRef);
$logRows = $emailLogModel->findByBooking((int) $bookingRow['id']);
$types = array_column($logRows, 'email_type');
assert(in_array('booking_confirmation', $types, true), 'booking_confirmation not recorded in email_logs');
assert(in_array('admin_new_booking', $types, true), 'admin_new_booking not recorded in email_logs');
foreach ($logRows as $row) {
    assert($row['status'] === 'logged', "email_logs status should be 'logged' for the dev driver, got {$row['status']}");
    assert(!array_key_exists('body', $row) && !array_key_exists('html_body', $row), 'email_logs must not store bodies');
}
$custRow = array_values(array_filter($logRows, static fn($r) => $r['email_type'] === 'booking_confirmation'))[0];
assert($custRow['recipient'] === $testEmail && str_contains((string) $custRow['subject'], $bookingRef), 'Customer confirmation log has recipient + subject');
echo "[PASS] 7b. email_logs holds one row per delivery (recipient, type, subject, status) linked to booking #{$bookingRow['id']}\n";

// 6c. SMTP misconfiguration never dumps bodies: with no sender address the send fails cleanly and is recorded
$savedDriver = $_ENV['MAIL_DRIVER'];
$_ENV['MAIL_DRIVER'] = 'smtp';
$smtpSvc = new EmailService();
$probeEmail = 'probe_' . time() . '@sk-sports-lab.test';
$sizeBefore = filesize($mailLogFile);
if ($smtpSvc->fromAddress() === '') {
    $ok = $smtpSvc->send($probeEmail, 'Probe', 'Probe subject', '<p>SECRET-BODY-42</p>', 'SECRET-BODY-42', null, null, 'probe');
    assert($ok === false, 'send() must return false when no sender is configured');
    $probeLogs = $emailLogModel->findByRecipient($probeEmail, 'probe', 1);
    assert(count($probeLogs) === 1 && $probeLogs[0]['status'] === 'failed', 'Failed send recorded as failed');
    assert(filesize($mailLogFile) === $sizeBefore, 'No body written to mail.log on SMTP failure');
    echo "[PASS] 7c. SMTP send without sender fails closed and is recorded (no body on disk)\n";
} else {
    echo "[SKIP] 7c. SMTP is configured locally; failure path covered by source inspection\n";
}
$src = file_get_contents(dirname(__DIR__) . '/app/services/EmailService.php');
assert(!preg_match('/catch \(PHPMailerException[^}]*logMail\(/s', $src), 'The SMTP failure path must not call logMail()');
assert(!str_contains($src, 'noreply@sksl.in'), 'No invented fallback sender address');
assert(str_contains($src, "'Your SKSL Admin sign-in code'"), 'OTP subject line carries no code');
$_ENV['MAIL_DRIVER'] = $savedDriver;
$db->prepare("DELETE FROM email_logs WHERE recipient = ?")->execute([$probeEmail]);

// 7. Cleanup
@unlink($pdfPath);
$db->prepare('DELETE FROM payments WHERE razorpay_order_id = ?')->execute([$orderId]);
$db->prepare('DELETE FROM bookings WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM booking_holds WHERE booking_reference = ?')->execute([$bookingRef]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
$db->prepare('DELETE FROM email_logs WHERE recipient = ? OR subject LIKE ?')->execute([$testEmail, '%' . $bookingRef . '%']);
echo "[PASS] 8. Test data cleaned up successfully\n\n";

echo ">>> ALL PHASE 9 TRANSACTIONAL EMAIL TESTS PASSED! <<<\n";
