<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Models\PasswordResetModel;
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

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Please correct the errors in the form.',
                'errors'  => $errors,
            ];
        }

        // Check for duplicate email
        $existing = $this->userModel->findByEmail($email);
        if ($existing !== null) {
            return [
                'success' => false,
                'message' => 'An account with this email address already exists. Please log in.',
                'errors'  => ['email' => 'An account with this email address already exists.'],
            ];
        }

        // Hash password and store
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->userModel->create($name, $email, $mobile, $passwordHash);

        return [
            'success' => true,
            'message' => 'Registration successful! You can now log in.',
            'user_id' => $userId,
        ];
    }

    /**
     * Authenticate customer with email and password.
     *
     * @return array{success: bool, message: string, user?: array}
     */
    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $rateLimitKey = 'cust_login_' . md5($normalizedEmail);

        // Rate limit: 5 attempts per 15 minutes (900 seconds)
        if (!RateLimit::attempt($rateLimitKey, 5, 900)) {
            $remaining = RateLimit::remainingSeconds($rateLimitKey);
            $minutes = (int) ceil($remaining / 60);
            return [
                'success' => false,
                'message' => "Too many failed login attempts. Please try again in {$minutes} minute(s).",
            ];
        }

        $user = $this->userModel->findByEmail($normalizedEmail);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
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

        // Session fixation protection
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Populate customer session
        $_SESSION['user_id']     = (int) $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_mobile'] = $user['mobile'];

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
            $_SESSION['intended_url']
        );
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    /**
     * Initiate password reset flow.
     * Always returns generic success message to prevent account enumeration.
     */
    public function forgotPassword(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $rateLimitKey = 'cust_forgot_' . md5($normalizedEmail);

        if (!RateLimit::attempt($rateLimitKey, 3, 900)) {
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
        if (strlen($password) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long.',
            ];
        }

        if ($password !== $passwordConfirmation) {
            return [
                'success' => false,
                'message' => 'Passwords do not match.',
            ];
        }

        $reset = $this->verifyResetToken($plainToken);
        if ($reset === null) {
            return [
                'success' => false,
                'message' => 'This password reset link is invalid or has expired. Please request a new one.',
            ];
        }

        // Update password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->userModel->updatePassword((int) $reset['user_id'], $passwordHash);

        // Mark token as used (one-time use enforcement)
        $this->resetModel->markUsed((int) $reset['id']);

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully. You can now log in with your new password.',
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
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }

        if ($newPassword !== $newPasswordConfirmation) {
            return ['success' => false, 'message' => 'New passwords do not match.'];
        }

        // Fetch user from DB including password hash
        $stmt = getDb()->prepare('SELECT id, password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userModel->updatePassword($userId, $newHash);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}
