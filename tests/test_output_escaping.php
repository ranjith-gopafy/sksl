<?php

declare(strict_types=1);

/**
 * Output-escaping / allow-list tests (audit finding H5).
 *
 * Run: php tests/test_output_escaping.php
 * Section 3 hits Apache at http://localhost/sksl/public — skipped when unreachable.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

echo "===================================================================\n";
echo "SKSL — Output Escaping & URL Allow-list Tests (H5)\n";
echo "===================================================================\n\n";

$passCount = 0;
$failCount = 0;
function check(bool $condition, string $label): void
{
    global $passCount, $failCount;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $condition ? $passCount++ : $failCount++;
}

// 1. Image allow-list
echo "--- Section 1: safe_image() / service_image() ---\n";
check(is_safe_image_path('images/hero-banner.jpg'), '1.1 bundled image path accepted');
check(is_safe_image_path('uploads/services/service_1_abc123.webp'), '1.2 uploaded image path accepted');
check(is_safe_image_path('https://cdn.example.com/photo.png'), '1.3 https image URL accepted');
check(!is_safe_image_path('http://cdn.example.com/photo.png'), '1.4 plain http image URL rejected (mixed content)');
check(!is_safe_image_path('images/../index.php'), '1.5 traversal rejected');
check(!is_safe_image_path('images/x.jpg" onerror="alert(1)'), '1.6 attribute breakout rejected');
check(!is_safe_image_path('javascript:alert(1)'), '1.7 javascript: rejected');
check(!is_safe_image_path('images/shell.php'), '1.8 non-image extension rejected');
check(!is_safe_image_path('storage/invoices/x.jpg'), '1.9 paths outside images/ or uploads/ rejected');

$evil = 'images/x.jpg" onerror="alert(1)';
$out = safe_image($evil, 'images/hero-banner.jpg');
check(str_ends_with($out, '/images/hero-banner.jpg') && !str_contains($out, 'onerror'), '1.10 unsafe value falls back to bundled asset');
check(service_image('spa.jpg') === h(asset('images/services/spa.jpg')), '1.11 legacy bare filename resolved under images/services/');
check(service_image('uploads/services/abc.png') === h(asset('uploads/services/abc.png')), '1.12 uploaded path preserved');
check(service_image('"><script>alert(1)</script>') === h(asset('images/services/spa.jpg')), '1.13 injected markup replaced by fallback');
check(service_image(null) === h(asset('images/services/spa.jpg')), '1.14 null falls back');
check(str_contains(safe_image('https://cdn.example.com/a.png?x=1&y=2'), '&amp;'), '1.15 output is attribute-escaped');

// 2. Link allow-list
echo "\n--- Section 2: safe_link() ---\n";
check(is_safe_link('/services'), '2.1 site path accepted');
check(is_safe_link('/booking?service_id=3&x=1'), '2.2 site path with query accepted');
check(is_safe_link('https://wa.me/919999999999'), '2.3 https URL accepted');
check(!is_safe_link('javascript:alert(1)'), '2.4 javascript: rejected');
check(!is_safe_link('JavaScript:alert(1)'), '2.5 mixed-case javascript: rejected');
check(!is_safe_link('data:text/html,<script>'), '2.6 data: rejected');
check(!is_safe_link('//evil.example'), '2.7 protocol-relative rejected');
check(!is_safe_link('/x" onclick="alert(1)'), '2.8 attribute breakout rejected');
check(!is_safe_link("/x\nfoo"), '2.9 control characters rejected');
check(safe_link('javascript:alert(1)', '/services') === h(app_url('services')), '2.10 unsafe link falls back');
check(safe_link('/booking?service_id=3') === h(app_url('booking?service_id=3')), '2.11 relative link resolved via app_url()');
check(safe_link('https://example.com/a?b=1&c=2') === 'https://example.com/a?b=1&amp;c=2', '2.12 absolute link escaped');

// 3. Banner controller refuses unsafe media (through the real admin endpoint, if Apache is up)
echo "\n--- Section 3: Banner admin validation ---\n";
$db = getDb();
$stmt = $db->query("SELECT id, badge_text, headline, subheadline, image_url, cta_text, cta_link, is_active FROM hero_banners ORDER BY id LIMIT 1");
$banner = $stmt->fetch();
if (!$banner) {
    echo "[SKIP] no hero banner rows to test against\n";
} else {
    $baseUrl = 'http://localhost/sksl/public';
    $probe = @file_get_contents($baseUrl . '/health', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]));
    if ($probe === false) {
        echo "[SKIP] Apache not reachable at {$baseUrl}\n";
    } else {
        // Log in as a throwaway admin using the same OTP trick as the security audit
        $adminModel = new \App\Models\AdminModel();
        $adminEmail = 'h5_admin_' . time() . '@sk-sports-lab.test';
        $adminId = $adminModel->create('H5 Admin', $adminEmail, 'active');
        $adminModel->createOtp($adminId, password_hash('246810', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));

        $jar = sys_get_temp_dir() . '/sksl_h5_' . time() . '.txt';
        $http = static function (string $method, string $url, array $post = []) use ($jar): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10,
                CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            }
            $res = (string) curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            return [$code, substr($res, $hs)];
        };
        $csrf = static function (string $html): string {
            preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $m);
            return $m[1] ?? '';
        };

        [, $loginHtml] = $http('GET', $baseUrl . '/admin/login');
        [, $verifyHtml] = $http('GET', $baseUrl . '/admin/verify-otp?email=' . urlencode($adminEmail));
        $http('POST', $baseUrl . '/admin/verify-otp', ['_csrf_token' => $csrf($verifyHtml ?: $loginHtml), 'email' => $adminEmail, 'otp' => '246810']);
        [$code, $bannerHtml] = $http('GET', $baseUrl . '/admin/banner?edit=' . $banner['id']);
        check($code === 200, '3.1 Logged in to admin banner page (HTTP ' . $code . ')');
        $token = $csrf($bannerHtml);

        $attempt = static function (array $fields) use ($http, $baseUrl, $token, $banner): void {
            $http('POST', $baseUrl . '/admin/banner', array_merge([
                '_csrf_token' => $token, 'id' => $banner['id'], 'action' => 'update',
                'headline' => $banner['headline'], 'subheadline' => $banner['subheadline'],
                'badge_text' => $banner['badge_text'], 'cta_text' => $banner['cta_text'],
                'image_url' => $banner['image_url'], 'cta_link' => $banner['cta_link'],
                'is_active' => (string) (int) $banner['is_active'],
            ], $fields));
        };
        $current = static function () use ($db, $banner): array {
            $s = $db->prepare('SELECT image_url, cta_link FROM hero_banners WHERE id = ?');
            $s->execute([$banner['id']]);
            return $s->fetch();
        };

        $attempt(['cta_link' => 'javascript:alert(1)']);
        check($current()['cta_link'] === $banner['cta_link'], '3.2 javascript: CTA link rejected by admin endpoint');
        $attempt(['image_url' => 'images/x.jpg" onerror="alert(1)']);
        check($current()['image_url'] === $banner['image_url'], '3.3 attribute-breakout image rejected by admin endpoint');
        $attempt(['image_url' => 'images/services/does-not-exist.jpg']);
        check($current()['image_url'] === $banner['image_url'], '3.4 non-existent image file rejected by admin endpoint');
        $attempt(['image_url' => 'images/services/sauna.jpg', 'cta_link' => '/booking?service_id=2']);
        $now = $current();
        check($now['image_url'] === 'images/services/sauna.jpg' && $now['cta_link'] === '/booking?service_id=2', '3.5 valid image + link accepted');

        // restore original values + cleanup
        $db->prepare('UPDATE hero_banners SET badge_text = ?, headline = ?, subheadline = ?, image_url = ?, cta_text = ?, cta_link = ?, is_active = ? WHERE id = ?')
            ->execute([$banner['badge_text'], $banner['headline'], $banner['subheadline'], $banner['image_url'], $banner['cta_text'], $banner['cta_link'], (int) $banner['is_active'], $banner['id']]);
        $db->prepare('DELETE FROM admin_otps WHERE admin_id = ?')->execute([$adminId]);
        $db->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
        @unlink($jar);
    }
}

echo "\nResults: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo ">>> ALL OUTPUT ESCAPING TESTS PASSED <<<\n";
} else {
    echo ">>> {$failCount} TESTS FAILED <<<\n";
    exit(1);
}
