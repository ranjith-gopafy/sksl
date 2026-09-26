<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Models\PasswordResetModel;
use App\Helpers\Csrf;
use App\Helpers\PasswordPolicy;
use App\Helpers\RateLimit;

/**
 * Customer Authentication & Account Service
 *
 * Handles registration, login, logout, password recovery, and profile updates.
 */
class AuthService
{
    private UserModel $userModel;
    private PasswordResetModel $resetModel;
    private EmailService $emailService;

    public function __construct(
        ?UserModel $userModel = null,
        ?PasswordResetModel $resetModel = null,
        ?EmailService $emailService = null
    ) {
        $this->userModel    = $userModel ?? new UserModel();
        $this->resetModel   = $resetModel ?? new PasswordResetModel();
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Register a new customer.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, message: string, errors?: array<string, string>, user_id?: int}
     */
    public function register(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        // Rate limit: 10 attempts per email and 20 per IP, per hour (DB-backed).
        $wait = RateLimit::throttle('cust_register', $email !== '' ? $email : RateLimit::clientIp(), 10, 20, 3600);
        if ($wait > 0) {
            return [
                'success' => false,
                'message' => 'Too many registration attempts. Please try again in ' . (int) ceil($wait / 60) . ' minute(s).',
            ];
        }
        $mobile = preg_replace('/\D/', '', (string) ($input['mobile'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

        // Validation
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Full name must be between 2 and 100 characters.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($mobile === '' || strlen($mobile) !== 10) {
            $errors['mobile'] = 'Please enter a valid 10-digit mobile number.';
        }

        if (($policyError = PasswordPolicy::validate($password, $email, $name)) !== null) {
            $errors['password'] = $policyError;
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        // Terms of Service + Privacy Policy must be accepted explicitly
        $termsAccepted = filter_var($input['terms'] ?? $input['accept_terms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$termsAccepted) {
            $errors['terms'] = 'Please accept the Terms of Service and Privacy Policy to create an account.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Please correct the errors in the form.',
                'errors'  => $errors,
            ];
        }

        // Duplicate email: do NOT tell the requester (account enumeration).
        // The real owner gets an email explaining someone tried to register and
        // how to reset their password; the response is identical to a success.
        $existing = $this->userModel->findByEmail($email);
        if ($existing !== null) {
            try {
                $this->emailService->sendExistingAccountNotice((string) $existing['email'], (string) $existing['name']);
            } catch (\Throwable $e) {
                error_log('Existing-account notice failed: ' . $e->getMessage());
            }
            return [
                'success'  => true,
                'message'  => self::REGISTER_SUCCESS_MESSAGE,
                'existing' => true, // internal flag — never sent to the browser (see AuthController)
            ];
        }

        // Hash password and store
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->userModel->create($name, $email, $mobile, $passwordHash, date('Y-m-d H:i:s'));

        return [
            'success' => true,
            'message' => self::REGISTER_SUCCESS_MESSAGE,
            'user_id' => $userId,
        ];
    }

    /** Shown for both a new account and a duplicate email, so the two are indistinguishable. */
    public const REGISTER_SUCCESS_MESSAGE = 'Thanks! If this email was not already registered, your account is ready — sign in below. If it was, we have sent that address a note on how to access the existing account.';

    /**
     * Authenticate customer with email and password.
     *
     * @return array{success: bool, message: string, user?: array}
     */
    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $rateLimitKey = RateLimit::key('cust_login', $normalizedEmail);

        // Rate limit (DB-backed, counts failures only):
        // 5 failures per account and 30 per IP lock for 15 minutes.
        $wait = RateLimit::retryAfter('cust_login', $normalizedEmail);
        if ($wait > 0) {
            $minutes = (int) ceil($wait / 60);
            return [
                'success' => false,
                'message' => "Too many failed login attempts. Please try again in {$minutes} minute(s).",
            ];
        }

        $user = $this->userModel->findByEmail($normalizedEmail);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            RateLimit::recordFailure('cust_login', $normalizedEmail, 5, 30, 900);
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
            ];
        }

        // Clear rate limit on successful authentication
        RateLimit::clear($rateLimitKey);

        // Session fixation protection + fresh CSRF token for the new privilege level
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Csrf::rotate();

        // Role separation: a browser is either a customer or an administrator.
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);

        // Populate customer session
        $_SESSION['user_id']     = (int) $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_mobile'] = $user['mobile'];
        $_SESSION['auth_at']     = time(); // compared with users.password_changed_at by CustomerAuth

        // Remove sensitive fields before returning
        unset($user['password_hash']);

        return [
            'success' => true,
            'message' => 'Logged in successfully.',
            'user'    => $user,
        ];
    }

