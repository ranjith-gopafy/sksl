<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailLogModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Service
 *
 * Drivers (MAIL_DRIVER, or inferred from SMTP_HOST):
 *   smtp — PHPMailer over SMTP. Delivery failures are recorded in `email_logs`
 *          and the PHP error log *without* the message body (audit: OTPs and reset
 *          links used to be dumped to storage/logs/mail.log on failure).
 *   log  — development only: writes the full message to storage/logs/mail.log so
 *          local work is never blocked. Refused in production (recorded as failed).
 *
 * Every attempt (sent / failed / logged) is written to `email_logs` — recipient,
 * type, subject, outcome — never the body. Email failures never crash calling
 * flows (confirmed bookings, password resets).
 */
class EmailService
{
    public const DRIVER_SMTP = 'smtp';
    public const DRIVER_LOG  = 'log';

    private array $config;
    private string $driver;
    private ?EmailLogModel $logModel = null;

    public function __construct()
    {
        $this->config = config('mail', []);

        // $_ENV is read directly so CLI tools/tests can force the driver after bootstrap.
        $driver = strtolower(trim((string) ($_ENV['MAIL_DRIVER'] ?? ($this->config['driver'] ?? ''))));
        if ($driver === '') {
            $driver = trim((string) ($this->config['host'] ?? '')) !== '' ? self::DRIVER_SMTP : self::DRIVER_LOG;
        }
        $this->driver = $driver === self::DRIVER_LOG ? self::DRIVER_LOG : self::DRIVER_SMTP;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Send Password Reset Link Email.
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): bool
    {
        $subject  = 'Reset Your Password — Sara Kinetic Sports Lab';
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

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
        <p>Hello {$safeName},</p>
        <p>We received a request to reset the password for your SKSL account. Click the button below to choose a new password:</p>
        <p><a href="{$safeUrl}" class="btn">Reset My Password</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #0284c7; font-size: 13px;">{$safeUrl}</p>
        <p class="footer">
            This reset link is valid for <strong>1 hour</strong> and can only be used once.<br>
            If you did not request this password reset, please ignore this email. Your account remains secure.
        </p>
    </div>
</body>
</html>
HTML;

        $altBody = "Hello {$toName},\n\nWe received a request to reset your SKSL account password. Use this link within 1 hour:\n{$resetUrl}\n\nIf you did not request this, please ignore this email.";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $altBody, null, null, 'password_reset');
    }

    /**
     * Sent to the OWNER of an existing account when someone tries to register
     * with their email again. The registrant sees a generic response, so this
     * is the only place the duplicate is disclosed — to the rightful owner.
     */
    public function sendExistingAccountNotice(string $toEmail, string $toName): bool
    {
        $subject   = 'You already have an SKSL account — Sara Kinetic Sports Lab';
        $safeName  = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $loginUrl  = app_url('login');
        $forgotUrl = app_url('forgot-password');

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
        .btn { display: inline-block; background-color: #0284c7; color: #ffffff !important; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 16px 8px 16px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">SARA KINETIC SPORTS LAB</div>
        <div class="tagline">Recover. Recharge. Perform.</div>
        <h2>You already have an account</h2>
        <p>Hello {$safeName},</p>
        <p>Someone (probably you) just tried to create a new SKSL account with this email address. An account already exists, so nothing was changed.</p>
        <p>
            <a href="{$loginUrl}" class="btn">Sign In</a>
            <a href="{$forgotUrl}" class="btn" style="background-color:#475569;">Reset Password</a>
        </p>
        <p class="footer">
            If this wasn't you, no action is needed — your account and password are unchanged.
        </p>
    </div>
</body>
</html>
HTML;

        $altBody = "Hello {$toName},\n\nSomeone (probably you) tried to create a new SKSL account with this email address. An account already exists, so nothing was changed.\n\nSign in: {$loginUrl}\nForgot your password? {$forgotUrl}\n\nIf this wasn't you, no action is needed.";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $altBody, null, null, 'existing_account_notice');
    }

    /**
     * Send Admin Login OTP Email.
     * The code lives only in the body — never in the subject line, which is
     * visible in notification previews, mail logs and server-side delivery records.
     */
    public function sendAdminOtp(string $toEmail, string $otp): bool
    {
        $subject = 'Your SKSL Admin sign-in code';
        $otp     = preg_replace('/\D/', '', $otp) ?? '';

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

        return $this->send($toEmail, 'SKSL Admin', $subject, $htmlBody, $altBody, null, null, 'admin_otp');
    }

    /**
     * Send Customer Booking Confirmation with attached Tax Invoice PDF.
     *
     * @param array<string, mixed> $booking
     */
    public function sendBookingConfirmation(array $booking, ?string $invoicePdfPath = null): bool
    {
        $toEmail = (string) ($booking['user_email'] ?? '');
        $toNameRaw  = (string) ($booking['user_name'] ?? 'Athlete');
        $serviceRaw = (string) ($booking['service_name_snapshot'] ?: ($booking['service_name'] ?? 'Recovery Session'));
        $refRaw     = (string) ($booking['booking_reference'] ?? '');
        // Everything interpolated into the HTML body is escaped; the plain-text body uses the raw values.
        $toName  = htmlspecialchars($toNameRaw, ENT_QUOTES, 'UTF-8');
        $ref     = htmlspecialchars($refRaw, ENT_QUOTES, 'UTF-8');
        $service = htmlspecialchars($serviceRaw, ENT_QUOTES, 'UTF-8');
        $date    = date('l, d F Y', strtotime((string) $booking['booking_date']));
        $time    = date('h:i A', strtotime((string) $booking['start_time'])) . ' – ' . date('h:i A', strtotime((string) $booking['end_time'])) . ' IST';
        $amount  = number_format((float) ($booking['total_amount'] ?? 0), 2);
        $myBookingsUrl = htmlspecialchars(app_url('my-bookings'), ENT_QUOTES, 'UTF-8');
        $business = (array) config('business', []);
        $footerLine = htmlspecialchars(trim(($business['trade_name'] ?? 'Sara Kinetic Sports Lab') . ' • ' . ($business['city'] ?? 'Bengaluru') . ', ' . ($business['state_name'] ?? 'Karnataka') . ', India'), ENT_QUOTES, 'UTF-8');

        $subject = "Confirmed: Your {$serviceRaw} Session on {$date} ({$refRaw})";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — Sara Kinetic Sports Lab</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; padding: 32px 16px; }
        .wrapper { max-width: 600px; margin: 0 auto; }

        /* Header */
        .header { background-color: #053d63; border-radius: 16px 16px 0 0; padding: 28px 32px; }
        .brand-name { font-size: 18px; font-weight: 900; color: #ffffff; letter-spacing: 0.5px; }
        .brand-tagline { font-size: 11px; color: #93c5fd; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 3px; }

        /* Card */
        .card { background: #ffffff; border-radius: 0 0 16px 16px; padding: 32px; border: 1px solid #e2e8f0; border-top: none; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }

        /* Confirmed badge */
        .badge { display: inline-flex; align-items: center; gap: 6px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 5px 12px; border-radius: 9999px; margin-bottom: 16px; }
        .badge-dot { width: 7px; height: 7px; background: #22c55e; border-radius: 50%; display: inline-block; }

        /* Heading */
        .heading { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .subtext { font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }

        /* Session details box */
        .session-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin: 24px 0; }
        .session-box table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .session-box td { padding: 11px 16px; }
        .session-box tr:not(:last-child) td { border-bottom: 1px solid #e2e8f0; }
        .label-col { color: #64748b; font-weight: 500; width: 45%; }
        .value-col { color: #0f172a; font-weight: 700; text-align: right; }
        .ref-value { color: #075183; font-family: monospace; font-size: 14px; }
        .total-row td { background: #f0f9ff; }
        .total-label { color: #0f172a; font-weight: 700; font-size: 14px; }
        .total-value { color: #16a34a; font-weight: 800; font-size: 15px; text-align: right; }

        /* Checklist */
        .checklist { margin: 24px 0; }
        .checklist-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; border-left: 3px solid #053d63; padding-left: 10px; }
        .checklist-item { display: flex; gap: 10px; align-items: flex-start; font-size: 12px; color: #475569; line-height: 1.6; margin-bottom: 8px; }
        .checklist-dot { width: 6px; height: 6px; background: #053d63; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .checklist-item strong { color: #1e293b; }

        /* CTA Button */
        .cta-wrap { text-align: center; margin: 28px 0 0; }
        .cta-btn { display: inline-block; background-color: #053d63; color: #ffffff !important; padding: 13px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; }

        /* Footer */
        .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; line-height: 1.6; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header -->
        <div class="header">
            <div class="brand-name">SARA KINETIC SPORTS LAB</div>
            <div class="brand-tagline">Recover &bull; Recharge &bull; Perform</div>
        </div>

        <!-- Card Body -->
        <div class="card">
            <div class="badge"><span class="badge-dot"></span> Session Confirmed &amp; Paid</div>
            <h1 class="heading">Get Ready to Recover, {$toName}!</h1>
            <p class="subtext">
                Your recovery session has been successfully confirmed and payment received. Your official GST Tax Invoice is attached to this email for your records.
            </p>

            <!-- Session Details Table -->
            <div class="session-box">
                <table>
                    <tr>
                        <td class="label-col">Booking Reference</td>
                        <td class="value-col ref-value">{$ref}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Modality</td>
                        <td class="value-col">{$service}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Appointment Date</td>
                        <td class="value-col">{$date}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Time Window</td>
                        <td class="value-col">{$time}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label-col total-label">Total Paid</td>
                        <td class="total-value">&#8377;{$amount}</td>
                    </tr>
                </table>
            </div>

            <!-- Arrival Checklist -->
            <div class="checklist">
                <div class="checklist-title">Important Arrival Checklist</div>
                <div class="checklist-item">
                    <span class="checklist-dot"></span>
                    <span><strong>Arrive 10 minutes early</strong> for facility check-in and thermal acclimation.</span>
                </div>
                <div class="checklist-item">
                    <span class="checklist-dot"></span>
                    <span><strong>Attire:</strong> Athletic compression gear or clean athletic swimwear. Fresh towels and private lockers are provided.</span>
                </div>
                <div class="checklist-item">
                    <span class="checklist-dot"></span>
                    <span><strong>Hydration:</strong> Please ensure you are adequately hydrated before hot sauna or cold plunge sessions.</span>
                </div>
                <div class="checklist-item">
                    <span class="checklist-dot"></span>
                    <span>Present this email or your <strong>Booking Reference ({$ref})</strong> at the reception desk on arrival.</span>
                </div>
            </div>

            <!-- CTA -->
            <div class="cta-wrap">
                <a href="{$myBookingsUrl}" class="cta-btn">View My Bookings</a>
            </div>

            <!-- Footer -->
            <div class="footer">
                {$footerLine}<br>
                This is an automated confirmation. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        $altBody = "Hello {$toNameRaw},\n\nYour session at Sara Kinetic Sports Lab is confirmed!\n\nBooking Ref: {$refRaw}\nModality: {$serviceRaw}\nDate: {$date}\nTime: {$time}\nTotal Paid: INR {$amount}\n\nPlease arrive 10 minutes prior to your session.\nTax Invoice attached.";

        $attachmentName = $invoicePdfPath ? "SKSL_Invoice_{$refRaw}.pdf" : null;

        return $this->send($toEmail, $toNameRaw, $subject, $htmlBody, $altBody, $invoicePdfPath, $attachmentName, 'booking_confirmation', isset($booking['id']) ? (int) $booking['id'] : null);
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
            error_log('EmailService: ADMIN_EMAIL not configured; admin booking notification not sent.');
            return false;
        }

        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $ref     = $e((string) ($booking['booking_reference'] ?? ''));
        $service = $e((string) ($booking['service_name_snapshot'] ?: ($booking['service_name'] ?? '')));
        $custName= $e((string) ($booking['user_name'] ?? ''));
        $custMail= $e((string) ($booking['user_email'] ?? ''));
        $custMob = $e((string) ($booking['user_mobile'] ?? ''));
        $date    = (string) ($booking['booking_date'] ?? '');
        $time    = (string) ($booking['start_time'] ?? '') . ' - ' . (string) ($booking['end_time'] ?? '');
        $amount  = number_format((float) ($booking['total_amount'] ?? 0), 2);

        $subject = '[New Booking] ' . (string) ($booking['service_name_snapshot'] ?: ($booking['service_name'] ?? ''))
                 . ' — ' . (string) ($booking['user_name'] ?? '') . ' (' . (string) ($booking['booking_reference'] ?? '') . ')';

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

        return $this->send($adminEmail, 'SKSL Admin', $subject, $htmlBody, $altBody, null, null, 'admin_new_booking', isset($booking['id']) ? (int) $booking['id'] : null);
    }

    /**
     * Alert staff that a payment was captured for a booking that is no longer
     * pending (e.g. cancelled before the gateway callback arrived). Refund needed.
     *
     * @param array<string, mixed> $booking
     */
    public function sendAdminPaymentAfterCloseAlert(array $booking, string $paymentId): bool
    {
        $adminEmail = trim((string) ($this->config['admin_email'] ?? ''));
        if ($adminEmail === '') {
            error_log('EmailService: ADMIN_EMAIL not configured; payment-after-close alert not sent.');
            return false;
        }

        $ref     = htmlspecialchars((string) ($booking['booking_reference'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status  = htmlspecialchars((string) ($booking['booking_status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name    = htmlspecialchars((string) ($booking['user_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mail    = htmlspecialchars((string) ($booking['user_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount  = number_format((float) ($booking['total_amount'] ?? 0), 2);
        $payId   = htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8');

        $subject = "[Action Required] Payment received for {$status} booking {$ref}";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #0f172a;">
    <h2 style="color: #b91c1c;">Refund required</h2>
    <p>A payment was captured for a booking that is <strong>{$status}</strong>. The slot was not re-opened.</p>
    <table style="font-size: 13px;">
        <tr><td>Reference</td><td><strong>{$ref}</strong></td></tr>
        <tr><td>Customer</td><td>{$name} ({$mail})</td></tr>
        <tr><td>Amount</td><td>&#8377;{$amount}</td></tr>
        <tr><td>Razorpay Payment ID</td><td>{$payId}</td></tr>
    </table>
    <p>Please process the refund in the Razorpay dashboard and inform the customer.</p>
</body></html>
HTML;

        $altBody = "Refund required.\nBooking {$ref} is {$status} but payment {$paymentId} was captured.\nCustomer: {$name} ({$mail})\nAmount: INR {$amount}";

        return $this->send($adminEmail, 'SKSL Admin', $subject, $htmlBody, $altBody, null, null, 'admin_payment_after_close', isset($booking['id']) ? (int) $booking['id'] : null);
    }

    /**
     * The address outbound mail is sent from. MAIL_FROM_ADDRESS, or the SMTP
     * username when that is itself an email address. No made-up fallback domain:
     * an unverifiable sender is the fastest way to land in spam or be rejected.
     */
    public function fromAddress(): string
    {
        $from = trim((string) ($this->config['from_address'] ?? ''));
        if ($from === '') {
            $user = trim((string) ($this->config['username'] ?? ''));
            if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
                $from = $user;
            }
        }
        return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : '';
    }

    /**
     * Internal email sender. Every attempt is recorded in `email_logs`.
     *
     * @param string   $emailType short machine label stored in email_logs.email_type
     * @param int|null $bookingId related booking (for booking emails)
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody = '',
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        string $emailType = 'generic',
        ?int $bookingId = null
    ): bool {
        // Header hygiene: no CR/LF in a subject that partially comes from user data
        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
        $toEmail = trim($toEmail);

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->record($toEmail, $emailType, EmailLogModel::STATUS_FAILED, $subject, 'Invalid recipient address', $bookingId);
            error_log("EmailService: refusing to send '{$emailType}' — invalid recipient address.");
            return false;
        }

        if ($this->driver === self::DRIVER_LOG) {
            if ((string) config('app.env') === 'production') {
                // Never dump message bodies (OTPs, reset links) to disk on a live server.
                $this->record($toEmail, $emailType, EmailLogModel::STATUS_FAILED, $subject, 'Mail driver is "log" / SMTP_HOST not configured in production', $bookingId);
                error_log("EmailService: '{$emailType}' to {$toEmail} NOT sent — configure SMTP_HOST (MAIL_DRIVER=log is refused in production).");
                return false;
            }
            $this->logMail($toEmail, $subject, $htmlBody, $altBody, $attachmentPath);
            $this->record($toEmail, $emailType, EmailLogModel::STATUS_LOGGED, $subject, null, $bookingId);
            return true;
        }

        $fromAddress = $this->fromAddress();
        if ($fromAddress === '') {
            $this->record($toEmail, $emailType, EmailLogModel::STATUS_FAILED, $subject, 'MAIL_FROM_ADDRESS not configured', $bookingId);
            error_log("EmailService: '{$emailType}' to {$toEmail} NOT sent — MAIL_FROM_ADDRESS is not configured.");
            return false;
        }

        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = trim((string) ($this->config['host'] ?? ''));
            $mail->SMTPAuth   = !empty($this->config['username']);
            $mail->Username   = (string) ($this->config['username'] ?? '');
            $mail->Password   = (string) ($this->config['password'] ?? '');
            $mail->SMTPSecure = ($this->config['encryption'] ?? 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) ($this->config['port'] ?? 587);
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 20;

            // Recipients
            $fromName = trim((string) ($this->config['from_name'] ?? '')) ?: 'Sara Kinetic Sports Lab';
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
            $this->record($toEmail, $emailType, EmailLogModel::STATUS_SENT, $subject, null, $bookingId);
            return true;
        } catch (PHPMailerException $e) {
            // Metadata only — the body (which may hold an OTP or a reset link) is never written anywhere.
            $error = trim($e->getMessage());
            $this->record($toEmail, $emailType, EmailLogModel::STATUS_FAILED, $subject, $error, $bookingId);
            error_log("EmailService: failed to send '{$emailType}' to {$toEmail}: {$error}");
            return false;
        }
    }

    private function record(string $recipient, string $type, string $status, ?string $subject, ?string $error, ?int $bookingId): void
    {
        try {
            $this->logModel ??= new EmailLogModel();
            $this->logModel->record($recipient, $type, $status, $subject, $error, $bookingId);
        } catch (\Throwable $e) {
            error_log('EmailService: email_logs unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Development driver: append the message to storage/logs/mail.log.
     * Only reachable outside production (see send()). The directory is denied
     * over HTTP by storage/.htaccess and the root .htaccess.
     */
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
