<?php

/**
 * SKSL — Live SMTP Send Test
 *
 * Run from project root:
 *   php tests/diagnostic/test_smtp_send.php
 *
 * Sends a real test email using .env SMTP credentials to verify
 * end-to-end email delivery before running full E2E tests.
 *
 * SAFE: Uses only configured credentials — never hard-codes secrets.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
try {
    $dotenv->load();
} catch (\Throwable $e) {
    echo "❌  FATAL: Cannot load .env — " . $e->getMessage() . "\n";
    exit(1);
}

$smtpHost     = trim((string) ($_ENV['SMTP_HOST']         ?? ''));
$smtpPort     = (int)         ($_ENV['SMTP_PORT']         ?? 587);
$smtpUsername = trim((string) ($_ENV['SMTP_USERNAME']     ?? ''));
$smtpPassword = trim((string) ($_ENV['SMTP_PASSWORD']     ?? ''));
$smtpEnc      = trim((string) ($_ENV['SMTP_ENCRYPTION']   ?? 'tls'));
$fromAddress  = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
$fromName     = trim((string) ($_ENV['MAIL_FROM_NAME']    ?? 'SKSL'));
$adminEmail   = trim((string) ($_ENV['ADMIN_EMAIL']       ?? ''));

echo "\n";
echo "============================================================\n";
echo "  SKSL — Live SMTP Email Send Test\n";
echo "  " . date('Y-m-d H:i:s T') . "\n";
echo "============================================================\n\n";

if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '') {
    echo "⚠️   SMTP credentials not configured in .env\n";
    echo "    Set SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD and try again.\n\n";
    exit(1);
}

if ($fromAddress === '') {
    echo "⚠️   MAIL_FROM_ADDRESS is not set in .env\n";
    echo "    A from-address is required to send email.\n\n";
    exit(1);
}

// Send to admin email if configured, otherwise self-send
$recipient = $adminEmail ?: $fromAddress;

echo "  From:       {$fromAddress}\n";
echo "  To:         {$recipient}\n";
echo "  SMTP Host:  {$smtpHost}:{$smtpPort}\n";
echo "  Encryption: {$smtpEnc}\n\n";

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
    $mail->Port       = $smtpPort;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;

    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($recipient, 'SKSL Test Recipient');

    $mail->isHTML(true);
    $mail->Subject = 'SKSL Email Delivery Test — ' . date('Y-m-d H:i:s');
    $mail->Body = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f8fafc; padding: 20px; margin: 0; }
    .card { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .brand { font-size: 22px; font-weight: 800; color: #075183; }
    .status { background: #dcfce7; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin: 20px 0; color: #166534; font-weight: 600; }
    .detail { font-size: 13px; color: #475569; margin: 4px 0; }
    .footer { font-size: 11px; color: #94a3b8; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">Sara Kinetic Sports Lab</div>
    <p style="color:#64748b;font-size:12px;margin-top:4px;">Recover. Recharge. Perform.</p>
    <div class="status">
      ✅ &nbsp;SMTP Email Delivery Test Passed
    </div>
    <p style="color:#1e293b;">This test email confirms that the SMTP email service is correctly configured and operational for SKSL.</p>
    <div class="detail">📅 <strong>Sent:</strong> ' . date('Y-m-d H:i:s T') . '</div>
    <div class="detail">📧 <strong>From:</strong> ' . htmlspecialchars($fromAddress) . '</div>
    <div class="detail">🔒 <strong>Encryption:</strong> ' . strtoupper($smtpEnc) . ' on port ' . $smtpPort . '</div>
    <div class="detail">🖥️ <strong>SMTP Host:</strong> ' . htmlspecialchars($smtpHost) . '</div>
    <p class="footer">This is an automated diagnostic email from the SKSL booking platform. If you received this, email delivery is working correctly.</p>
  </div>
</body>
</html>';

    $mail->AltBody = "SKSL Email Delivery Test\n\n"
        . "This test email confirms SMTP is correctly configured.\n\n"
        . "Sent: " . date('Y-m-d H:i:s T') . "\n"
        . "From: {$fromAddress}\n"
        . "Host: {$smtpHost}:{$smtpPort}\n";

    $mail->send();

    echo "  ✅  EMAIL SENT SUCCESSFULLY!\n\n";
    echo "  ✉️   Check your inbox at: {$recipient}\n";
    echo "  ⏱️   Delivery usually takes a few seconds.\n\n";
    echo "  SMTP is properly configured — email flows are operational.\n";
    echo "  You can now run the full E2E test suite:\n\n";
    echo "    npm run test:e2e\n\n";
} catch (\PHPMailer\PHPMailer\Exception $e) {
    echo "  ❌  EMAIL SEND FAILED\n\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    echo "  Troubleshooting:\n";
    echo "    • Verify SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD in .env\n";
    echo "    • Check if Less Secure Apps / App Passwords are required\n";
    echo "    • Try changing SMTP_PORT to 465 with SMTP_ENCRYPTION=ssl\n";
    echo "    • For Gmail: use an App Password (not your Google password)\n";
    echo "    • For Hostinger Titan Mail: use port 587 with TLS\n\n";
    exit(1);
}

echo "============================================================\n\n";
