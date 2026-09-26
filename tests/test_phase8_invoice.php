<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\BookingModel;
use App\Models\PaymentModel;
use App\Services\InvoiceService;

echo "=======================================================\n";
echo "SKSL — Phase 8 Tax Invoice Architecture Verification Tests\n";
echo "=======================================================\n\n";

$db = getDb();
$userModel    = new UserModel();
$serviceModel = new ServiceModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();
$invoiceSvc   = new InvoiceService();

// 1. Create Test Athlete
$testEmail = 'invtest_' . time() . '@sk-sports-lab.test';
$userId = $userModel->create(
    'Invoice Athlete',
    $testEmail,
    '9876543210',
    password_hash('Pass1234!', PASSWORD_BCRYPT)
);
assert($userId > 0, 'Failed to create user');
echo "[PASS] 1. Test customer created (ID: $userId)\n";

// 2. Create Confirmed Booking (e.g. Sauna: base 499.00, GST 89.82, Total 588.82)
$service = $serviceModel->findBySlug('sauna');
assert($service !== null, 'Service "sauna" not found');
$bookingRef = 'SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$bookingId = $bookingModel->create([
    'booking_reference'        => $bookingRef,
    'user_id'                  => $userId,
    'service_id'               => (int) $service['id'],
    'booking_date'             => date('Y-m-d', strtotime('+2 days')),
    'start_time'               => '11:00:00',
    'end_time'                 => '11:10:00',
    'service_duration_minutes' => (int) $service['duration_minutes'],
    'base_amount'              => 499.00,
    'gst_amount'               => 89.82,
    'total_amount'             => 588.82,
    'booking_status'           => 'confirmed',
    'payment_status'           => 'paid',
]);
assert($bookingId > 0, 'Failed to create booking');
echo "[PASS] 2. Confirmed booking created (Ref: $bookingRef)\n";

// 3. Create Linked Payment Record
$orderId = 'order_mock_' . bin2hex(random_bytes(6));
$paymentId = 'pay_mock_' . bin2hex(random_bytes(6));
$paymentRecordId = $paymentModel->create([
    'booking_id'        => $bookingId,
    'razorpay_order_id' => $orderId,
    'amount'            => 588.82,
    'currency'          => 'INR',
    'status'            => 'pending',
]);
$paymentModel->markPaid($paymentRecordId, $paymentId, 'mock_signature', '{}');
// markPaid is idempotent: a second call must not overwrite the payment id
$paymentModel->markPaid($paymentRecordId, 'pay_should_not_win', 'x', '{}');
assert($paymentModel->findByOrderId($orderId)['razorpay_payment_id'] === $paymentId, 'markPaid must not overwrite a paid row');
echo "[PASS] 3. Linked payment record created (Payment ID: $paymentId)\n";

// 4. Test getInvoiceData()
$identity = \App\Services\InvoiceService::businessIdentity();
$invData = $invoiceSvc->getInvoiceData($bookingRef);
assert($invData !== null, 'Invoice data was null');
assert((bool) preg_match('#^[A-Z0-9]+/\d{4}-\d{2}/\d{6}$#', $invData['invoice_number']), 'Invoice number must be PREFIX/FY/000001, got ' . $invData['invoice_number']);
assert($invData['sac_code'] === $identity['sac_code'], 'SAC Code must come from business config');
assert($invData['base_amount'] === 499.00, 'Base amount mismatch');
assert($invData['cgst_percent'] === 9.0 && $invData['sgst_percent'] === 9.0, 'GST split must derive from stored gst_percent (18 -> 9 + 9)');
assert($invData['cgst_amount'] === 44.91, 'CGST 9% mismatch');
assert($invData['sgst_amount'] === 44.91, 'SGST 9% mismatch');
assert(round($invData['cgst_amount'] + $invData['sgst_amount'], 2) === 89.82, 'Total GST sum mismatch');
assert($invData['total_amount'] === 588.82, 'Total amount mismatch');
assert($invData['business_gstin'] === $identity['gstin'], 'GSTIN must come from business config');
assert($invData['business_gstin'] !== '33AATCS1234F1Z9', 'Placeholder GSTIN must be gone');
echo "[PASS] 4. Authoritative invoice data validated (GST split: ₹44.91 CGST + ₹44.91 SGST = ₹89.82; GSTIN from config" . ($identity['is_specimen'] ? ', SPECIMEN mode' : '') . ")\n";

