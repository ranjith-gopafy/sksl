<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\AdminModel;
use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\BookingModel;
use App\Models\ClosedDateModel;
use App\Services\AdminAuthService;

echo "===================================================================\n";
echo "SKSL — Phase 12 & 13 Admin Bookings, Services & Closed Dates Tests\n";
echo "===================================================================\n\n";

$db = getDb();
$adminModel  = new AdminModel();
$userModel   = new UserModel();
$serviceModel= new ServiceModel();
$bookingModel= new BookingModel();
$closedModel = new ClosedDateModel();
$authService = new AdminAuthService();

// 1. Create Test Admin
$adminEmail = 'ops_admin_' . time() . '@sk-sports-lab.test';
$adminId = $adminModel->create('Operations Manager', $adminEmail, 'active');
assert($adminId > 0, 'Failed to create test admin');
echo "[PASS] 1. Test administrator created (ID: $adminId)\n";

// 2. Create Test Athlete and Test Booking
$userId = $userModel->create('Ops Athlete', 'athlete_ops_' . time() . '@test.com', '9876543210', password_hash('Pass1234!', PASSWORD_BCRYPT));
$service = $serviceModel->findBySlug('treadmill');
$bookingRef = 'SKSL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$bookingId = $bookingModel->create([
    'booking_reference'        => $bookingRef,
    'user_id'                  => $userId,
    'service_id'               => (int) $service['id'],
    'booking_date'             => date('Y-m-d', strtotime('+1 day')),
    'start_time'               => '08:00:00',
    'end_time'                 => '08:15:00',
    'service_duration_minutes' => 15,
    'base_amount'              => 149.00,
    'gst_amount'               => 26.82,
    'total_amount'             => 175.82,
    'booking_status'           => 'confirmed',
    'payment_status'           => 'paid',
]);
assert($bookingId > 0, 'Failed to create booking');
echo "[PASS] 2. Test booking created (Ref: $bookingRef, Modality: Treadmill)\n";

// 3. Test getAdminBookings with search & filters
$allBookings = $bookingModel->getAdminBookings(['search' => $bookingRef]);
assert(count($allBookings) === 1, 'Search by reference failed');
assert($allBookings[0]['booking_reference'] === $bookingRef, 'Reference mismatch');
echo "[PASS] 3. Admin booking search by reference verified\n";

$byStatus = $bookingModel->getAdminBookings(['status' => 'confirmed', 'service_id' => (int) $service['id']]);
assert(count($byStatus) >= 1, 'Filter by status and service failed');
echo "[PASS] 4. Admin booking filter by status and modality verified\n";

// 4. Test Booking Status Transition (Admin Action)
$updateSuccess = $bookingModel->updateStatus($bookingId, 'completed');
assert($updateSuccess === true, 'Failed to mark booking completed');
$reloaded = $bookingModel->findById($bookingId);
assert($reloaded['booking_status'] === 'completed', 'Status not updated to completed');
echo "[PASS] 5. Admin transitioned booking status to 'completed'\n";

// 5. Test Service Modality Management (Phase 13)
$testService = $serviceModel->findBySlug('walker');
assert($testService !== null, 'Service "walker" not found');
$srvId = (int) $testService['id'];

// Toggle to inactive
$serviceModel->updateStatus($srvId, 'inactive');
$toggled = $serviceModel->findById($srvId);
assert($toggled['status'] === 'inactive', 'Service status not toggled to inactive');
echo "[PASS] 6. Service deactivated successfully (hidden from catalog)\n";

// Toggle back to active
$serviceModel->updateStatus($srvId, 'active');
$activeAgain = $serviceModel->findActiveById($srvId);
assert($activeAgain !== null, 'Service not reactivated');
echo "[PASS] 7. Service reactivated successfully\n";

// Update specs
$newPrice = 199.00;
$newCap = 2;
// updateDetails() writes every column it knows about, so carry the image through
// and restore the exact original row afterwards (other suites depend on it).
$serviceModel->updateDetails($srvId, [
    'price'       => $newPrice,
    'capacity'    => $newCap,
    'description' => 'Updated high-performance walker session.',
    'status'      => 'active',
    'image'       => $testService['image'] ?? null,
]);
$updatedSrv = $serviceModel->findById($srvId);
assert((float) $updatedSrv['price'] === 199.00, 'Updated price mismatch');
assert((int) $updatedSrv['capacity'] === 2, 'Updated capacity mismatch');
assert(($updatedSrv['image'] ?? null) === ($testService['image'] ?? null), 'Image must survive a details update');
echo "[PASS] 8. Service price and capacity specifications updated\n";

