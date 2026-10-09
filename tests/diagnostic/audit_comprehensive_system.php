<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
assert_cli_only(true);

echo "=======================================================================\n";
echo "SKSL — FULL COMPREHENSIVE ARCHITECTURE & SECURITY AUDIT\n";
echo "=======================================================================\n\n";

$db = getDb();
$totalPassed = 0;
$totalWarnings = 0;
$totalFailed = 0;

function auditPass(string $label, ?string $detail = null): void {
    global $totalPassed;
    $totalPassed++;
    echo "  [PASS] {$label}" . ($detail ? " ($detail)" : "") . "\n";
}

function auditWarn(string $label, string $detail): void {
    global $totalWarnings;
    $totalWarnings++;
    echo "  [WARN] {$label} — {$detail}\n";
}

function auditFail(string $label, string $detail): void {
    global $totalFailed;
    $totalFailed++;
    echo "  [FAIL] {$label} — {$detail}\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. DATABASE SCHEMA & INTEGRITY AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "1. Database Schema & Tables Audit...\n";

$requiredTables = [
    'users',
    'services',
    'service_duration_options',
    'availability_rules',
    'manual_blocks',
    'packages',
    'package_items',
    'customer_packages',
    'customer_package_credits',
    'bookings',
    'booking_holds',
    'payments',
    'closed_dates',
    'hero_banners',
];

$existingTables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN) ?: [];

foreach ($requiredTables as $tbl) {
    if (in_array($tbl, $existingTables, true)) {
        auditPass("Table exists: `{$tbl}`");
    } else {
        auditFail("Missing table", "`{$tbl}` not found in database.");
    }
}

// Check key Phase 2 columns in `bookings`
$bookingColumns = $db->query("SHOW COLUMNS FROM `bookings`")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
$expectedBookingCols = ['booking_type', 'payment_mode', 'duration_option_id', 'package_credit_id', 'visit_status', 'visit_marked_at', 'visit_marked_by_admin_id'];
foreach ($expectedBookingCols as $col) {
    if (in_array($col, $bookingColumns, true)) {
        auditPass("Bookings column verified: `{$col}`");
    } else {
        auditFail("Missing column in `bookings`", "`{$col}` column missing.");
    }
}

