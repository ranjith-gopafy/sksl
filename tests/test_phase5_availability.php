<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\AvailabilityService;
use App\Models\ServiceModel;
use App\Models\ClosedDateModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Models\UserModel;
use App\Helpers\TimeHelper;

echo "=== SKSL Phase 5 Availability Engine Test Suite ===\n\n";

// This suite exercises the pure duration grid (06:00, 06:10, 06:20 … for a 10-min
// service). Force the turnaround buffer to 0 for the run regardless of .env so
// the expected slot counts below are deterministic. Buffered grids are covered
// by tests/test_booking_rules.php.
foreach (['CHECKIN_BUFFER_MINUTES', 'CHECKOUT_BUFFER_MINUTES', 'BUFFER_MINUTES'] as $bufKey) {
    $_ENV[$bufKey] = '0';
    putenv($bufKey . '=0');
}

$db = getDb();
$availability = new AvailabilityService();
$closedDateModel = new ClosedDateModel();
$bookingModel = new BookingModel();
$holdModel = new BookingHoldModel();

$testsPassed = 0;
$testsFailed = 0;

function assertAvail(bool $condition, string $testName): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $testsFailed++;
    }
}

// Future test date: 7 days from today
$testDate = TimeHelper::now()->modify('+7 days')->format('Y-m-d');
$pastDate = TimeHelper::now()->modify('-1 day')->format('Y-m-d');

// ── Test 1: Dynamic Slot Generation by Duration ──────────────────────────────
echo "1. Testing Slot Generation Across Durations (06:00 – 22:00 IST)...\n";

// Ice Bath (id=4, 10 min) -> 16 hours * 6 = 96 slots
$res10 = $availability->getAvailableSlots(4, $testDate);
assertAvail($res10['success'], 'Ice Bath (10 min) availability query succeeds');
assertAvail(count($res10['slots']) === 96, "Generated 96 slots for 10-min service (got " . count($res10['slots']) . ")");
assertAvail($res10['slots'][0]['start_time'] === '06:00', 'First slot starts at 06:00');
assertAvail($res10['slots'][0]['end_time'] === '06:10', 'First slot ends at 06:10');
assertAvail($res10['slots'][95]['start_time'] === '21:50', 'Last slot starts at 21:50');
assertAvail($res10['slots'][95]['end_time'] === '22:00', 'Last slot ends exactly at 22:00');

// Cycle (id=7, 15 min) -> 16 hours * 4 = 64 slots
$res15 = $availability->getAvailableSlots(7, $testDate);
assertAvail(count($res15['slots']) === 64, "Generated 64 slots for 15-min service (got " . count($res15['slots']) . ")");

// Spa (id=1, 30 min) -> 16 hours * 2 = 32 slots
$res30 = $availability->getAvailableSlots(1, $testDate);
assertAvail(count($res30['slots']) === 32, "Generated 32 slots for 30-min service (got " . count($res30['slots']) . ")");

// Lap Pool (id=10, 45 min) -> floor(960 / 45) = 21 slots (ends at 21:45)
$res45 = $availability->getAvailableSlots(10, $testDate);
assertAvail(count($res45['slots']) === 21, "Generated 21 slots for 45-min service (got " . count($res45['slots']) . ")");
assertAvail($res45['slots'][20]['end_time'] === '21:45', 'Last 45-min slot finishes inside 22:00 boundary (21:45)');

// ── Test 2: Past Date Rejection ──────────────────────────────────────────────
echo "\n2. Testing Past Date Rejection...\n";
$resPast = $availability->getAvailableSlots(4, $pastDate);
assertAvail(!$resPast['success'], 'Past date is rejected');
assertAvail(str_contains($resPast['message'], 'past'), 'Error message specifies past date');

// ── Test 3: Closed Dates ─────────────────────────────────────────────────────
echo "\n3. Testing Closed Dates Facility-Wide Enforcement...\n";
$closedDate = TimeHelper::now()->modify('+10 days')->format('Y-m-d');
$closedId = $closedDateModel->add($closedDate, 'Facility Deep Maintenance');

$resClosed = $availability->getAvailableSlots(4, $closedDate);
assertAvail(!$resClosed['success'], 'Closed date returns no availability');
assertAvail(str_contains($resClosed['message'], 'closed'), 'Error message specifies facility closure');

// Remove closed date and re-test
$closedDateModel->delete($closedId);
$resReopened = $availability->getAvailableSlots(4, $closedDate);
assertAvail($resReopened['success'], 'Reopened date returns available slots');

// ── Test 4: Capacity Protection with Confirmed Bookings ──────────────────────
echo "\n4. Testing Capacity Enforcement (Ice Bath capacity = 8)...\n";

// Create a dummy user for bookings
$userModel = new UserModel();
$dummyEmail = 'test_capacity_' . time() . '@example.com';
$dummyUserId = $userModel->create('Capacity Athlete', $dummyEmail, '9876543210', password_hash('pass', PASSWORD_DEFAULT));

$targetSlotStart = '10:00:00';
$targetSlotEnd   = '10:10:00';
$bookingIds = [];

// Insert 7 confirmed bookings for Ice Bath (cap=8) at 10:00
for ($i = 1; $i <= 7; $i++) {
    $ref = 'TEST-CAP-' . $i . '-' . time();
    $bId = $bookingModel->create([
        'booking_reference'        => $ref,
        'user_id'                  => $dummyUserId,
        'service_id'               => 4, // Ice Bath
        'booking_date'             => $testDate,
        'start_time'               => $targetSlotStart,
        'end_time'                 => $targetSlotEnd,
        'service_duration_minutes' => 10,
        'base_amount'              => 449.00,
        'gst_amount'               => 80.82,
        'total_amount'             => 529.82,
        'booking_status'           => 'confirmed',
        'payment_status'           => 'paid',
    ]);
    $bookingIds[] = $bId;
}

