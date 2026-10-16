<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminAuthService;
use App\Helpers\Flash;

/**
 * Admin Authentication Controller
 *
 * Coordinates admin OTP request, OTP submission, and logout.
 */
class AdminAuthController
{
    private AdminAuthService $authService;

    public function __construct(?AdminAuthService $authService = null)
    {
        $this->authService = $authService ?? new AdminAuthService();
    }

    /**
     * GET /admin/login
     */
    public function showLogin(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            header('Location: ' . app_url('admin/dashboard'));
            exit;
        }

        $title = 'Admin Portal Login — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/admin/login.php';
        require dirname(__DIR__) . '/views/layouts/admin-auth.php';
    }

    /**
     * POST /admin/login
     * Request OTP for admin email.
     */
    public function sendOtp(): void
    {
        $email = trim(input_str($_POST['email'] ?? ''));

        $res = $this->authService->requestOtp($email);

        if (!$res['success']) {
            Flash::set('error', $res['message']);
            header('Location: ' . app_url('admin/login'));
            exit;
        }

        // The code is only valid for the browser that asked for it: remember the address in this
        // session instead of passing it through the URL (logs, history, shared screens).
        $_SESSION['admin_otp_email'] = strtolower($email);

        Flash::set('success', $res['message']);
        header('Location: ' . app_url('admin/verify-otp'));
        exit;
    }

    /**
     * GET /admin/verify-otp
     */
    public function showVerifyOtp(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            header('Location: ' . app_url('admin/dashboard'));
            exit;
        }

        $email = (string) ($_SESSION['admin_otp_email'] ?? '');
        if ($email === '') {
            header('Location: ' . app_url('admin/login'));
            exit;
        }

        $title = 'Verify Admin Code — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/admin/verify-otp.php';
        require dirname(__DIR__) . '/views/layouts/admin-auth.php';
    }

    /**
     * POST /admin/verify-otp
     * Submit and verify 6-digit OTP.
     */
    public function verifyOtp(): void
    {
        // Only the address this browser requested a code for can be verified; a posted email is ignored.
        $email = (string) ($_SESSION['admin_otp_email'] ?? '');
        $otp   = trim(input_str($_POST['otp'] ?? ''));

        if ($email === '') {
            Flash::set('error', 'Please request a new verification code.');
            header('Location: ' . app_url('admin/login'));
            exit;
        }

        $res = $this->authService->verifyOtp($email, $otp);

        if (!$res['success']) {
            Flash::set('error', $res['message']);
            header('Location: ' . app_url('admin/verify-otp'));
            exit;
        }
        unset($_SESSION['admin_otp_email']);

        Flash::set('success', $res['message']);
        header('Location: ' . app_url('admin/dashboard'));
        exit;
    }

    /**
     * GET /admin/logout or POST /admin/logout
     */
    public function logout(): void
    {
        $this->authService->logout();
        Flash::set('success', 'You have been securely signed out of the Admin Portal.');
        header('Location: ' . app_url('admin/login'));
        exit;
    }
}
