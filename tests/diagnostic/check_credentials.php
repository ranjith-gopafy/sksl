<?php

/**
 * SKSL — Credentials & Services Diagnostic
 *
 * Run from project root with XAMPP running:
 *   php tests/diagnostic/check_credentials.php
 *
 * Tests:
 *   1. Environment variables are loaded and populated
 *   2. MySQL database connection
 *   3. Razorpay API connectivity (test key)
 *   4. SMTP connection (no email sent)
 *
 * SAFE: Never prints secret values. Only prints "configured" / "not configured" / pass/fail.
 */

declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────────────────
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
try {
    $dotenv->load();
} catch (\Throwable $e) {
    echo "❌  FATAL: Cannot load .env — " . $e->getMessage() . "\n";
    exit(1);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function pass(string $label, string $detail = ''): void
{
    echo "  ✅  " . $label . ($detail ? " — {$detail}" : '') . "\n";
}

function fail(string $label, string $reason = ''): void
{
    echo "  ❌  " . $label . ($reason ? " — {$reason}" : '') . "\n";
}

function warn(string $label, string $reason = ''): void
{
    echo "  ⚠️   " . $label . ($reason ? " — {$reason}" : '') . "\n";
}

function configured(string $key): bool
{
    $val = trim((string) ($_ENV[$key] ?? ''));
    return $val !== '';
}

function masked(string $key): string
{
    $val = trim((string) ($_ENV[$key] ?? ''));
    if ($val === '') {
        return '(empty)';
    }
    if (strlen($val) <= 8) {
        return str_repeat('*', strlen($val));
    }
    return substr($val, 0, 4) . str_repeat('*', strlen($val) - 8) . substr($val, -4);
}

// ─── Header ───────────────────────────────────────────────────────────────────
echo "\n";
echo "============================================================\n";
echo "  SKSL — Credential & Service Diagnostic\n";
echo "  " . date('Y-m-d H:i:s T') . "\n";
echo "============================================================\n\n";

$allPassed = true;

// ─── Section 1: Environment File ──────────────────────────────────────────────
echo "[ 1 ] ENVIRONMENT FILE (.env)\n";

$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    pass('.env file found', $envFile);
} else {
    fail('.env file NOT found', 'Create it from .env.example');
    $allPassed = false;
}

$appUrl = $_ENV['APP_URL'] ?? '';
if ($appUrl !== '') {
    pass('APP_URL', $appUrl);
} else {
    warn('APP_URL is not set', 'Set APP_URL=http://localhost/sksl/public');
}

$appSecret = trim((string) ($_ENV['APP_SECRET'] ?? ''));
if ($appSecret !== '' && $appSecret !== 'local-dev-secret-change-in-production') {
    pass('APP_SECRET is configured');
} else {
    warn('APP_SECRET uses default placeholder', 'Change before production deployment');
}

echo "\n";

// ─── Section 2: Database Connection ───────────────────────────────────────────
echo "[ 2 ] DATABASE CONNECTION (MySQL)\n";

$dbHost     = $_ENV['DB_HOST']     ?? '127.0.0.1';
$dbPort     = $_ENV['DB_PORT']     ?? '3306';
$dbDatabase = $_ENV['DB_DATABASE'] ?? 'sksl';
$dbUsername = $_ENV['DB_USERNAME'] ?? 'root';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

pass('DB_HOST', $dbHost);
pass('DB_DATABASE', $dbDatabase);
pass('DB_USERNAME', $dbUsername);

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    // Verify key tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = ['users', 'services', 'bookings', 'booking_holds', 'payments', 'admins', 'hero_banners'];
    $missingTables = array_diff($requiredTables, $tables);

    pass('MySQL connection established', "Host: {$dbHost}:{$dbPort}");
    pass('Database "' . $dbDatabase . '" exists', count($tables) . ' tables found');

    if (empty($missingTables)) {
        pass('All required tables present');
    } else {
        fail('Missing tables', implode(', ', $missingTables));
        $allPassed = false;
    }

    // Quick data check
    $serviceCount = (int) $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $adminCount   = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    pass("Services seeded", "{$serviceCount} modalities");
    if ($adminCount > 0) {
        pass("Admin account configured", "{$adminCount} admin(s)");
    } else {
        fail("No admin account found", "Run: php database/seeds/seed_admin.php");
        $allPassed = false;
    }
} catch (\PDOException $e) {
    fail('MySQL connection FAILED', $e->getMessage());
    fail('Cannot continue without database', 'Start XAMPP MySQL service and try again');
    $allPassed = false;
}

