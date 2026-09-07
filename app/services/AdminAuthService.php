<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminModel;
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

        // Rate limit: 5 OTP requests per 15 minutes per IP + email
        $rateKey = 'admin_otp_req_' . md5($cleanEmail . '_' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (!RateLimit::attempt($rateKey, 5, 900)) {
            return [
                'success' => false,
                'message' => 'Too many login attempts. Please wait 15 minutes before requesting another code.',
            ];
        }

        $admin = $this->adminModel->findByEmail($cleanEmail);

        if ($admin) {
            // Database-backed rate limit check (5 OTPs per 15 minutes)
            if ($this->adminModel->countRecentOtps((int) $admin['id'], 15) >= 5) {
                return [
                    'success' => false,
                    'message' => 'Too many login attempts. Please wait 15 minutes before requesting another code.',
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

        $admin = $this->adminModel->findByEmail($cleanEmail);
        if (!$admin) {
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
            return ['success' => false, 'message' => 'Incorrect verification code. Please check and try again.'];
        }

        // Consume OTP (single use)
        $this->adminModel->markOtpUsed((int) $otpRecord['id']);

        // Establish admin session
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

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
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }
}
