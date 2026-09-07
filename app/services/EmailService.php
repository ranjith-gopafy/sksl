<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Service
 *
 * Uses PHPMailer with SMTP.
 * Safe fallback: If SMTP credentials are empty or during local testing when an SMTP server
 * is unavailable, safely logs the email to storage/logs/mail.log so development is never blocked.
 * Email failures never crash calling flows (such as confirmed bookings or password resets).
 */
class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('mail', []);
    }

    /**
     * Send Password Reset Link Email.
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): bool
    {
        $subject = 'Reset Your Password — Sara Kinetic Sports Lab';

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .tagline { font-size: 12px; color: #0284c7; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; }
        .btn { display: inline-block; background-color: #0284c7; color: #ffffff !important; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 24px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">SARA KINETIC SPORTS LAB</div>
        <div class="tagline">Recover. Recharge. Perform.</div>
        <h2>Password Reset Request</h2>
        <p>Hello {$toName},</p>
        <p>We received a request to reset the password for your SKSL account. Click the button below to choose a new password:</p>
        <p><a href="{$resetUrl}" class="btn">Reset My Password</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #0284c7; font-size: 13px;">{$resetUrl}</p>
        <p class="footer">
            This reset link is valid for <strong>1 hour</strong> and can only be used once.<br>
            If you did not request this password reset, please ignore this email. Your account remains secure.
        </p>
    </div>
</body>
</html>
HTML;

        $altBody = "Hello {$toName},\n\nWe received a request to reset your SKSL account password. Use this link within 1 hour:\n{$resetUrl}\n\nIf you did not request this, please ignore this email.";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $altBody);
    }

    /**
     * Send Admin Login OTP Email.
     */
    public function sendAdminOtp(string $toEmail, string $otp): bool
    {
        $subject = "Your SKSL Admin Login OTP: {$otp}";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .tagline { font-size: 12px; color: #0284c7; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; }
        .otp-box { background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 18px; text-align: center; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #0f172a; margin: 24px 0; }
        .footer { margin-top: 24px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">SARA KINETIC SPORTS LAB</div>
        <div class="tagline">Admin Access Control</div>
        <h2>Admin Authentication Code</h2>
        <p>Use the following 6-digit one-time passcode to sign in to the SKSL Admin Portal:</p>
        <div class="otp-box">{$otp}</div>
        <p class="footer">
            This code expires in <strong>5 minutes</strong> and is strictly for authorized personnel only.<br>
            If you did not request this login code, no action is required.
        </p>
    </div>
</body>
</html>
HTML;

        $altBody = "SKSL Admin Login Code: {$otp}\nValid for 5 minutes. Do not share this code.";

        return $this->send($toEmail, 'SKSL Admin', $subject, $htmlBody, $altBody);
    }

    /**
     * Internal email sender. Dispatches via SMTP or falls back safely to logging.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody = '',
        ?string $attachmentPath = null,
        ?string $attachmentName = null
    ): bool {
        $smtpHost = trim((string) ($this->config['host'] ?? ''));

        // If SMTP is not configured or in local dev without live host, log safely to storage/logs/mail.log
        if ($smtpHost === '') {
            $this->logMail($toEmail, $subject, $htmlBody, $altBody, $attachmentPath);
            return true;
        }

        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = !empty($this->config['username']);
            $mail->Username   = (string) ($this->config['username'] ?? '');
            $mail->Password   = (string) ($this->config['password'] ?? '');
            $mail->SMTPSecure = ($this->config['encryption'] ?? 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) ($this->config['port'] ?? 587);
            $mail->CharSet    = 'UTF-8';

            // Recipients
            $fromAddress = $this->config['from_address'] ?: 'noreply@sksl.in';
            $fromName    = $this->config['from_name'] ?: 'Sara Kinetic Sports Lab';
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toEmail, $toName);

            // Attachments
            if ($attachmentPath && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("EmailService: Failed to send email to {$toEmail}: " . $e->getMessage());
            // Fall back to log file so email content is still reviewable
            $this->logMail($toEmail, $subject, $htmlBody, $altBody, $attachmentPath);
            return false;
        }
    }

    private function logMail(string $toEmail, string $subject, string $htmlBody, string $altBody, ?string $attachmentPath): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/mail.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "============================================================\n"
               . "TIMESTAMP: {$timestamp}\n"
               . "TO:        {$toEmail}\n"
               . "SUBJECT:   {$subject}\n"
               . ($attachmentPath ? "ATTACHMENT: {$attachmentPath}\n" : '')
               . "BODY (PLAIN):\n" . ($altBody ?: strip_tags($htmlBody)) . "\n"
               . "============================================================\n\n";

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