// 4b. Invoice number is assigned once and persisted
$again = $invoiceSvc->getInvoiceData($bookingRef);
assert($again['invoice_number'] === $invData['invoice_number'], 'Invoice number must be stable');
$stored = $bookingModel->findByReference($bookingRef);
assert($stored['invoice_number'] === $invData['invoice_number'], 'Invoice number must be stored on the booking');
echo "[PASS] 4b. Invoice number persisted on booking: {$invData['invoice_number']}\n";

// 4c. Unpaid / cancelled bookings cannot get an invoice
$bookingModel->updateStatus($bookingId, 'cancelled');
try {
    $invoiceSvc->getInvoiceData($bookingRef);
    assert(false, 'Cancelled booking must not receive an invoice');
} catch (\App\Services\InvoiceNotAvailableException $e) {
    echo "[PASS] 4c. Cancelled booking refused an invoice\n";
}
$bookingModel->updateStatus($bookingId, 'confirmed', 'pending');
try {
    $invoiceSvc->getInvoiceData($bookingRef);
    assert(false, 'Unpaid booking must not receive an invoice');
} catch (\App\Services\InvoiceNotAvailableException $e) {
    echo "[PASS] 4d. Unpaid booking refused an invoice\n";
}
$bookingModel->updateStatus($bookingId, 'confirmed', 'paid');

// 5. Test renderHtml()
$html = $invoiceSvc->renderHtml($invData);
assert(str_contains($html, 'TAX INVOICE'), 'Missing TAX INVOICE heading');
assert(str_contains($html, $identity['gstin']), 'Missing GSTIN');
assert(str_contains($html, $identity['sac_code']), 'Missing SAC code');
assert(str_contains($html, $invData['invoice_number']), 'Missing invoice number');
assert(str_contains($html, $paymentId), 'Missing payment ID in audit trail');
assert(!str_contains($html, '98765 43210') && !str_contains($html, 'sk-sports-lab.com'), 'Placeholder contact details must be gone');
assert($identity['is_specimen'] ? str_contains($html, 'SPECIMEN') : !str_contains($html, 'SPECIMEN'), 'SPECIMEN stamp must match configuration state');
echo "[PASS] 5. HTML invoice template rendered with compliant GST headers\n";

// 6. Test generateInvoicePdf() using mPDF
$pdfPath = $invoiceSvc->generateInvoicePdf($bookingRef);
assert(file_exists($pdfPath), 'PDF file was not created on disk');
$fileSize = filesize($pdfPath);
assert($fileSize > 1000, "PDF file is suspiciously small ($fileSize bytes)");

$fileHandle = fopen($pdfPath, 'rb');
$header = fread($fileHandle, 5);
fclose($fileHandle);
assert($header === '%PDF-', 'File header does not match %PDF- signature');
echo "[PASS] 6. PDF successfully generated via mPDF ($fileSize bytes, valid %PDF- header)\n";

// 7. Test PDF caching (subsequent call returns same file path without error)
$pdfPath2 = $invoiceSvc->generateInvoicePdf($bookingRef);
assert($pdfPath2 === $pdfPath, 'Cache path mismatch');
echo "[PASS] 7. Invoice PDF generation utilizes file-system cache\n";

// 8. Test HTTP Download Endpoint via Apache
$baseUrl = 'http://localhost/sksl/public';
$cookieFile = sys_get_temp_dir() . '/sksl_inv_cookie_' . time() . '.txt';

// Login user via HTTP
$ch = curl_init("$baseUrl/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $loginHtml, $m);
$csrfToken = $m[1] ?? '';

$ch = curl_init("$baseUrl/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'email' => $testEmail,
        'password' => 'Pass1234!',
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch);
curl_close($ch);

// Download invoice as authorized customer
$ch = curl_init("$baseUrl/bookings/$bookingRef/invoice");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HEADER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($httpCode === 200, "Expected HTTP 200 for authorized invoice download, got $httpCode");
assert(str_contains($response, 'application/pdf'), 'Missing Content-Type: application/pdf');
assert(str_contains($response, 'attachment; filename="SKSL_Invoice_' . $bookingRef . '.pdf"'), 'Missing Content-Disposition header');
echo "[PASS] 8. HTTP invoice download served with application/pdf and attachment headers\n";

// 9. Cleanup
@unlink($pdfPath);
@unlink($cookieFile);
$db->prepare('DELETE FROM payments WHERE id = ?')->execute([$paymentRecordId]);
$db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$bookingId]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
echo "[PASS] 9. Test records and generated PDF cleaned up successfully\n\n";

echo ">>> ALL PHASE 8 TAX INVOICE TESTS PASSED SUCCESSFULLY! <<<\n";