// Revert to the original row
$serviceModel->updateDetails($srvId, [
    'price'       => $testService['price'],
    'capacity'    => $testService['capacity'],
    'description' => $testService['description'],
    'status'      => $testService['status'] ?? 'active',
    'image'       => $testService['image'] ?? null,
]);

// 6. Test Facility Closed Dates Management (Phase 13)
$closeDate = date('Y-m-d', strtotime('+10 days'));
assert($closedModel->isDateClosed($closeDate) === false, 'Date should initially be open');

$closeId = $closedModel->add($closeDate, 'Test Deep Sanitation Closure');
assert($closeId > 0, 'Failed to add closed date');
assert($closedModel->isDateClosed($closeDate) === true, 'Date should now be marked closed');
echo "[PASS] 9. Facility closed date added and verified (isDateClosed => true)\n";

$delSuccess = $closedModel->delete($closeId);
assert($delSuccess === true, 'Failed to delete closed date');
assert($closedModel->isDateClosed($closeDate) === false, 'Date should be open after deletion');
echo "[PASS] 10. Facility closed date removed and normal operations restored\n";

// 7. HTTP Verification via Apache
$baseUrl = 'http://localhost/sksl/public';

// Test unauthenticated access to /admin/bookings redirects to /admin/login
$ch = curl_init("$baseUrl/admin/bookings");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 302, "Expected 302 for unauthenticated admin access, got $httpCode");
echo "[PASS] 11. Unauthenticated request to /admin/bookings redirects to /admin/login\n";

// Login admin via HTTP
$cookieFile = sys_get_temp_dir() . '/sksl_admin_ops_cookie_' . time() . '.txt';

$ch = curl_init("$baseUrl/admin/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $loginHtml, $m);
$csrf = $m[1] ?? '';

// Send OTP
$ch = curl_init("$baseUrl/admin/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrf,
        'email' => $adminEmail,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
curl_close($ch);

// The web server's mail driver may be real SMTP (nothing readable on disk), so
// issue a second, known code for this admin exactly as the service would.
$otp = '246810';
$adminModel->createOtp($adminId, password_hash($otp, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));

// Verify OTP
$ch = curl_init("$baseUrl/admin/verify-otp");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrf,
        'email' => $adminEmail,
        'otp'   => $otp,
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch);
curl_close($ch);

// Test GET /admin/bookings as logged-in admin (the test booking is 'completed'; the default tab is 'confirmed')
$ch = curl_init("$baseUrl/admin/bookings?status=all");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$bHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 200, "Expected 200 for /admin/bookings, got $httpCode");
assert(str_contains($bHtml, 'SKSL Control Center'), 'Admin layout title missing');
assert(str_contains($bHtml, $bookingRef), "Booking reference $bookingRef missing from admin table");
echo "[PASS] 12. Authenticated admin access to /admin/bookings confirmed (HTTP 200)\n";

// Test GET /admin/services
$ch = curl_init("$baseUrl/admin/services");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$sHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 200, "Expected 200 for /admin/services, got $httpCode");
assert(str_contains($sHtml, 'Recovery Modalities Catalog'), 'Modalities catalog title missing');
echo "[PASS] 13. Authenticated admin access to /admin/services confirmed (HTTP 200)\n";

// Test GET /admin/closed-dates
$ch = curl_init("$baseUrl/admin/closed-dates");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$cHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert($httpCode === 200, "Expected 200 for /admin/closed-dates, got $httpCode");
assert(str_contains($cHtml, 'Mark Facility Closure'), 'Closed dates title missing');
echo "[PASS] 14. Authenticated admin access to /admin/closed-dates confirmed (HTTP 200)\n";

// 8. Cleanup
@unlink($cookieFile);
$db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$bookingId]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
$db->prepare('DELETE FROM admin_otps WHERE admin_id = ?')->execute([$adminId]);
$db->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
echo "[PASS] 15. Test data cleaned up successfully\n\n";

echo ">>> ALL PHASE 12 & 13 ADMIN OPERATIONS TESTS PASSED SUCCESSFULLY! <<<\n";