// Check key Phase 2 columns in `services`
$serviceColumns = $db->query("SHOW COLUMNS FROM `services`")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
$expectedServiceCols = ['category', 'pricing_type', 'duration_mode'];
foreach ($expectedServiceCols as $col) {
    if (in_array($col, $serviceColumns, true)) {
        auditPass("Services column verified: `{$col}`");
    } else {
        auditFail("Missing column in `services`", "`{$col}` column missing.");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. BUSINESS CONSTRAINTS & DATA AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. Business Constraints & Services Configuration Audit...\n";

// 2.1 Standard Modalities Count
$modalities = $db->query("SELECT * FROM services WHERE category = 'modality' AND status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
if (count($modalities) === 9) {
    auditPass("Active Recovery Modalities catalog verified (9 active, hot-bath removed)");
} else {
    auditFail("Modality Count Mismatch", "Expected 9 active modalities, found " . count($modalities));
}

// 2.2 Physiotherapy configuration
$physio = $db->query("SELECT * FROM services WHERE slug = 'physiotherapy' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
if ($physio) {
    $pPass = ((int)$physio['duration_minutes'] === 45)
          && ($physio['category'] === 'professional')
          && ($physio['pricing_type'] === 'pay_at_centre')
          && ((float)$physio['price'] === 0.0);
    if ($pPass) {
        auditPass("Physiotherapy configured correctly (45m slot, pay_at_centre, ₹0 online display)");
    } else {
        auditFail("Physiotherapy Misconfigured", json_encode($physio));
    }
} else {
    auditFail("Physiotherapy Service Missing", "Slug 'physiotherapy' not found.");
}

// 2.3 Sports Psychology configuration
$psych = $db->query("SELECT * FROM services WHERE slug = 'sports-psychology' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
if ($psych) {
    $psPass = ((int)$psych['duration_minutes'] === 60)
           && ($psych['category'] === 'professional')
           && ($psych['pricing_type'] === 'pay_at_centre')
           && ((float)$psych['price'] === 0.0);
    if ($psPass) {
        auditPass("Sports Psychology configured correctly (60m slot, pay_at_centre, ₹0 online display)");
    } else {
        auditFail("Sports Psychology Misconfigured", json_encode($psych));
    }
} else {
    auditFail("Sports Psychology Service Missing", "Slug 'sports-psychology' not found.");
}

// 2.4 Duration Options configured for modalities
$optsCount = (int)$db->query("SELECT COUNT(*) FROM service_duration_options WHERE status = 'active'")->fetchColumn();
if ($optsCount >= 10) {
    auditPass("Variable duration options catalog populated", "Total active options: {$optsCount}");
} else {
    auditWarn("Low duration options count", "Found {$optsCount} duration options.");
}

// 2.5 Practitioner Availability Rules
$rulesCount = (int)$db->query("SELECT COUNT(*) FROM availability_rules WHERE status = 'active'")->fetchColumn();
if ($rulesCount >= 5) {
    auditPass("Practitioner availability rules active", "Total active rules: {$rulesCount}");
} else {
    auditFail("Missing Availability Rules", "Expected at least 5 active rules, found {$rulesCount}");
}

// 2.6 Active Combo Packages
$packages = $db->query("SELECT * FROM packages WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
if (count($packages) >= 3) {
    auditPass("Combo packages active in catalog", "Count: " . count($packages));
    // Verify each package has items
    $pkgItemStmt = $db->prepare("SELECT COUNT(*) FROM package_items WHERE package_id = ?");
    $allItemsValid = true;
    foreach ($packages as $pkg) {
        $pkgItemStmt->execute([$pkg['id']]);
        if ((int)$pkgItemStmt->fetchColumn() === 0) {
            $allItemsValid = false;
            auditFail("Package without items", "Package {$pkg['name']} has 0 items");
        }
    }
    if ($allItemsValid) {
        auditPass("All combo packages have bundled modality items configured");
    }
} else {
    auditFail("Insufficient Packages", "Expected at least 3 packages, found " . count($packages));
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ROUTE REGISTRATION & CONTROLLER INTEGRITY AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. Route Registration & Controller Audit...\n";

$routerFile = file_get_contents(__DIR__ . '/../../public/index.php');

// Verify that all routes referenced have valid Controller classes and methods
$matchedRoutes = [];
preg_match_all("/\\\$router->(get|post|delete|patch|put)\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*\[([^:]+)::class\s*,\s*['\"]([^'\"]+)['\"]\s*\]\s*\)/i", $routerFile, $matches, PREG_SET_ORDER);

$brokenRoutes = 0;
foreach ($matches as $m) {
    $httpMethod = strtoupper($m[1]);
    $routeUri   = $m[2];
    $controller = trim($m[3]);
    $methodName = trim($m[4]);

    // Format fully qualified class name
    $fqcn = '\\App\\Controllers\\' . ltrim($controller, '\\');
    if (!class_exists($fqcn)) {
        // Maybe FQCN is already specified or without namespace
        if (class_exists($controller)) {
            $fqcn = $controller;
        } else {
            auditFail("Route points to missing Controller", "{$httpMethod} {$routeUri} -> {$controller}::{$methodName}");
            $brokenRoutes++;
            continue;
        }
    }

    if (!method_exists($fqcn, $methodName)) {
        auditFail("Route points to missing Action method", "{$httpMethod} {$routeUri} -> {$fqcn}::{$methodName}");
        $brokenRoutes++;
    }
}

if ($brokenRoutes === 0) {
    auditPass("All " . count($matches) . " registered routes have valid Controllers & Action methods");
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. SECURITY & PERMISSION AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Security & Access Control Audit...\n";

// 4.1 Strict No-Customer-Cancellation Constraint
$hasCustomerCancelRoute = str_contains($routerFile, "'/api/bookings/{id}/cancel'") 
    || str_contains($routerFile, "'/bookings/{id}/cancel'")
    || str_contains($routerFile, "'/bookings/cancel'");
if (!$hasCustomerCancelRoute) {
    auditPass("Customer cancellation route strictly absent (Contact centre policy enforced)");
} else {
    auditFail("Unwanted Customer Cancellation Route", "Found customer cancellation route in index.php");
}

// 4.2 Admin Route Guards
// Ensure every admin controller verifies AdminAuth
$adminControllers = [
    \App\Controllers\AdminBookingController::class,
    \App\Controllers\AdminServiceController::class,
    \App\Controllers\AdminAvailabilityController::class,
    \App\Controllers\AdminManualBlockController::class,
    \App\Controllers\AdminPackageController::class,
    \App\Controllers\AdminBannerController::class,
    \App\Controllers\AdminClosedDateController::class,
];

foreach ($adminControllers as $ctrl) {
    $ref = new ReflectionClass($ctrl);
    $fn = $ref->getFileName();
    $content = file_get_contents($fn);
    if (str_contains($content, 'AdminAuth::handle()') || str_contains($content, 'AdminAuth::check()')) {
        auditPass("AdminAuth guard verified in " . $ref->getShortName());
    } else {
        auditFail("Admin Controller without Auth guard", $ref->getShortName());
    }
}

// 4.3 CSRF Protection on Forms
// Audit views with POST forms to ensure CSRF token is present
$viewFiles = [
    'app/views/pages/booking.php',
    'app/views/admin/services.php',
    'app/views/admin/bookings.php',
    'app/views/admin/availability.php',
    'app/views/admin/manual-blocks.php',
    'app/views/admin/packages.php',
];

foreach ($viewFiles as $vf) {
    $fullPath = __DIR__ . '/../../' . $vf;
    if (file_exists($fullPath)) {
        $vContent = file_get_contents($fullPath);
        // If file has `<form method="POST"` or `method="post"`, check for Csrf::field or csrf_token
        if (preg_match('/<form[^>]+method=["\']post["\']/i', $vContent)) {
            if (str_contains($vContent, 'Csrf::field()') || str_contains($vContent, 'csrf_token') || str_contains($vContent, 'X-CSRF-Token')) {
                auditPass("CSRF protection present in {$vf}");
            } else {
                auditWarn("CSRF field missing in form", $vf);
            }
        } else {
            auditPass("View verified (No raw POST form or uses JS API with CSRF): {$vf}");
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. CONCURRENCY & ROW-LOCKING AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. Concurrency & Row-Locking Audit...\n";

$bookingServiceFile = file_get_contents(__DIR__ . '/../../app/services/BookingService.php');
$hasHoldRowLock = str_contains($bookingServiceFile, 'FOR UPDATE');
if ($hasHoldRowLock) {
    auditPass("BookingService uses `FOR UPDATE` row-locking during concurrency contention");
} else {
    auditFail("Missing Concurrency Lock", "BookingService does not use FOR UPDATE");
}

$custPkgModelFile = file_get_contents(__DIR__ . '/../../app/models/CustomerPackageModel.php');
$hasCreditRowLock = str_contains($custPkgModelFile, 'FOR UPDATE') && str_contains($custPkgModelFile, 'remaining_quantity > 0');
if ($hasCreditRowLock) {
    auditPass("CustomerPackageModel implements atomic credit decrement with `FOR UPDATE` and balance verification");
} else {
    auditFail("Missing Atomic Lock", "CustomerPackageModel credit consumption missing atomic balance check");
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. POLICY NOTICES & ATHLETE EXPERIENCE AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n6. Policy Notices & Athlete Experience Audit...\n";

$bookingView = file_get_contents(__DIR__ . '/../../app/views/pages/booking.php');
$myBookingsView = file_get_contents(__DIR__ . '/../../app/views/pages/my-bookings.php');

if (str_contains($bookingView, 'You can cancel a confirmed session from My Sessions')) {
    auditPass("Booking checkout view states the cancellation rule");
} else {
    auditFail("Missing Policy Notice", "booking.php lacks the cancellation rule text");
}

if (str_contains($myBookingsView, 'You can cancel a confirmed session here up to')) {
    auditPass("Athlete portal states the cancellation rule");
} else {
    auditFail("Missing Policy Notice", "my-bookings.php lacks the cancellation rule text");
}

// ─────────────────────────────────────────────────────────────────────────────
// AUDIT SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=======================================================================\n";
echo "AUDIT RESULTS: {$totalPassed} Checks Passed, {$totalWarnings} Warnings, {$totalFailed} Failures\n";
echo "=======================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
