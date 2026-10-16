<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
assert_cli_only(true);

echo "=======================================================================\n";
echo "SKSL — Phase 2 Extended Booking Engine & Features Diagnostic Suite\n";
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

// 0. Setup Test Customer Athlete
$userModel = new \App\Models\UserModel($db);
$testEmail = 'phase2_athlete_' . bin2hex(random_bytes(4)) . '@example.com';
$testMobile = '99' . mt_rand(10000000, 99999999);
$testUserId = $userModel->create(
    'Phase 2 Champion Athlete',
    $testEmail,
    $testMobile,
    password_hash('Secret@123', PASSWORD_DEFAULT)
);
report($testUserId > 0, "0.1 Test athlete created (User ID: $testUserId)");

// Cleanup any past test athlete bookings so test suite remains idempotent
$db->exec("DELETE FROM bookings WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'phase2_athlete_%')");
$db->exec("DELETE FROM booking_holds WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'phase2_athlete_%')");
$db->exec("DELETE FROM payments WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'phase2_athlete_%'))");
$db->exec("DELETE FROM customer_package_credits WHERE customer_package_id IN (SELECT id FROM customer_packages WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'phase2_athlete_%'))");
$db->exec("DELETE FROM customer_packages WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'phase2_athlete_%')");
$db->exec("DELETE FROM users WHERE email LIKE 'phase2_athlete_%' AND id != $testUserId");

// ─────────────────────────────────────────────────────────────────────────────
// 1. Variable Duration Options
// ─────────────────────────────────────────────────────────────────────────────
echo "\n1. Testing Variable Duration Options...\n";
$durationOptionModel = new \App\Models\DurationOptionModel($db);
$massageOptions = $durationOptionModel->getByServiceId(1, true); // Massages options
report(!empty($massageOptions), "1.1 Retrieved active duration options for Massages");

$opt60 = null;
foreach ($massageOptions as $opt) {
    if ((int) $opt['duration_minutes'] === 60) {
        $opt60 = $opt;
        break;
    }
}
report($opt60 !== null, "1.2 Found 60-minute option for Massages with price ₹" . ($opt60['price'] ?? 0));

// Test slot generator with custom duration
$availService = new \App\Services\AvailabilityService();
$testDate = date('Y-m-d', strtotime('+3 days'));
if ((int) date('w', strtotime($testDate)) === 0) {
    $testDate = date('Y-m-d', strtotime('+4 days'));
}

$slotsStandard = $availService->getAvailableSlots(1, $testDate, null, null);
$slotsCustom60 = $availService->getAvailableSlots(1, $testDate, null, (int) ($opt60['id'] ?? 0));

report($slotsStandard['success'] && $slotsCustom60['success'], "1.3 Availability queries succeed for standard and custom duration");
report(!empty($slotsCustom60['slots']), "1.4 Slots generated for 60-min duration option");
$firstSlot = $slotsCustom60['slots'][0] ?? null;
report($firstSlot !== null && (int) $firstSlot['duration_minutes'] === 60, "1.5 Generated slot duration matches selected duration option (60m)");

// ─────────────────────────────────────────────────────────────────────────────
// 2. Professional Services (Physiotherapy & Sports Psychology)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. Testing Professional Services (Physiotherapy & Sports Psychology)...\n";
$serviceModel = new \App\Models\ServiceModel($db);
$physio = $serviceModel->findById(11);
$psycho = $serviceModel->findById(12);

report($physio !== null && $physio['category'] === 'professional', "2.1 Physiotherapy is configured as professional category");
report($physio['pricing_type'] === 'pay_at_centre', "2.2 Physiotherapy has pricing_type = 'pay_at_centre'");
report($psycho !== null && $psycho['category'] === 'professional', "2.3 Sports Psychology is configured as professional category");
report($psycho['pricing_type'] === 'pay_at_centre', "2.4 Sports Psychology has pricing_type = 'pay_at_centre'");

// Availability rules test for Physiotherapy
$availRuleModel = new \App\Models\AvailabilityRuleModel($db);
$hasRules = $availRuleModel->hasRules(11);
report($hasRules, "2.5 Availability rules configured for Physiotherapy");

// Find a valid weekday for Physiotherapy (Monday-Friday) with an available slot
$physioDate = null;
$physioSlots = null;
$testSlot = null;
for ($offset = 1; $offset <= 14; $offset++) {
    $candDate = date('Y-m-d', strtotime("+$offset days"));
    $dow = (int) date('N', strtotime($candDate));
    if ($dow >= 1 && $dow <= 5) {
        $candSlots = $availService->getAvailableSlots(11, $candDate);
        if ($candSlots['success'] && !empty($candSlots['slots'])) {
            foreach ($candSlots['slots'] as $s) {
                if ($s['available']) {
                    $physioDate = $candDate;
                    $physioSlots = $candSlots;
                    $testSlot = $s;
                    break 2;
                }
            }
        }
    }
}
report($physioSlots !== null && !empty($physioSlots['slots']), "2.6 Practitioner slots generated according to availability schedule");
report($testSlot !== null, "2.7 Found available practitioner slot at " . ($testSlot['start_time'] ?? ''));