$check7 = $availability->getAvailableSlots(4, $testDate);
$slot10 = null;
foreach ($check7['slots'] as $s) {
    if ($s['start_time'] === '10:00') {
        $slot10 = $s;
        break;
    }
}
assertAvail($slot10 !== null, 'Found 10:00 slot in availability list');
assertAvail($slot10['available'] === true, 'Slot is still available with 7/8 booked');
assertAvail($slot10['remaining_capacity'] === 1, 'Remaining capacity is exactly 1 (got ' . $slot10['remaining_capacity'] . ')');

// Insert 8th confirmed booking (fills capacity)
$ref8 = 'TEST-CAP-8-' . time();
$bId8 = $bookingModel->create([
    'booking_reference'        => $ref8,
    'user_id'                  => $dummyUserId,
    'service_id'               => 4,
    'booking_date'             => $testDate,
    'start_time'               => $targetSlotStart,
    'end_time'                 => $targetSlotEnd,
    'service_duration_minutes' => 10,
    'base_amount'              => 449.00,
    'gst_amount'               => 80.82,
    'total_amount'             => 529.82,
    'booking_status'           => 'confirmed',
    'payment_status'           => 'paid',
]);
$bookingIds[] = $bId8;

$check8 = $availability->getAvailableSlots(4, $testDate);
foreach ($check8['slots'] as $s) {
    if ($s['start_time'] === '10:00') {
        $slot10 = $s;
        break;
    }
}
assertAvail($slot10['available'] === false, 'Slot 10:00 is marked UNAVAILABLE when capacity 8/8 reached');
assertAvail($slot10['remaining_capacity'] === 0, 'Remaining capacity is 0');
assertAvail($slot10['unavailable_reason'] === 'fully_booked', 'Reason is fully_booked');

// Adjacent slots (e.g. 09:50 or 10:10) remain unaffected
$slot1010 = null;
foreach ($check8['slots'] as $s) {
    if ($s['start_time'] === '10:10') {
        $slot1010 = $s;
        break;
    }
}
assertAvail($slot1010['available'] === true, 'Adjacent slot 10:10 is completely unaffected and available');

// ── Test 5: Temporary Booking Hold Consumes Capacity ─────────────────────────
echo "\n5. Testing Temporary Hold Capacity Consumption...\n";

// Lap Pool (id=10, capacity = 1) at 11:15 - 12:00
$holdRef = 'HOLD-TEST-' . time();
$holdId = $holdModel->create([
    'booking_reference' => $holdRef,
    'user_id'           => $dummyUserId,
    'service_id'        => 10, // Lap Pool (cap 1)
    'booking_date'      => $testDate,
    'start_time'        => '11:15:00',
    'end_time'          => '12:00:00',
    'expires_at'        => TimeHelper::now()->modify('+10 minutes')->format('Y-m-d H:i:s'),
]);

$checkHold = $availability->getAvailableSlots(10, $testDate);
$slotLap = null;
foreach ($checkHold['slots'] as $s) {
    if ($s['start_time'] === '11:15') {
        $slotLap = $s;
        break;
    }
}
assertAvail($slotLap !== null, 'Found 11:15 slot for Lap Pool');
assertAvail($slotLap['available'] === false, 'Active temporary hold blocks capacity (Lap Pool 11:15 unavailable)');
assertAvail($slotLap['occupied'] === 1, 'Hold counts as 1 occupied unit');

// ── Test 6: Expired Hold Releases Capacity Immediately ───────────────────────
echo "\n6. Testing Expired Hold Release (without waiting for cron)...\n";

// Fast-forward hold expiry to 5 minutes ago
$db->exec("UPDATE booking_holds SET expires_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE id = {$holdId}");

$checkExpired = $availability->getAvailableSlots(10, $testDate);
foreach ($checkExpired['slots'] as $s) {
    if ($s['start_time'] === '11:15') {
        $slotLap = $s;
        break;
    }
}
assertAvail($slotLap['available'] === true, 'Expired hold is automatically ignored and slot becomes available');
assertAvail($slotLap['remaining_capacity'] === 1, 'Capacity restored to 1');

// ── Test 7: Customer Double-Booking Conflict Prevention ──────────────────────
echo "\n7. Testing Customer Overlap Conflict Prevention...\n";

// Dummy user already has confirmed bookings at 10:00 on $testDate
// Query availability passing $dummyUserId
$checkUserConflict = $availability->getAvailableSlots(1, $testDate, $dummyUserId); // Spa 30 min (10:00 - 10:30 overlaps 10:00 - 10:10)
$slotSpaConflict = null;
foreach ($checkUserConflict['slots'] as $s) {
    if ($s['start_time'] === '10:00') {
        $slotSpaConflict = $s;
        break;
    }
}
assertAvail($slotSpaConflict['available'] === false, 'Customer cannot book conflicting overlapping slot for another modality');
assertAvail($slotSpaConflict['unavailable_reason'] === 'user_conflict', 'Reason indicates user conflict');

// ── Cleanup ──────────────────────────────────────────────────────────────────
echo "\n8. Cleaning up test bookings and holds...\n";
$db->exec("DELETE FROM bookings WHERE id IN (" . implode(',', $bookingIds) . ")");
$db->exec("DELETE FROM booking_holds WHERE id = {$holdId}");
$db->exec("DELETE FROM users WHERE id = {$dummyUserId}");
echo "  [INFO] Test records cleaned up.\n";

echo "\n============================================\n";
echo "Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "============================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
