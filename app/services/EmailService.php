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
     * Send Customer Booking Confirmation with attached Tax Invoice PDF.
     *
     * @param array<string, mixed> $booking
     */
    public function sendBookingConfirmation(array $booking, ?string $invoicePdfPath = null): bool
    {
        $toEmail = (string) ($booking['user_email'] ?? '');
        $toName  = (string) ($booking['user_name'] ?? 'Athlete');
        $ref     = (string) ($booking['booking_reference'] ?? '');
        $service = (string) ($booking['service_name'] ?? 'Recovery Session');
        $date    = date('l, d F Y', strtotime((string) $booking['booking_date']));
        $time    = date('h:i A', strtotime((string) $booking['start_time'])) . ' – ' . date('h:i A', strtotime((string) $booking['end_time'])) . ' IST';
        $amount  = number_format((float) ($booking['total_amount'] ?? 0), 2);

        $subject = "Confirmed: Your {$service} Session on {$date} ({$ref})";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); }
        .brand { font-size: 20px; font-weight: 900; color: #38bdf8; letter-spacing: 0.5px; margin-bottom: 2px; }
        .tagline { font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 24px; }
        .badge { display: inline-block; background-color: #064e3b; color: #34d399; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 12px; border-radius: 9999px; letter-spacing: 1px; margin-bottom: 12px; }
        .title { font-size: 24px; font-weight: 800; color: #ffffff; margin: 0 0 12px 0; }
        .session-box { background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 24px 0; }
        .checklist { margin: 24px 0; font-size: 12px; color: #cbd5e1; line-height: 1.6; }
        .checklist strong { color: #38bdf8; }
        .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #334155; font-size: 11px; color: #64748b; line-height: 1.5; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">SARA KINETIC SPORTS LAB</div>
        <div class="tagline">Recover. Recharge. Perform.</div>
        
        <div>
            <span class="badge">&bull; Session Confirmed &amp; Paid</span>
            <h1 class="title">Get Ready to Recover, {$toName}</h1>
            <p style="color: #cbd5e1; font-size: 14px; margin-top: 4px;">
                Your recovery session has been locked in. We have generated your official GST Tax Invoice and attached it to this email.
            </p>
        </div>

        <div class="session-box">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <tr>
                    <td style="color: #94a3b8; padding: 8px 0; border-bottom: 1px solid #1e293b;">Booking Reference:</td>
                    <td style="color: #38bdf8; font-family: monospace; font-weight: 700; text-align: right; padding: 8px 0; border-bottom: 1px solid #1e293b;">{$ref}</td>
                </tr>
                <tr>
                    <td style="color: #94a3b8; padding: 8px 0; border-bottom: 1px solid #1e293b;">Modality:</td>
                    <td style="color: #ffffff; font-weight: 700; text-align: right; padding: 8px 0; border-bottom: 1px solid #1e293b;">{$service}</td>
                </tr>
                <tr>
                    <td style="color: #94a3b8; padding: 8px 0; border-bottom: 1px solid #1e293b;">Appointment Date:</td>
                    <td style="color: #ffffff; font-weight: 600; text-align: right; padding: 8px 0; border-bottom: 1px solid #1e293b;">{$date}</td>
                </tr>
                <tr>
                    <td style="color: #94a3b8; padding: 8px 0; border-bottom: 1px solid #1e293b;">Time Window:</td>
                    <td style="color: #ffffff; font-weight: 600; text-align: right; padding: 8px 0; border-bottom: 1px solid #1e293b;">{$time}</td>
                </tr>
                <tr>
                    <td style="color: #94a3b8; padding: 8px 0;">Total Paid:</td>
                    <td style="color: #34d399; font-weight: 800; text-align: right; padding: 8px 0; font-size: 15px;">&#8377;{$amount}</td>
                </tr>
            </table>
        </div>

        <div class="checklist">
            <h3 style="color: #ffffff; font-size: 14px; margin-bottom: 8px;">Important Arrival Checklist:</h3>
            <p>&bull; <strong>Arrive 10 minutes early</strong> for facility check-in and acclimation.</p>
            <p>&bull; <strong>Attire:</strong> Athletic compression gear or clean athletic swimwear.</p>
            <p>&bull; Fresh towels, hot showers, and private lockers are provided at our Chennai center.</p>
        </div>

        <div class="footer">
            Sara Kinetic Sports Lab &bull; Chennai, Tamil Nadu, India<br>
            Please show this confirmation email or your booking reference upon arrival.
        </div>
    </div>
</body>
</html>
HTML;

        $altBody = "Hello {$toName},\n\nYour session at Sara Kinetic Sports Lab is confirmed!\n\nBooking Ref: {$ref}\nModality: {$service}\nDate: {$date}\nTime: {$time}\nTotal Paid: INR {$amount}\n\nPlease arrive 10 minutes prior to your session.\nTax Invoice attached.";

        $attachmentName = $invoicePdfPath ? "SKSL_Invoice_{$ref}.pdf" : null;

        return $this->send($toEmail, $toName, $subject, $htmlBody, $altBody, $invoicePdfPath, $attachmentName);
    }

    /**
     * Send Admin Alert when new booking is confirmed.
     *
     * @param array<string, mixed> $booking
     */
    public function sendAdminBookingNotification(array $booking): bool
    {
        $adminEmail = trim((string) ($this->config['admin_email'] ?? ''));
        if ($adminEmail === '') {
            $adminEmail = 'admin@sk-sports-lab.com';
        }

        $ref     = (string) ($booking['booking_reference'] ?? '');
        $service = (string) ($booking['service_name'] ?? '');
        $custName= (string) ($booking['user_name'] ?? '');
        $custMail= (string) ($booking['user_email'] ?? '');
        $custMob = (string) ($booking['user_mobile'] ?? '');
        $date    = (string) ($booking['booking_date'] ?? '');
        $time    = (string) ($booking['start_time'] ?? '') . ' - ' . (string) ($booking['end_time'] ?? '');
        $amount  = number_format((float) ($booking['total_amount'] ?? 0), 2);

        $subject = "[New Booking] {$service} — {$custName} ({$ref})";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; background-color: #f8fafc; color: #0f172a; padding: 20px; }
        .card { max-width: 500px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 12px; }
        td { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; }
        .label { color: #64748b; width: 40%; }
        .val { font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top: 0; color: #0284c7;">New Booking Confirmed</h2>
        <p>A new customer booking has been confirmed and paid:</p>
        <table>
            <tr><td class="label">Reference:</td><td class="val">{$ref}</td></tr>
            <tr><td class="label">Modality:</td><td class="val">{$service}</td></tr>
            <tr><td class="label">Athlete:</td><td class="val">{$custName}</td></tr>
            <tr><td class="label">Email:</td><td class="val">{$custMail}</td></tr>
            <tr><td class="label">Mobile:</td><td class="val">{$custMob}</td></tr>
            <tr><td class="label">Date:</td><td class="val">{$date}</td></tr>
            <tr><td class="label">Time:</td><td class="val">{$time} IST</td></tr>
            <tr><td class="label">Amount:</td><td class="val">&#8377;{$amount}</td></tr>
        </table>
    </div>
</body>
</html>
HTML;

        $altBody = "New Booking Confirmed: {$ref}\nModality: {$service}\nCustomer: {$custName} ({$custMail}, {$custMob})\nDate: {$date} {$time}\nAmount: INR {$amount}";

        return $this->send($adminEmail, 'SKSL Admin', $subject, $htmlBody, $altBody);
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
