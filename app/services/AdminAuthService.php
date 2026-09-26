<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminModel;
use App\Helpers\Csrf;
use App\Helpers\RateLimit;

/**
 * Admin Authentication Service
 *
 * Implements passwordless 6-digit Email OTP authentication for administrators.
 * OTPs expire in 5 minutes, are single-use, and rate-limited.
 */
class AdminAuthService
{
    private AdminModel $adminModel;
    private EmailService $emailService;

    public function __construct(
        ?AdminModel $adminModel = null,
        ?EmailService $emailService = null
    ) {
        $this->adminModel   = $adminModel ?? new AdminModel();
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Request a 6-digit OTP for admin login.
     *
     * @return array{success: bool, message: string}
     */
    public function requestOtp(string $email): array
    {
        $cleanEmail = strtolower(trim($email));

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        // Rate limit (DB-backed): 5 OTP requests per email and 10 per IP, per 15 minutes
        if (RateLimit::throttle('admin_otp_req', $cleanEmail, 5, 10, 900) > 0) {
            return [
                'success' => false,
                'message' => 'Too many login attempts. Please wait 15 minutes before requesting another code.',
            ];
        }

        $admin = $this->adminModel->findByEmail($cleanEmail);

        if ($admin) {
            // Per-account OTP cap (5 per 15 minutes). Respond exactly like an
            // unknown address so the cap cannot be used to confirm admin emails.
            if ($this->adminModel->countRecentOtps((int) $admin['id'], 15) >= 5) {
                error_log('Admin OTP cap reached for admin #' . (int) $admin['id']);
                return [
                    'success' => true,
                    'message' => 'If your email is registered as an administrator, a 6-digit verification code has been dispatched.',
                ];
            }

            // Generate cryptographically secure 6-digit numeric OTP
            $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $otpHashed = password_hash($otp, PASSWORD_BCRYPT);
            $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes validity

            $this->adminModel->createOtp((int) $admin['id'], $otpHashed, $expiresAt);
            $this->emailService->sendAdminOtp($admin['email'], $otp);
        }

        // Generic response prevents admin email enumeration
        return [
            'success' => true,
            'message' => 'If your email is registered as an administrator, a 6-digit verification code has been dispatched.',
        ];
    }

    /**
     * Verify submitted 6-digit OTP and establish authenticated admin session.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $cleanEmail = strtolower(trim($email));
        $cleanOtp   = trim($otp);

        if (!preg_match('/^\d{6}$/', $cleanOtp)) {
            return ['success' => false, 'message' => 'Verification code must be exactly 6 digits.'];
        }

        // Failed verifications: 10 per email and 20 per IP lock for 15 minutes,
        // on top of the 5-attempt cap stored on each OTP row.
        if (RateLimit::retryAfter('admin_otp_verify', $cleanEmail) > 0) {
            return ['success' => false, 'message' => 'Too many verification attempts. Please wait 15 minutes and request a new code.'];
        }

        $admin = $this->adminModel->findByEmail($cleanEmail);
        if (!$admin) {
            RateLimit::recordFailure('admin_otp_verify', $cleanEmail, 10, 20, 900);
            return ['success' => false, 'message' => 'Invalid or expired verification code.'];
        }

        $otpRecord = $this->adminModel->getLatestValidOtp((int) $admin['id']);
        if (!$otpRecord) {
            return ['success' => false, 'message' => 'Verification code has expired or was not found. Please request a new code.'];
        }

        // Lock out after 5 incorrect attempts
        if ((int) $otpRecord['attempts'] >= 5) {
            $this->adminModel->markOtpUsed((int) $otpRecord['id']);
            return ['success' => false, 'message' => 'Maximum verification attempts exceeded. Please request a new code.'];
        }

        if (!password_verify($cleanOtp, $otpRecord['otp_hash'])) {
            $this->adminModel->incrementAttempts((int) $otpRecord['id']);
            RateLimit::recordFailure('admin_otp_verify', $cleanEmail, 10, 20, 900);
            return ['success' => false, 'message' => 'Incorrect verification code. Please check and try again.'];
        }

        // Consume OTP (single use) and reset the per-account verify counter
        $this->adminModel->markOtpUsed((int) $otpRecord['id']);
        RateLimit::clear(RateLimit::key('admin_otp_verify', $cleanEmail));

        // Establish admin session (fresh id + CSRF token; drop any customer identity —
        // a browser is either an administrator or a customer, never both)
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Csrf::rotate();
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_mobile'], $_SESSION['auth_at'], $_SESSION['intended_url']);

        $_SESSION['admin_id']    = (int) $admin['id'];
        $_SESSION['admin_name']  = (string) $admin['name'];
        $_SESSION['admin_email'] = (string) $admin['email'];

        return [
            'success' => true,
            'message' => 'Admin authorization confirmed. Welcome to SKSL Management.',
        ];
    }

    /**
     * Terminate admin session.
     */
    public function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Csrf::rotate();
    }
}