echo "\n";

// ─── Section 3: Razorpay ──────────────────────────────────────────────────────
echo "[ 3 ] RAZORPAY PAYMENT GATEWAY\n";

$rpKeyId  = trim((string) ($_ENV['RAZORPAY_KEY_ID']     ?? ''));
$rpSecret = trim((string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? ''));
$rpWebhook= trim((string) ($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? ''));

if ($rpKeyId === '') {
    warn('RAZORPAY_KEY_ID is empty', 'App will use MOCK mode — real payments disabled');
} elseif (str_starts_with($rpKeyId, 'rzp_test_')) {
    pass('RAZORPAY_KEY_ID configured (test mode)', masked('RAZORPAY_KEY_ID'));
} elseif (str_starts_with($rpKeyId, 'rzp_live_')) {
    pass('RAZORPAY_KEY_ID configured (LIVE mode)', masked('RAZORPAY_KEY_ID'));
} else {
    fail('RAZORPAY_KEY_ID format unexpected', 'Expected rzp_test_... or rzp_live_...');
    $allPassed = false;
}

if ($rpSecret === '') {
    warn('RAZORPAY_KEY_SECRET is empty');
} else {
    pass('RAZORPAY_KEY_SECRET configured', masked('RAZORPAY_KEY_SECRET'));
}

if ($rpWebhook === '') {
    // PaymentController::webhook() refuses every webhook without a configured
    // secret, so an empty value means paid bookings are never confirmed server-side.
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        fail('RAZORPAY_WEBHOOK_SECRET is empty', 'Webhooks are rejected without it — bookings paid outside the browser flow stay pending');
        $allPassed = false;
    } else {
        warn('RAZORPAY_WEBHOOK_SECRET is empty', 'All incoming webhooks will be rejected (fine for local dev without webhooks)');
    }
} else {
    pass('RAZORPAY_WEBHOOK_SECRET configured');
}

// Test API connectivity if keys exist
if ($rpKeyId !== '' && $rpSecret !== '') {
    try {
        $api = new \Razorpay\Api\Api($rpKeyId, $rpSecret);
        // Lightweight call — fetch 1 order to confirm key validity
        $orders = $api->order->all(['count' => 1]);
        pass('Razorpay API connectivity', 'Successfully authenticated with Razorpay API');
    } catch (\Throwable $e) {
        $errMsg = $e->getMessage();
        if (str_contains($errMsg, 'Authentication')) {
            fail('Razorpay API auth failed', 'Check KEY_ID and KEY_SECRET values');
        } elseif (str_contains($errMsg, 'cURL')) {
            fail('Razorpay API network error', 'Check internet connectivity');
        } else {
            fail('Razorpay API error', $errMsg);
        }
        $allPassed = false;
    }
} else {
    warn('Skipping Razorpay API connectivity test', 'Keys not configured — mock mode active');
}

echo "\n";

// ─── Section 4: SMTP Email ────────────────────────────────────────────────────
echo "[ 4 ] SMTP EMAIL (PHPMailer)\n";

$smtpHost     = trim((string) ($_ENV['SMTP_HOST']         ?? ''));
$smtpPort     = trim((string) ($_ENV['SMTP_PORT']         ?? '587'));
$smtpUsername = trim((string) ($_ENV['SMTP_USERNAME']     ?? ''));
$smtpPassword = trim((string) ($_ENV['SMTP_PASSWORD']     ?? ''));
$smtpEnc      = trim((string) ($_ENV['SMTP_ENCRYPTION']   ?? 'tls'));
$fromAddress  = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
$adminEmail   = trim((string) ($_ENV['ADMIN_EMAIL']       ?? ''));

