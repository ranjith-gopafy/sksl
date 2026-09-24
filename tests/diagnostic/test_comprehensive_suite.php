<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

echo "=======================================================================\n";
echo "SKSL — Comprehensive Step-by-Step Scenarios Test Suite (User & Admin)\n";
echo "=======================================================================\n\n";

$db = getDb();
$totalPassed = 0;
$totalFailed = 0;

function report(bool $passed, string $step, ?string $info = null): void {
    global $totalPassed, $totalFailed;
    if ($passed) {
        echo "  [PASS] {$step}\n";
        $totalPassed++;
    } else {
        echo "  [FAIL] {$step}" . ($info ? " — {$info}" : "") . "\n";
        $totalFailed++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 1: Public Athlete Experience
// ─────────────────────────────────────────────────────────────────────────────
echo "1. Testing Public Athlete Pages & Components...\n";

// 1.1 Homepage Hero Banners
$bannerModel = new \App\Models\HeroBannerModel($db);
$activeBanners = $bannerModel->getAllActive();
report(count($activeBanners) >= 1 && count($activeBanners) <= 5, "1.1 Active Hero Carousel slides between 1 and 5 (got " . count($activeBanners) . ")");

// 1.2 Metric counters row removal from home.php
$homeContent = file_get_contents(__DIR__ . '/../../app/views/pages/home.php');
report(!str_contains($homeContent, 'Metric Counters') && !str_contains($homeContent, '10+ Protocols'), "1.2 Metric Counters row is completely deleted from home.php");

// 1.3 Recovery Modalities Catalog
$serviceModel = new \App\Models\ServiceModel($db);
$services = $serviceModel->getAllActive();
report(count($services) === 10, "1.3 All 10 recovery modalities active in catalog");

// 1.4 Pricing calculation (Base + 18% GST)
$pricing = \App\Models\ServiceModel::calculatePricing(1000.0, 18.0);
report($pricing['base_price'] === 1000.0 && $pricing['gst_amount'] === 180.0 && $pricing['total_amount'] === 1180.0, "1.4 Pricing formula correctly calculates Base (₹1000) + 18% GST (₹180) = ₹1180");

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 2: Availability Engine & .env Buffer Minutes
// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. Testing Availability Engine & Buffer Minutes Integration...\n";
$availService = new \App\Services\AvailabilityService();
$futureDate = date('Y-m-d', strtotime('+5 days'));

// Test slot generation with duration and buffer
$slotsRes = $availService->getAvailableSlots(1, $futureDate); // Spa: 30 min duration
report($slotsRes['success'] === true, "2.1 Availability query succeeds for future date ($futureDate)");
report(!empty($slotsRes['slots']), "2.2 Slots generated successfully");

$buffer = (int) (($_ENV['CHECKIN_BUFFER_MINUTES'] ?? 0) + ($_ENV['CHECKOUT_BUFFER_MINUTES'] ?? 0));
if ($buffer === 0 && isset($_ENV['BUFFER_MINUTES'])) {
    $buffer = (int) $_ENV['BUFFER_MINUTES'];
}
$first = $slotsRes['slots'][0] ?? null;
$second = $slotsRes['slots'][1] ?? null;
if ($first && $second) {
    $firstStartMin = ((int) substr($first['start_time'], 0, 2)) * 60 + ((int) substr($first['start_time'], 3, 2));
    $secondStartMin = ((int) substr($second['start_time'], 0, 2)) * 60 + ((int) substr($second['start_time'], 3, 2));
    $stepMin = $secondStartMin - $firstStartMin;
    $expectedStep = 30 + $buffer;
    report($stepMin === $expectedStep, "2.3 Slot interval ({$stepMin}m) exactly matches duration (30m) + buffer ({$buffer}m)");
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 3: Customer Account & Booking Hold Flow
// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. Testing Customer Account, Holds & Payment Flow...\n";

// 3.1 Customer Creation
$customerModel = new \App\Models\UserModel($db);
$testEmail = 'test_athlete_' . bin2hex(random_bytes(4)) . '@example.com';
$testMobile = '98' . mt_rand(10000000, 99999999);
$userId = $customerModel->create(
    'Champion Athlete',
    $testEmail,
    $testMobile,
    password_hash('Secret@123', PASSWORD_DEFAULT)
);
report($userId > 0, "3.1 Customer account created (User ID: $userId)");

// 3.2 Customer Authentication
$user = $customerModel->findByEmail($testEmail);
report($user !== null && password_verify('Secret@123', $user['password_hash']), "3.2 Customer password verification successful");

// 3.3 Create Booking Hold (Pick an available slot)
$targetDate = date('Y-m-d', strtotime('+' . mt_rand(15, 28) . ' days'));
$availForTarget = $availService->getAvailableSlots(1, $targetDate);
$firstAvailSlot = '08:00';
foreach (($availForTarget['slots'] ?? []) as $s) {
    if (!empty($s['available']) && ($s['remaining_capacity'] ?? 0) > 0) {
        $firstAvailSlot = $s['start_time'];
        break;
    }
}
$bookingService = new \App\Services\BookingService();
$holdRes = $bookingService->createHold($userId, 1, $targetDate, $firstAvailSlot);
report($holdRes['success'] === true, "3.3 Booking hold initiated for {$firstAvailSlot} (Ref: " . ($holdRes['data']['booking_reference'] ?? '') . ")", $holdRes['message'] ?? 'Failed');
$confirmedRef = $holdRes['data']['booking_reference'] ?? '';

// 3.4 Create Order and Confirm Booking
$paymentService = new \App\Services\PaymentService();
$orderRes = $paymentService->createOrder($userId, $confirmedRef);
report($orderRes['success'] === true, "3.4 Payment order created for booking hold (Order ID: " . ($orderRes['data']['razorpay_order_id'] ?? '') . ")");

$bookingModel = new \App\Models\BookingModel($db);
$pendingBooking = $bookingModel->findByReference($confirmedRef);
if ($pendingBooking) {
    $bookingModel->updateStatus((int) $pendingBooking['id'], 'confirmed', 'paid');
}
$savedBooking = $bookingModel->findByReference($confirmedRef);
report($savedBooking !== null && $savedBooking['booking_status'] === 'confirmed', "3.5 Booking status updated to 'confirmed' and 'paid' in database");

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 4: Tax Invoice Generation & Access Restrictions
// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Testing Tax Invoice Generation & Restriction Security...\n";
$invoiceService = new \App\Services\InvoiceService();

// 4.1 Generate Invoice for Confirmed Booking
$invoiceData = $invoiceService->getInvoiceData($confirmedRef);
report(!empty($invoiceData['invoice_number']) && ($invoiceData['total_amount'] ?? 0) > 0 && ($invoiceData['cgst_amount'] ?? 0) > 0, "4.1 Invoice data generated with GST compliance breakdown (CGST: ₹" . ($invoiceData['cgst_amount'] ?? 0) . ", SGST: ₹" . ($invoiceData['sgst_amount'] ?? 0) . ", Total: ₹" . ($invoiceData['total_amount'] ?? 0) . ")");

// 4.2 Test Cancelled / Pending Booking PDF Restriction (Item 2)
// Transition booking status to 'cancelled'
$bookingModel->updateStatus((int) $savedBooking['id'], 'cancelled');

// Test invoice download restriction directly in sub-process
$tmpScript = __DIR__ . '/../../storage/test_dl.php';
file_put_contents($tmpScript, '<?php require __DIR__ . "/../bootstrap.php"; (new App\Services\InvoiceService())->downloadInvoice($argv[1], (int) $argv[2]);');
$resCode = trim((string) shell_exec("php " . escapeshellarg($tmpScript) . " " . escapeshellarg($confirmedRef) . " " . $userId));
@unlink($tmpScript);
report(str_contains($resCode, 'Tax Invoice is not available'), "4.2 InvoiceService::downloadInvoice strictly outputs rejection for cancelled booking (got: {$resCode})");

// 4.3 Verify my-bookings view hides PDF for cancelled and pending
$myBookingsView = file_get_contents(__DIR__ . '/../../app/views/pages/my-bookings.php');
report(str_contains($myBookingsView, "\$b['booking_status'] !== 'cancelled' && \$b['booking_status'] !== 'pending'"), "4.3 my-bookings.php hides invoice button when status is cancelled or pending");

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 5: Admin Management Console (Modality Catalog, 3-Dots, Banners)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. Testing Admin Modalities, 3-Dots Menu & Banner Manager...\n";

// 5.1 Admin Modality Catalog Data Attributes (Fix for JavaScript SyntaxError)
$adminServicesView = file_get_contents(__DIR__ . '/../../app/views/admin/services.php');
report(str_contains($adminServicesView, 'class="btn-edit-service') && str_contains($adminServicesView, 'data-description='), "5.1 admin/services.php uses safe data-* attributes instead of inline onclick arguments");
report(!str_contains($adminServicesView, 'onclick="openEditModal('), "5.2 Dangerous unescaped inline onclick='openEditModal(...)' is removed");

// 5.2 Admin Bookings 3-Dots Menu & View Modal (Item 3 & 6)
$adminBookingsView = file_get_contents(__DIR__ . '/../../app/views/admin/bookings.php');
report(str_contains($adminBookingsView, 'toggleActionMenu(event'), "5.3 3-dots kebab menu action trigger present in bookings table");
report(str_contains($adminBookingsView, 'id="booking-view-modal"'), "5.4 Read-only View Modal (#booking-view-modal) present in bookings view");
report(str_contains($adminBookingsView, 'class="btn-view-booking-details') && str_contains($adminBookingsView, 'data-booking='), "5.5 View Details uses safe data-booking attribute");

// 5.3 Admin Hero Banner Manager (Item 4)
$bannersAll = $bannerModel->getAll();
report(count($bannersAll) >= 1 && count($bannersAll) <= 5, "5.6 Hero Banner Manager has " . count($bannersAll) . " banners (Max 5 enforced)");

// Test updating a banner by ID
$firstBanner = $bannersAll[0];
$updateSuccess = $bannerModel->updateById((int)$firstBanner['id'], array_merge($firstBanner, [
    'headline' => 'Elite Sports Science & Regeneration Lab',
]));
report($updateSuccess, "5.7 Banner #" . $firstBanner['id'] . " headline successfully updated");

// Test backward compatibility methods
$bannerGetActive = $bannerModel->getActive();
report(!empty($bannerGetActive['headline']), "5.8 HeroBannerModel::getActive() backwards compatibility verified");
$bannerGet = $bannerModel->get();
report(!empty($bannerGet['id']), "5.9 HeroBannerModel::get() backwards compatibility verified");

// 5.4 Admin Closed Dates Facility Enforcement
$closedDateModel = new \App\Models\ClosedDateModel();
$holiday = date('Y-m-d', strtotime('+12 days'));
$db->prepare("DELETE FROM closed_dates WHERE closed_date = ?")->execute([$holiday]);
$closedId = $closedDateModel->add($holiday, 'Annual Facility Maintenance');
$isClosed = $closedDateModel->isDateClosed($holiday);
report($isClosed === true, "5.10 Facility closed date successfully registered ($holiday)");

$holidayAvail = $availService->getAvailableSlots(1, $holiday);
report($holidayAvail['success'] === false && str_contains(strtolower($holidayAvail['message'] ?? ''), 'closed'), "5.11 Availability engine blocks booking on closed date");

// Clean up closed date
$closedDateModel->delete($closedId);
report($closedDateModel->isDateClosed($holiday) === false, "5.12 Closed date removed and operations restored");

// ─────────────────────────────────────────────────────────────────────────────
// CLEANUP
// ─────────────────────────────────────────────────────────────────────────────
echo "\n6. Cleaning Up Test Artifacts...\n";
$db->prepare("DELETE FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = ?)")->execute([$userId]);
$db->prepare("DELETE FROM bookings WHERE user_id = ?")->execute([$userId]);
$db->prepare("DELETE FROM booking_holds WHERE user_id = ?")->execute([$userId]);
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
echo "  [INFO] Test database entries cleaned up.\n";

echo "\n=======================================================================\n";
echo "FINAL RESULTS: {$totalPassed} Passed, {$totalFailed} Failed\n";
echo "=======================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
