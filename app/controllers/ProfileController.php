<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Helpers\Flash;
use App\Helpers\Response;

class ProfileController
{
    private UserModel $userModel;
    private AuthService $authService;

    public function __construct(?UserModel $userModel = null, ?AuthService $authService = null)
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Show customer profile.
     */
    public function show(): void
    {
        \App\Middleware\CustomerAuth::handle();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user = $this->userModel->findById($userId);

        if (!$user) {
            Flash::set('error', 'User account not found.');
            header('Location: ' . app_url('logout'));
            exit;
        }

        $title = 'My Profile — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/profile.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Update customer profile (name, mobile).
     */
    public function update(): void
    {
        \App\Middleware\CustomerAuth::handle();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $result = $this->authService->updateProfile($userId, $_POST);

        if ($this->isApiRequest()) {
            Response::json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            Flash::set('success', $result['message']);
        } else {
            Flash::set('error', $result['message']);
        }

        header('Location: ' . app_url('profile'));
        exit;
    }

    /**
     * Change account password.
     */
    public function changePassword(): void
    {
        \App\Middleware\CustomerAuth::handle();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');

        $result = $this->authService->changePassword($userId, $currentPassword, $newPassword, $newConfirmation);

        if ($this->isApiRequest()) {
            Response::json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            Flash::set('success', $result['message']);
        } else {
            Flash::set('error', $result['message']);
        }

        header('Location: ' . app_url('profile'));
        exit;
    }

    private function isApiRequest(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_starts_with($uri, '/api') || str_contains($accept, 'application/json');
    }
}
