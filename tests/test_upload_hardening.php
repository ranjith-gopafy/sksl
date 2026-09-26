<?php

declare(strict_types=1);

/**
 * Upload hardening tests (audit finding H3).
 *
 * Run: php tests/test_upload_hardening.php
 * Section 3 hits Apache at http://localhost/sksl/public — skipped when unreachable.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers\ImageUpload;

echo "===================================================================\n";
echo "SKSL — Upload Hardening Tests (H3)\n";
echo "===================================================================\n\n";

$passCount = 0;
$failCount = 0;
function check(bool $condition, string $label): void
{
    global $passCount, $failCount;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $condition ? $passCount++ : $failCount++;
}

$tmpDir  = sys_get_temp_dir() . '/sksl_upload_test_' . bin2hex(random_bytes(4));
$destDir = $tmpDir . '/dest';
mkdir($tmpDir, 0777, true);
ImageUpload::allowLocalFilesForTesting(true);

$fake = static function (string $path, string $clientName = 'photo.jpg'): array {
    return ['name' => $clientName, 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK, 'type' => 'image/jpeg'];
};

// 1. Genuine JPEG is accepted, renamed randomly, extension from content
echo "--- Section 1: Valid uploads ---\n";
$srcJpg = dirname(__DIR__) . '/public/images/services/spa.jpg';
$copy   = $tmpDir . '/spa-input.JPEG';
copy($srcJpg, $copy);
$r = ImageUpload::store($fake($copy, '../../evil.php'), $destDir, 'service_1');
check($r['ok'] === true, '1.1 Real JPEG accepted (' . ($r['error'] ?? 'ok') . ')');
check(isset($r['filename']) && (bool) preg_match('/^service_1_[a-f0-9]{16}\.jpg$/', $r['filename']), '1.2 Stored name is random with content-derived .jpg extension: ' . ($r['filename'] ?? ''));
check(isset($r['filename']) && is_file($destDir . '/' . $r['filename']), '1.3 File written to destination directory');
check(isset($r['filename']) && (getimagesize($destDir . '/' . $r['filename'])[2] ?? 0) === IMAGETYPE_JPEG, '1.4 Stored file decodes as JPEG');
check(!file_exists(dirname($destDir) . '/evil.php'), '1.5 Client filename path traversal ignored');

// PNG with alpha
$png = imagecreatetruecolor(40, 30);
imagesavealpha($png, true);
imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
imagepng($png, $tmpDir . '/alpha.png');
$r = ImageUpload::store($fake($tmpDir . '/alpha.png', 'x.jpg'), $destDir, 'service_2');
check($r['ok'] === true && str_ends_with($r['filename'] ?? '', '.png'), '1.6 PNG detected by content even though client said .jpg');

// 2. Malicious / invalid uploads rejected
echo "\n--- Section 2: Rejected uploads ---\n";
file_put_contents($tmpDir . '/shell.php', "<?php system(\$_GET['c']);");
$r = ImageUpload::store($fake($tmpDir . '/shell.php', 'shell.php'), $destDir, 'service_3');
check($r['ok'] === false, '2.1 PHP script rejected: ' . ($r['error'] ?? ''));

file_put_contents($tmpDir . '/fake.jpg', "\xFF\xD8\xFF\xE0" . str_repeat('A', 100) . '<?php phpinfo();');
$r = ImageUpload::store($fake($tmpDir . '/fake.jpg'), $destDir, 'service_3');
check($r['ok'] === false, '2.2 JPEG magic bytes without a real image rejected');

// Polyglot: valid JPEG with PHP appended — accepted, but payload stripped by re-encode
file_put_contents($tmpDir . '/polyglot.jpg', file_get_contents($srcJpg) . "\n<?php echo 'pwned'; ?>");
$r = ImageUpload::store($fake($tmpDir . '/polyglot.jpg'), $destDir, 'service_4');
check($r['ok'] === true, '2.3 Polyglot JPEG is still a valid image (accepted)');
$stored = isset($r['filename']) ? (string) file_get_contents($destDir . '/' . $r['filename']) : '';
check($stored !== '' && !str_contains($stored, '<?php'), '2.4 Re-encoding stripped the appended PHP payload');

// SVG (scriptable) rejected
file_put_contents($tmpDir . '/img.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$r = ImageUpload::store($fake($tmpDir . '/img.svg', 'img.svg'), $destDir, 'service_5');
check($r['ok'] === false, '2.5 SVG rejected');

// GIF rejected (not in allow-list)
$gif = imagecreatetruecolor(5, 5);
imagegif($gif, $tmpDir . '/a.gif');
$r = ImageUpload::store($fake($tmpDir . '/a.gif', 'a.gif'), $destDir, 'service_5');
check($r['ok'] === false, '2.6 GIF rejected (not in allow-list)');

// Oversize
$r = ImageUpload::store($fake($copy), $destDir, 'service_6', 1024);
check($r['ok'] === false && str_contains($r['error'] ?? '', 'size'), '2.7 Oversize upload rejected');

// Upload error codes
$r = ImageUpload::store(['tmp_name' => $copy, 'size' => 10, 'error' => UPLOAD_ERR_PARTIAL, 'name' => 'a.jpg'], $destDir, 'service_7');
check($r['ok'] === false, '2.8 Partial upload rejected');

// Without the test seam, non-uploaded files are rejected (is_uploaded_file)
ImageUpload::allowLocalFilesForTesting(false);
$r = ImageUpload::store($fake($copy), $destDir, 'service_8');
check($r['ok'] === false && ($r['error'] ?? '') === 'Invalid upload.', '2.9 Non-upload temp file rejected when seam is off');
ImageUpload::allowLocalFilesForTesting(true);

// deleteWithin only touches uploads/services
echo "\n--- Section 3: Safe deletion ---\n";
$publicDir = dirname(__DIR__) . '/public';
check(ImageUpload::deleteWithin('images/services/spa.jpg', $publicDir, 'uploads/services') === false && is_file($srcJpg), '3.1 Seeded images/ assets are never deleted');
check(ImageUpload::deleteWithin('uploads/services/../../index.php', $publicDir, 'uploads/services') === false && is_file($publicDir . '/index.php'), '3.2 Path traversal in stored path refused');
$victim = $publicDir . '/uploads/services/test_delete_' . bin2hex(random_bytes(3)) . '.jpg';
copy($srcJpg, $victim);
check(ImageUpload::deleteWithin('uploads/services/' . basename($victim), $publicDir, 'uploads/services') === true && !file_exists($victim), '3.3 Own upload deleted on replace/remove');

// 4. Web server refuses to execute anything in uploads/
echo "\n--- Section 4: Apache protection for public/uploads ---\n";
$htaccess = $publicDir . '/uploads/.htaccess';
check(is_file($htaccess) && str_contains((string) file_get_contents($htaccess), 'Require all denied'), '4.1 public/uploads/.htaccess present with deny rules');
check(trim((string) file_get_contents($htaccess)) === trim(ImageUpload::htaccessContents()), '4.2 Committed .htaccess matches ImageUpload::htaccessContents()');

$baseUrl = 'http://localhost/sksl/public';
$probe = @file_get_contents($baseUrl . '/', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]));
if ($probe === false) {
    echo "[SKIP] 4.3–4.5 Apache not reachable at {$baseUrl}\n";
} else {
    $evil = $publicDir . '/uploads/services/evil_' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($evil, '<?php echo "EXECUTED";');
    $probeJpg = $publicDir . '/uploads/services/probe_' . bin2hex(random_bytes(3)) . '.jpg';
    copy($srcJpg, $probeJpg);
    $http = static function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 5]);
        $res = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [$code, substr($res, 0, $hs), substr($res, $hs)];
    };
    [$c1, , $b1] = $http($baseUrl . '/uploads/services/' . basename($evil));
    check($c1 === 403 && !str_contains($b1, 'EXECUTED'), "4.3 PHP file in uploads is not executed (HTTP {$c1})");
    [$c2, $h2] = $http($baseUrl . '/uploads/services/' . basename($probeJpg));
    check($c2 === 200 && stripos($h2, 'Content-Type: image/jpeg') !== false && stripos($h2, 'nosniff') !== false, "4.4 Image in uploads served as image/jpeg with nosniff (HTTP {$c2})");
    [$c3] = $http($baseUrl . '/uploads/services/');
    check($c3 === 403, "4.5 Directory listing denied (HTTP {$c3})");
    @unlink($evil);
    @unlink($probeJpg);
}

// Cleanup
foreach (glob($destDir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($destDir);
foreach (glob($tmpDir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($tmpDir);

echo "\nResults: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo ">>> ALL UPLOAD HARDENING TESTS PASSED <<<\n";
} else {
    echo ">>> {$failCount} TESTS FAILED <<<\n";
    exit(1);
}