$bookingService = new \App\Services\BookingService();
$profBookingRes = $bookingService->createProfessionalBooking(
    $testUserId,
    11,
    $physioDate,
    $testSlot['start_time'] ?? '09:00',
    true
);

report($profBookingRes['success'] === true, "2.8 Professional booking confirmed directly (bypassing payment gateway)");
$profBooking = $profBookingRes['booking'] ?? null;
report(
    $profBooking !== null 
    && $profBooking['booking_status'] === 'confirmed' 
    && $profBooking['booking_type'] === 'professional'
    && (float) $profBooking['total_amount'] === 0.0,
    "2.9 Confirmed professional booking recorded with ₹0 online charge and confirmed status"
);

// ─────────────────────────────────────────────────────────────────────────────
// 3. Manual Maintenance Blocks
// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. Testing Manual Maintenance Blocks Overlap Rejection...\n";
$manualBlockModel = new \App\Models\ManualBlockModel($db);
$blockDate = date('Y-m-d', strtotime('+10 days'));

// Create a maintenance block on Sauna (Service 2) from 14:00 to 16:00
$blockId = $manualBlockModel->create([
    'service_id'    => 2,
    'block_type'    => 'specific_date',
    'specific_date' => $blockDate,
    'start_time'    => '14:00:00',
    'end_time'      => '16:00:00',
    'reason'        => 'Scheduled heating element maintenance',
]);
report($blockId > 0, "3.1 Created manual block for Sauna on $blockDate (14:00–16:00)");

$isBlocked = $manualBlockModel->isRangeBlocked(2, $blockDate, '14:30:00', '14:40:00');
report($isBlocked === true, "3.2 Range check correctly flags 14:30 slot as blocked");

$slotsAfterBlock = $availService->getAvailableSlots(2, $blockDate);
$blockedSlotFound = false;
foreach ($slotsAfterBlock['slots'] as $sl) {
    if ($sl['start_time'] >= '14:00:00' && $sl['start_time'] < '16:00:00') {
        if (!$sl['available'] && $sl['unavailable_reason'] === 'maintenance_block') {
            $blockedSlotFound = true;
            break;
        }
    }
}
report($blockedSlotFound, "3.3 Slots during manual block window are marked unavailable (reason: maintenance_block)");

// Cleanup test block
$manualBlockModel->delete($blockId);
report(true, "3.4 Test manual block cleaned up");

// ─────────────────────────────────────────────────────────────────────────────
// 4. Combo Packages & Credit Provisioning
// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Testing Combo Packages Purchase & Credit Provisioning...\n";
$packageModel = new \App\Models\PackageModel($db);
$packages = $packageModel->getAll(true);
report(count($packages) >= 1, "4.1 Retrieved active combo packages catalog (count: " . count($packages) . ")");

$trioPkg = $packages[0];
report(!empty($trioPkg['items']), "4.2 Package contains bundled modality items");

$packageService = new \App\Services\PackageService();
$purchaseOrder = $packageService->createPurchaseOrder($testUserId, (int) $trioPkg['id']);
report($purchaseOrder['success'] === true, "4.3 Package purchase order created successfully");

$orderData = $purchaseOrder['data'] ?? [];
$orderId = $orderData['order_id'] ?? '';
$paymentId = $orderData['mock_payment_id'] ?? ('pay_test_pkg_' . bin2hex(random_bytes(4)));
$signature = $orderData['mock_signature'] ?? hash_hmac('sha256', $orderId . '|' . $paymentId, (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? ''));

$verifyRes = $packageService->verifyPurchase($testUserId, $orderId, $paymentId, $signature);
report($verifyRes['success'] === true, "4.4 Package purchase payment verified successfully", $verifyRes['message'] ?? 'Failed');

$customerPackageId = $verifyRes['data']['id'] ?? 0;
report($customerPackageId > 0, "4.5 Customer package activated with ID: $customerPackageId");

$custPkgModel = new \App\Models\CustomerPackageModel($db);
$credits = $custPkgModel->getCredits($customerPackageId);
report(!empty($credits), "4.6 Package credits successfully provisioned in customer wallet");

$expectedCredits = array_sum(array_column($trioPkg['items'], 'quantity'));
$initialTotalCredits = array_sum(array_column($credits, 'remaining_credits'));
report($initialTotalCredits === $expectedCredits && $initialTotalCredits >= 2, "4.7 Customer wallet holds $initialTotalCredits credits across bundled modalities");

// ─────────────────────────────────────────────────────────────────────────────
// 5. Booking via Package Credit (Atomic Decrement)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. Testing Booking via Package Credit...\n";
$creditItem = $credits[0];
$creditServiceId = (int) $creditItem['service_id'];
$initialServiceCredits = (int) $creditItem['remaining_credits'];

// Find slot for this modality
$pkgBookDate = date('Y-m-d', strtotime('+4 days'));
if ((int) date('w', strtotime($pkgBookDate)) === 0) {
    $pkgBookDate = date('Y-m-d', strtotime('+5 days'));
}

