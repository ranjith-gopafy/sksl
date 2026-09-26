<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Hardened image upload handling for admin-supplied pictures.
 *
 * Defence in depth:
 *  - the upload must be a real uploaded file with no PHP error and within size;
 *  - the content must decode as JPEG/PNG/WebP (getimagesize + finfo agree);
 *  - the stored extension is derived from the *detected* type, never from the
 *    client filename;
 *  - the stored name is random, so an attacker cannot predict or overwrite paths;
 *  - when GD is available the image is re-encoded, which strips embedded
 *    payloads / metadata; otherwise the bytes are moved as-is;
 *  - the target directory carries an .htaccess that disables script execution.
 */
final class ImageUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Detected IMAGETYPE_* => [extension, mime] */
    private const TYPES = [
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_PNG  => ['png', 'image/png'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    /** Largest pixel dimension accepted (guards against decompression bombs). */
    private const MAX_DIMENSION = 6000;

    /** Test seam: lets CLI tests feed ordinary files instead of PHP uploads. */
    private static bool $allowLocalFiles = false;

    public static function allowLocalFilesForTesting(bool $on = true): void
    {
        if (config('app.env') === 'production') {
            return;
        }
        self::$allowLocalFiles = $on;
    }

    /**
     * Validate and store an uploaded image.
     *
     * @param array<string, mixed> $file   One entry from $_FILES
     * @param string $destDir              Absolute directory to write into
     * @param string $prefix               Filename prefix (letters/digits/_ only)
     * @return array{ok: bool, filename?: string, error?: string}
     */
    public static function store(array $file, string $destDir, string $prefix, int $maxBytes = self::MAX_BYTES): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::errorMessage($error)];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || (!self::$allowLocalFiles && !is_uploaded_file($tmp))) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes || filesize($tmp) > $maxBytes) {
            return ['ok' => false, 'error' => 'Image size must not exceed ' . (int) ($maxBytes / 1024 / 1024) . ' MB.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            return ['ok' => false, 'error' => 'Invalid image. Only JPG, PNG and WebP files are accepted.'];
        }
        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return ['ok' => false, 'error' => 'Image dimensions must be between 1 and ' . self::MAX_DIMENSION . ' pixels.'];
        }
        [$ext, $expectedMime] = self::TYPES[$type];

        // Second opinion from libmagic; both detectors must agree.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($tmp);
        if ($detectedMime !== $expectedMime) {
            return ['ok' => false, 'error' => 'Image content does not match its type.'];
        }

        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) ?: 'img';
        $destDir = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['ok' => false, 'error' => 'Upload directory is not writable.'];
        }
        self::ensureHtaccess($destDir);

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target   = $destDir . $filename;

        if (!self::reencode($tmp, $target, $type)) {
            $moved = self::$allowLocalFiles ? copy($tmp, $target) : move_uploaded_file($tmp, $target);
            if (!$moved) {
                return ['ok' => false, 'error' => 'Failed to save uploaded image. Please try again.'];
            }
        }
        @chmod($target, 0644);

        return ['ok' => true, 'filename' => $filename];
    }

    /**
     * Delete a previously stored upload, but only if it lives inside $baseDir.
     * Returns true when nothing is left on disk.
     */
    public static function deleteWithin(string $relativePath, string $publicDir, string $allowedPrefix): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, rtrim($allowedPrefix, '/') . '/')) {
            return false; // seeded assets under images/ are never deleted
        }
        if (str_contains($relativePath, '..')) {
            return false;
        }
        $full = rtrim($publicDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($full)) {
            return @unlink($full);
        }
        return true;
    }

    /**
     * Re-encode via GD so that any non-image bytes (polyglot payloads, EXIF
     * scripts, etc.) are discarded. Returns false when GD is unavailable.
     */
    private static function reencode(string $src, string $target, int $type): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $data = file_get_contents($src);
        if ($data === false) {
            return false;
        }
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return false;
        }

        $ok = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $ok = imagejpeg($img, $target, 88);
                break;
            case IMAGETYPE_PNG:
                imagesavealpha($img, true);
                $ok = imagepng($img, $target, 6);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    imagesavealpha($img, true);
                    $ok = imagewebp($img, $target, 85);
                }
                break;
        }
        imagedestroy($img);

        if (!$ok && is_file($target)) {
            @unlink($target);
        }
        return $ok;
    }

    /** Make sure nothing in the upload directory can ever execute as a script. */
    private static function ensureHtaccess(string $destDir): void
    {
        $root = dirname(__DIR__, 2) . '/public/uploads/';
        $file = $root . '.htaccess';
        if (is_dir($root) && !is_file($file)) {
            @file_put_contents($file, self::htaccessContents());
        }
    }

    public static function htaccessContents(): string
    {
        return <<<HTACCESS
# Uploaded files are data, never code.
Options -Indexes -ExecCGI
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi .sh
RemoveType    .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi .sh
<FilesMatch "\\.(?i:php|phtml|php[0-9]|phar|pl|py|cgi|sh|htaccess|ini)$">
    Require all denied
</FilesMatch>
# Only serve the image types we accept
<FilesMatch "^(?!.*\\.(?i:jpe?g|png|webp)$).*$">
    Require all denied
</FilesMatch>
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set Content-Disposition "inline"
</IfModule>
HTACCESS;
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
            UPLOAD_ERR_PARTIAL => 'Image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No image was uploaded.',
            default => 'Image upload failed (code ' . $code . ').',
        };
    }
}
