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
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $title = 'Admin Portal Login — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/admin/login.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/login
     * Request OTP for admin email.
     */
    public function sendOtp(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));

        $res = $this->authService->requestOtp($email);

        if (!$res['success']) {
            Flash::set('error', $res['message']);
            header('Location: ' . app_url('admin/login'));
            exit;
        }

        Flash::set('success', $res['message']);
        header('Location: ' . app_url('admin/verify-otp?email=' . urlencode($email)));
        exit;
    }

    /**
     * GET /admin/verify-otp
     */
    public function showVerifyOtp(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $email = trim((string) ($_GET['email'] ?? ''));
        $title = 'Verify Admin Code — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/admin/verify-otp.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/verify-otp
     * Submit and verify 6-digit OTP.
     */
    public function verifyOtp(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $otp   = trim((string) ($_POST['otp'] ?? ''));

        $res = $this->authService->verifyOtp($email, $otp);

        if (!$res['success']) {
            Flash::set('error', $res['message']);
            header('Location: ' . app_url('admin/verify-otp?email=' . urlencode($email)));
            exit;
        }

        Flash::set('success', $res['message']);
        header('Location: ' . app_url('admin/bookings'));
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
