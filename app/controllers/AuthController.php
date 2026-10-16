<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\Flash;
use App\Helpers\Response;
use App\Helpers\Csrf;

class AuthController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Show registration page.
     */
    public function showRegister(): void
    {
        \App\Middleware\GuestMiddleware::handle();

        $title = 'Create Account — Sara Kinetic Sports Lab';
        $old   = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);

        $viewFile = dirname(__DIR__) . '/views/pages/register.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Handle registration submission (Form POST or API POST).
     */
    public function register(): void
    {
        $isApi = $this->isApiRequest();
        $result = $this->authService->register($_POST);

        if ($result['success']) {
            if ($isApi) {
                // Identical body whether the account was created or already existed
                Response::json(['success' => true, 'message' => $result['message']], 201);
            }

            Flash::set('success', $result['message']);
            $_SESSION['_old_input'] = ['email' => input_str($_POST['email'] ?? '')];
            header('Location: ' . app_url('login'));
            exit;
        }

        if ($isApi) {
            Response::json($result, 422);
        }

        $firstError = is_array($result['errors'] ?? null) ? (string) reset($result['errors']) : '';
        Flash::set('error', $firstError !== '' ? $firstError : $result['message']);
        $_SESSION['_old_input'] = [
            'name'   => input_str($_POST['name'] ?? ''),
            'email'  => input_str($_POST['email'] ?? ''),
            'mobile' => input_str($_POST['mobile'] ?? ''),
        ];
        header('Location: ' . app_url('register'));
        exit;
    }

    /**
     * Show login page.
     */
    public function showLogin(): void
    {
        \App\Middleware\GuestMiddleware::handle();

        // "Login to purchase" style links carry ?redirect=packages: remember where the customer was going,
        // but only a path inside this site (safe_return_url rejects anything else).
        $wanted = $_GET['redirect'] ?? null;
        if (is_string($wanted) && $wanted !== '') {
            $target = safe_return_url('/' . ltrim($wanted, '/'), '');
            if ($target !== app_url('')) {
                $_SESSION['intended_url'] = $target;
            }
        }

        $title = 'Sign In — Sara Kinetic Sports Lab';
        $oldEmail = $_SESSION['_old_input']['email'] ?? '';
        unset($_SESSION['_old_input']);

        $viewFile = dirname(__DIR__) . '/views/pages/login.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Handle login submission (Form POST or API POST).
     */
    public function login(): void
    {
        $isApi    = $this->isApiRequest();
        $email    = input_str($_POST['email'] ?? '');
        $password = input_str($_POST['password'] ?? '');

        $result = $this->authService->login($email, $password);

        if ($result['success']) {
            if ($isApi) {
                Response::json($result, 200);
            }

            // Only same-site relative paths survive; anything else goes to /services
            $redirectUrl = safe_return_url($_SESSION['intended_url'] ?? null, 'services');
            unset($_SESSION['intended_url']);

            // Shown as plain text by the toast, so the name is not HTML-escaped here (that would print "A&amp;B").
            Flash::set('success', 'Welcome back, ' . ($result['user']['name'] ?? ''));
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($isApi) {
            Response::json($result, 401);
        }

        Flash::set('error', $result['message']);
        $_SESSION['_old_input'] = ['email' => $email];
        header('Location: ' . app_url('login'));
        exit;
    }

    /**
     * Handle logout. POST only (routes) — a GET link could be triggered by an
     * <img src> on any page and log the visitor out.
     */
    public function logout(): void
    {
        $this->authService->logout();

        if ($this->isApiRequest()) {
            Response::success('Logged out successfully.');
        }

        Flash::set('success', 'You have been logged out.');
        header('Location: ' . app_url('login'));
        exit;
    }

    /**
     * Show forgot password request form.
     */
    public function showForgotPassword(): void
    {
        \App\Middleware\GuestMiddleware::handle();

        $title = 'Forgot Password — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/forgot-password.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Handle forgot password form submission.
     */
    public function forgotPassword(): void
    {
        $email = input_str($_POST['email'] ?? '');
        $result = $this->authService->forgotPassword($email);

        if ($this->isApiRequest()) {
            Response::json($result, 200);
        }

        Flash::set('info', $result['message']);
        header('Location: ' . app_url('forgot-password'));
        exit;
    }

    /**
     * Show reset password form.
     */
    public function showResetPassword(array $params = []): void
    {
        \App\Middleware\GuestMiddleware::handle();

        $token = input_str($_GET['token'] ?? $params['token'] ?? '');
        $title = 'Reset Password — Sara Kinetic Sports Lab';

        $reset = $this->authService->verifyResetToken($token);
        $isValid = ($reset !== null);

        $viewFile = dirname(__DIR__) . '/views/pages/reset-password.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Handle reset password submission.
     */
    public function resetPassword(): void
    {
        $token = input_str($_POST['token'] ?? '');
        $password = input_str($_POST['password'] ?? '');
        $passwordConfirmation = input_str($_POST['password_confirmation'] ?? '');

        $result = $this->authService->resetPassword($token, $password, $passwordConfirmation);

        if ($result['success']) {
            if ($this->isApiRequest()) {
                Response::json($result, 200);
            }

            Flash::set('success', $result['message']);
            header('Location: ' . app_url('login'));
            exit;
        }

        if ($this->isApiRequest()) {
            Response::json($result, 400);
        }

        Flash::set('error', $result['message']);
        header('Location: ' . app_url('reset-password?token=' . urlencode($token)));
        exit;
    }

    private function isApiRequest(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_starts_with($uri, '/api') || str_contains($accept, 'application/json');
    }
}