    /**
     * Terminate the customer session.
     */
    public function logout(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            $_SESSION['user_mobile'],
            $_SESSION['auth_at'],
            $_SESSION['intended_url']
        );
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Csrf::rotate();
    }

    /**
     * Initiate password reset flow.
     * Always returns generic success message to prevent account enumeration.
     */
    public function forgotPassword(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));

        // 3 reset emails per account and 10 per IP, per 15 minutes; response stays generic.
        if (RateLimit::throttle('cust_forgot', $normalizedEmail, 3, 10, 900) > 0) {
            return [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.',
            ];
        }

        $user = $this->userModel->findByEmail($normalizedEmail);

        if ($user !== null && ($user['status'] ?? 'active') === 'active') {
            // Generate 32-byte cryptographically secure random token
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $plainToken);

            // Clean up older unused/expired tokens for this user
            $this->resetModel->deleteOldForUser((int) $user['id']);

            // Store token hash valid for 1 hour
            $expiresAt = new \DateTime('+1 hour');
            $this->resetModel->create((int) $user['id'], $tokenHash, $expiresAt);

            // Send password reset email
            $resetUrl = app_url('reset-password?token=' . urlencode($plainToken));
            $this->emailService->sendPasswordReset($user['email'], $user['name'], $resetUrl);
        }

        return [
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ];
    }

    /**
     * Verify if a reset token is currently valid.
     */
    public function verifyResetToken(string $plainToken): ?array
    {
        if (trim($plainToken) === '') {
            return null;
        }

        $tokenHash = hash('sha256', $plainToken);
        return $this->resetModel->findValidByHash($tokenHash);
    }

    /**
     * Complete password reset using token and new password.
     */
    public function resetPassword(string $plainToken, string $password, string $passwordConfirmation): array
    {
        if (($policyError = PasswordPolicy::validate($password)) !== null) {
            return ['success' => false, 'message' => $policyError];
        }

        if ($password !== $passwordConfirmation) {
            return [
                'success' => false,
                'message' => 'Passwords do not match.',
            ];
        }

        // Token guessing is infeasible (256-bit), but throttle per IP anyway.
        if (!RateLimit::attempt(RateLimit::key('cust_reset_ip', RateLimit::clientIp()), 10, 900)) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please wait 15 minutes and try again.',
            ];
        }

        $reset = $this->verifyResetToken($plainToken);
        if ($reset === null) {
            return [
                'success' => false,
                'message' => 'This password reset link is invalid or has expired. Please request a new one.',
            ];
        }

        // Re-check the policy against the account's own email/name
        $owner = $this->userModel->findById((int) $reset['user_id']);
        if (($policyError = PasswordPolicy::validate($password, (string) ($owner['email'] ?? ''), (string) ($owner['name'] ?? ''))) !== null) {
            return ['success' => false, 'message' => $policyError];
        }

        // Update password — updatePassword() also stamps password_changed_at,
        // which invalidates every other session for this account.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->userModel->updatePassword((int) $reset['user_id'], $passwordHash);

        // Mark token as used (one-time use enforcement) and burn any siblings
        $this->resetModel->markUsed((int) $reset['id']);
        $this->resetModel->deleteOldForUser((int) $reset['user_id']);

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully. Any other devices have been signed out. You can now log in with your new password.',
        ];
    }

    /**
     * Update customer profile (name and mobile only).
     */
    public function updateProfile(int $userId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $mobile = preg_replace('/\D/', '', (string) ($data['mobile'] ?? ''));

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'Full name must be between 2 and 100 characters.'];
        }

        if ($mobile === '' || strlen($mobile) !== 10) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }

        $updated = $this->userModel->updateProfile($userId, [
            'name'   => $name,
            'mobile' => $mobile,
        ]);

        if ($updated) {
            $_SESSION['user_name']   = $name;
            $_SESSION['user_mobile'] = $mobile;
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        }

        return ['success' => false, 'message' => 'Failed to update profile.'];
    }

    /**
     * Change authenticated user's password.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $newPasswordConfirmation): array
    {
        if ($newPassword !== $newPasswordConfirmation) {
            return ['success' => false, 'message' => 'New passwords do not match.'];
        }

        // Fetch user from DB including password hash
        $stmt = getDb()->prepare('SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        if (($policyError = PasswordPolicy::validate($newPassword, (string) $user['email'], (string) $user['name'])) !== null) {
            return ['success' => false, 'message' => 'New password: ' . lcfirst($policyError)];
        }
        if (password_verify($newPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'New password must be different from the current password.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userModel->updatePassword($userId, $newHash);

        // Other sessions are now invalid (auth_at < password_changed_at); keep this one.
        $_SESSION['auth_at'] = time() + 1;
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Csrf::rotate();

        return ['success' => true, 'message' => 'Password changed successfully. Any other devices have been signed out.'];
    }
}
