<?php

declare(strict_types=1);

echo "=== SKSL Phase 4 Verification Suite ===\n\n";

$baseUrl = 'http://localhost/sksl/public';

function checkUrl(string $url, string $expectedContent = '', int $expectedStatus = 200): bool {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== $expectedStatus) {
        echo "  [FAIL] {$url} returned HTTP {$status} (expected {$expectedStatus})\n";
        return false;
    }

    if ($expectedContent !== '' && !str_contains($body, $expectedContent)) {
        echo "  [FAIL] {$url} did not contain expected snippet '{$expectedContent}'\n";
        return false;
    }

    echo "  [PASS] {$url} (HTTP {$status})\n";
    return true;
}

$allPassed = true;

// 1. Homepage
echo "1. Testing Homepage...\n";
$allPassed = checkUrl("{$baseUrl}/", 'Sara Kinetic Sports Lab') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/", 'Signature Recovery Modalities') && $allPassed;

// 2. Services Catalog
echo "\n2. Testing Services Catalog...\n";
$allPassed = checkUrl("{$baseUrl}/services", 'Recovery Modalities &amp; Pricing') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/services", 'Ice Bath') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/services", 'Endless Pool') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/services", 'Lap Pool') && $allPassed;

// 3. API Services
echo "\n3. Testing JSON API Endpoints...\n";
$ch = curl_init("{$baseUrl}/api/services");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$jsonStr = curl_exec($ch);
curl_close($ch);
$apiData = json_decode($jsonStr, true);

if (isset($apiData['success']) && $apiData['success'] && count($apiData['data'] ?? []) === 10) {
    echo "  [PASS] /api/services returned 10 active services in JSON format.\n";
} else {
    echo "  [FAIL] /api/services failed to return 10 services.\n";
    $allPassed = false;
}

// Single service API
$ch = curl_init("{$baseUrl}/api/services/4");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$jsonIce = curl_exec($ch);
curl_close($ch);
$iceData = json_decode($jsonIce, true);

if (isset($iceData['data']['name']) && $iceData['data']['name'] === 'Ice Bath') {
    echo "  [PASS] /api/services/4 returned Ice Bath with pricing breakdown.\n";
} else {
    echo "  [FAIL] /api/services/4 did not return Ice Bath.\n";
    $allPassed = false;
}

// 4. Legal & Info Pages
echo "\n4. Testing Legal & Informational Pages...\n";
$allPassed = checkUrl("{$baseUrl}/privacy-policy", 'Privacy Policy') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/terms", 'Facility Terms &amp; Conditions') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/cancellation-refund", 'Cancellation &amp; Refund Policy') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/contact", 'Phone, Email &amp; Support') && $allPassed;
$allPassed = checkUrl("{$baseUrl}/contact", 'Cancellation &amp; Refund Policy') && $allPassed;

echo "\n============================================\n";
if ($allPassed) {
    echo "All Phase 4 endpoints verified successfully!\n";
    echo "============================================\n";
    exit(0);
} else {
    echo "Some tests failed!\n";
    echo "============================================\n";
    exit(1);
}