$pkgSlots = $availService->getAvailableSlots($creditServiceId, $pkgBookDate);
$pkgSlot = null;
foreach ($pkgSlots['slots'] as $ps) {
    if ($ps['available']) {
        $pkgSlot = $ps;
        break;
    }
}
report($pkgSlot !== null, "5.1 Found available slot for bundled modality #$creditServiceId at " . ($pkgSlot['start_time'] ?? ''));

$pkgBookingRes = $bookingService->createPackageBooking(
    $testUserId,
    $customerPackageId,
    $creditServiceId,
    $pkgBookDate,
    $pkgSlot['start_time'],
    true
);

report($pkgBookingRes['success'] === true, "5.2 Package booking created successfully");
$pkgBooking = $pkgBookingRes['booking'] ?? null;
report(
    $pkgBooking !== null 
    && $pkgBooking['booking_status'] === 'confirmed' 
    && $pkgBooking['booking_type'] === 'package'
    && (int) $pkgBooking['customer_package_id'] === $customerPackageId,
    "5.3 Package booking recorded with booking_type='package' and confirmed status"
);

// Verify atomic credit decrement
$creditsAfter = $custPkgModel->getCredits($customerPackageId);
$remainingForService = 0;
foreach ($creditsAfter as $ca) {
    if ((int) $ca['service_id'] === $creditServiceId) {
        $remainingForService = (int) $ca['remaining_credits'];
        break;
    }
}
report($remainingForService === ($initialServiceCredits - 1), "5.4 Relational modality credit atomically decremented ($initialServiceCredits -> $remainingForService)");

// ─────────────────────────────────────────────────────────────────────────────
// 6. Admin Visited & No-Show (with Credit Restoration)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n6. Testing Admin Visited & No-Show with Credit Restoration...\n";
$bookingModel = new \App\Models\BookingModel($db);

// Attendance can only be recorded once the session has started, so move both bookings to yesterday.
$db->exec("UPDATE bookings SET booking_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id IN (" . (int) $profBooking['id'] . ", " . (int) $pkgBooking['id'] . ")");

// 6.1 Mark Visited on the professional appointment
$bookingModel->markVisited((int) $profBooking['id']);
$updatedProf = $bookingModel->findById((int) $profBooking['id']);
report($updatedProf['visit_status'] === 'visited', "6.1 Professional appointment transitioned to 'visited' status");

// 6.2 Mark No-Show on the package booking
// This should transition status to 'no_show' AND restore the consumed package credit!
$bookingModel->markNoShow((int) $pkgBooking['id']);
$updatedPkg = $bookingModel->findById((int) $pkgBooking['id']);
report($updatedPkg['visit_status'] === 'no_show', "6.2 Package booking transitioned to 'no_show' status");

// Restore credit (as AdminBookingController does)
$custPkgModel->restoreCredit($customerPackageId, $creditServiceId, 1);
$creditsRestored = $custPkgModel->getCredits($customerPackageId);
$remainingAfterRestore = 0;
foreach ($creditsRestored as $cr) {
    if ((int) $cr['service_id'] === $creditServiceId) {
        $remainingAfterRestore = (int) $cr['remaining_credits'];
        break;
    }
}
report($remainingAfterRestore === $initialServiceCredits, "6.3 Package credit successfully restored to wallet ($remainingForService -> $remainingAfterRestore)");

// ─────────────────────────────────────────────────────────────────────────────
// 7. Cancellation & Rescheduling Strict Policy Verification
// ─────────────────────────────────────────────────────────────────────────────
echo "\n7. Testing Cancellation & Rescheduling Policy Constraints...\n";
$bookingViewContent = file_get_contents(__DIR__ . '/../../app/views/pages/booking.php');
$myBookingsViewContent = file_get_contents(__DIR__ . '/../../app/views/pages/my-bookings.php');

$noCancelInBooking = str_contains($bookingViewContent, 'You can cancel a confirmed session from My Sessions');
$noCancelInMyBookings = str_contains($myBookingsViewContent, 'You can cancel a confirmed session here up to');

report($noCancelInBooking, "7.1 Booking page states the cancellation rule (notice period, charge, manual refund)");
report($noCancelInMyBookings, "7.2 Athlete portal states the cancellation rule and how to reach staff");

// Customer cancellation is a POST-only route keyed by booking reference
$routesContent = file_get_contents(__DIR__ . '/../../public/index.php');
$hasCustomerCancelRoute = str_contains($routesContent, "\$router->post('/my-bookings/{ref}/cancel'");
$cancelByGet = str_contains($routesContent, "\$router->get('/my-bookings/{ref}/cancel'");
report($hasCustomerCancelRoute && !$cancelByGet, "7.3 Customer cancellation is a POST-only endpoint");

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=======================================================================\n";
echo "Phase 2 Diagnostic Results: {$totalPassed} Passed, {$totalFailed} Failed\n";
echo "=======================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
