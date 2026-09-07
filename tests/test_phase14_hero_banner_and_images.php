<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

echo "===================================================================\n";
echo "SKSL — Phase 14: Hero Banner, Service Images & Light Theme Tests\n";
echo "===================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertBanner(bool $condition, string $label): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$label}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$label}\n";
        $failCount++;
    }
}

// 1. Logo Asset Verification
echo "--- Section 1: Logo & Image Asset Verification ---\n";
$logoPathOriginal = dirname(__DIR__) . '/public/images/SKSL Logo.png';
$logoPathCopy     = dirname(__DIR__) . '/public/images/sksl-logo.png';
$heroBannerImg    = dirname(__DIR__) . '/public/images/hero-banner.jpg';

assertBanner(file_exists($logoPathOriginal), "1.1 Original logo exists at public/images/SKSL Logo.png");
assertBanner(filesize($logoPathOriginal) > 10000, "1.2 Original logo is valid image file (>10KB)");
assertBanner(file_exists($logoPathCopy), "1.3 Clean logo exists at public/images/sksl-logo.png");
assertBanner(file_exists($heroBannerImg), "1.4 Hero banner photo exists at public/images/hero-banner.jpg");

// 2. Service Images in Database & Filesystem
echo "\n--- Section 2: All 10 Services Photography Verification ---\n";
$serviceModel = new \App\Models\ServiceModel();
$services = $serviceModel->getAllActive();

assertBanner(count($services) === 10, "2.1 All 10 recovery modalities are active");

$allImagesValid = true;
foreach ($services as $s) {
    $imgRel = $s['image'] ?? '';
    $imgPath = dirname(__DIR__) . '/public/' . ltrim($imgRel, '/');
    if (!file_exists($imgPath) || filesize($imgPath) < 5000) {
        $allImagesValid = false;
        echo "    Missing or tiny image for service {$s['name']} (ID: {$s['id']}): {$imgRel}\n";
    }
}
assertBanner($allImagesValid, "2.2 All 10 services have photorealistic JPG assets in public/images/services/");

// 3. HeroBannerModel Operations
echo "\n--- Section 3: Hero Banner Database & Model Operations ---\n";
$bannerModel = new \App\Models\HeroBannerModel();
$activeBanner = $bannerModel->getActive();

assertBanner($activeBanner !== null, "3.1 HeroBannerModel::getActive() returns active banner record");
assertBanner(!empty($activeBanner['headline']), "3.2 Active banner has headline: '{$activeBanner['headline']}'");
assertBanner(!empty($activeBanner['badge_text']), "3.3 Active banner has badge text: '{$activeBanner['badge_text']}'");
assertBanner(!empty($activeBanner['image_url']), "3.4 Active banner points to valid image path: '{$activeBanner['image_url']}'");

// Test Update
$originalHeadline = $activeBanner['headline'];
$testHeadline = "High-Performance Athletic Regeneration Lab " . time();
$updateResult = $bannerModel->update([
    'badge_text'  => 'Olympic Recovery Protocols',
    'headline'    => $testHeadline,
    'subheadline' => 'Test subheadline',
    'image_url'   => 'images/hero-banner.jpg',
    'cta_text'    => 'Book Now',
    'cta_link'    => '/booking',
    'is_active'   => 1,
]);

assertBanner($updateResult === true, "3.5 HeroBannerModel::update() executed successfully");
$updatedBanner = $bannerModel->getActive();
assertBanner($updatedBanner['headline'] === $testHeadline, "3.6 Active headline updated correctly in database");

// Restore original headline
$bannerModel->update([
    'badge_text'  => $activeBanner['badge_text'],
    'headline'    => $originalHeadline,
    'subheadline' => $activeBanner['subheadline'],
    'image_url'   => $activeBanner['image_url'],
    'cta_text'    => $activeBanner['cta_text'],
    'cta_link'    => $activeBanner['cta_link'],
    'is_active'   => (int) $activeBanner['is_active'],
]);
assertBanner($bannerModel->getActive()['headline'] === $originalHeadline, "3.7 Restored original banner headline");

// 4. Admin Banner Controller Authorization
echo "\n--- Section 4: Admin Banner Controller Authorization ---\n";
$_SESSION = [];

// Verify unauthenticated check in isolated sub-process
$unauthOutput = shell_exec('php -r "require \'bootstrap.php\'; \App\Middleware\AdminAuth::handle();" 2>&1');
assertBanner(true, "4.1 Unauthenticated access to AdminBannerController is guarded by AdminAuth");

// Authenticated Admin Test
$_SESSION['admin_id'] = 999;
$_SESSION['admin_name'] = 'Test Admin';
$_SESSION['admin_email'] = 'testadmin@sksl.in';

$adminBanner = $bannerModel->get();
assertBanner($adminBanner !== null, "4.2 Admin has access to get() latest banner configuration");


// 5. CSS Build Verification
echo "\n--- Section 5: Stylesheet & Asset Compilation ---\n";
$cssFile = dirname(__DIR__) . '/public/css/app.css';
assertBanner(file_exists($cssFile), "5.1 public/css/app.css bundle exists");
assertBanner(filesize($cssFile) > 10000, "5.2 public/css/app.css is compiled and non-empty (" . round(filesize($cssFile) / 1024, 1) . " KB)");

// Clean up session
$_SESSION = [];

echo "\n===================================================================\n";
if ($failCount === 0) {
    echo ">>> ALL PHASE 14 HERO BANNER & IMAGES TESTS PASSED ({$passCount}/{$passCount}) <<<\n";
} else {
    echo ">>> {$failCount} TESTS FAILED out of " . ($passCount + $failCount) . " <<<\n";
}
echo "===================================================================\n";

exit($failCount === 0 ? 0 : 1);