if ($smtpHost === '') {
    warn('SMTP_HOST is empty', 'Emails will be logged to storage/logs/mail.log only');
} else {
    pass('SMTP_HOST configured', $smtpHost);
}

if ($smtpUsername === '') {
    warn('SMTP_USERNAME is empty');
} else {
    pass('SMTP_USERNAME configured', masked('SMTP_USERNAME'));
}

if ($smtpPassword === '') {
    warn('SMTP_PASSWORD is empty');
} else {
    pass('SMTP_PASSWORD configured');
}

pass('SMTP_PORT', $smtpPort);
pass('SMTP_ENCRYPTION', $smtpEnc);

if ($fromAddress === '') {
    warn('MAIL_FROM_ADDRESS is empty', 'Set a valid email address for outgoing mail');
} else {
    pass('MAIL_FROM_ADDRESS', $fromAddress);
}

if ($adminEmail === '') {
    warn('ADMIN_EMAIL is empty', 'Admin notifications will not be delivered');
} else {
    pass('ADMIN_EMAIL', $adminEmail);
}

// SMTP connectivity test
if ($smtpHost !== '' && $smtpUsername !== '' && $smtpPassword !== '') {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsername;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = ($smtpEnc === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $smtpPort;
        $mail->Timeout    = 10;

        $mail->smtpConnect();
        $mail->smtpClose();
        pass('SMTP connection test', "Connected to {$smtpHost}:{$smtpPort} successfully");
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        fail('SMTP connection FAILED', $e->getMessage());
        $allPassed = false;
    }
} else {
    warn('Skipping SMTP connectivity test', 'SMTP credentials not fully configured');
}

echo "\n";

// ─── Section 5: File System ───────────────────────────────────────────────────
echo "[ 5 ] FILE SYSTEM & PERMISSIONS\n";

$checkDirs = [
    'storage/logs'        => 'Mail log directory',
    'storage/invoices'    => 'PDF invoice storage',
    'public/images'       => 'Public image directory',
    'public/css'          => 'Compiled CSS directory',
    'public/images/services' => 'Service modality images',
];

foreach ($checkDirs as $dir => $label) {
    $path = $projectRoot . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    if (is_dir($path) && is_writable($path)) {
        pass($label, $dir);
    } elseif (is_dir($path)) {
        fail($label . ' (not writable)', $dir);
        $allPassed = false;
    } else {
        fail($label . ' (missing)', $dir);
        $allPassed = false;
    }
}

// Check CSS compiled
$cssFile = $projectRoot . '/public/css/app.css';
if (file_exists($cssFile)) {
    pass('Compiled Tailwind CSS', 'public/css/app.css (' . round(filesize($cssFile) / 1024) . ' KB)');
} else {
    fail('Compiled CSS missing', 'Run: npm run build');
    $allPassed = false;
}

// Check service images
$imageDir = $projectRoot . '/public/images/services';
$images   = glob($imageDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
if ($images && count($images) >= 10) {
    pass('Service modality images', count($images) . ' images found');
} else {
    warn('Service images incomplete', (count($images ?? [])) . '/10 found in public/images/services/');
}

echo "\n";

// ─── Final Summary ────────────────────────────────────────────────────────────
echo "============================================================\n";
if ($allPassed) {
    echo "  🟢  ALL CRITICAL CHECKS PASSED — Ready to run tests!\n";
    echo "\n  Next step:\n";
    echo "    php tests/diagnostic/test_smtp_send.php   # Send test email\n";
    echo "    npm run test:e2e                           # Run Playwright E2E\n";
} else {
    echo "  🔴  SOME CHECKS FAILED — Fix issues above before running tests.\n";
    echo "\n  Common fixes:\n";
    echo "    - Fill in .env with SMTP and Razorpay credentials\n";
    echo "    - Start XAMPP Apache + MySQL\n";
    echo "    - Run: npm run build  (for Tailwind CSS)\n";
}
echo "============================================================\n\n";
