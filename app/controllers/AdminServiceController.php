<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Service Controller
 *
 * Manages modalities: viewing specs, active/inactive toggling, pricing, and capacities.
 */
class AdminServiceController
{
    private ServiceModel $serviceModel;

    public function __construct(?ServiceModel $serviceModel = null)
    {
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * GET /admin/services
     */
    public function index(): void
    {
        AdminAuth::handle();

        $services = $this->serviceModel->getAll();
        foreach ($services as &$s) {
            $s['pricing'] = ServiceModel::calculatePricing((float) $s['price'], (float) ($s['gst_percent'] ?? 18.0));
        }
        unset($s);

        $title = 'Manage Recovery Modalities — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/services.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/services/{id}/toggle
     * Activate or deactivate a recovery modality.
     *
     * @param array<string, string> $params
     */
    public function toggle(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $service = $this->serviceModel->findById($id);

        if (!$service) {
            Flash::set('error', 'Service not found.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $newStatus = ($service['status'] === 'active') ? 'inactive' : 'active';
        $this->serviceModel->updateStatus($id, $newStatus);

        Flash::set('success', "Modality \"{$service['name']}\" is now " . ucfirst($newStatus) . '.');
        header('Location: ' . app_url('admin/services'));
        exit;
    }

    /**
     * POST /admin/services/{id}/update
     * Update service price, capacity, description, status and optionally image.
     *
     * @param array<string, string> $params
     */
    public function update(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $service = $this->serviceModel->findById($id);

        if (!$service) {
            Flash::set('error', 'Service not found.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $price    = (float) ($_POST['price'] ?? $service['price']);
        $capacity = (int) ($_POST['capacity'] ?? $service['capacity']);
        $desc     = trim((string) ($_POST['description'] ?? $service['description']));
        $status   = trim((string) ($_POST['status'] ?? $service['status']));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $service['status'];
        }

        if ($price < 0 || $capacity < 1) {
            Flash::set('error', 'Price must be positive and capacity must be at least 1.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        // --- Image Handling ---
        $imagePath = $service['image']; // default: keep existing

        $removeImage = !empty($_POST['remove_image']);
        $hasUpload   = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

        if ($hasUpload) {
            $file    = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $mime    = mime_content_type($file['tmp_name']);

            if (!in_array($mime, $allowed, true)) {
                Flash::set('error', 'Invalid image type. Only JPG, PNG, and WebP are allowed.');
                header('Location: ' . app_url('admin/services'));
                exit;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                Flash::set('error', 'Image size must not exceed 5 MB.');
                header('Location: ' . app_url('admin/services'));
                exit;
            }

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'service_' . $id . '_' . time() . '.' . strtolower($ext);
            $destDir  = dirname(__DIR__, 2) . '/public/uploads/services/';

            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                Flash::set('error', 'Failed to save uploaded image. Please try again.');
                header('Location: ' . app_url('admin/services'));
                exit;
            }

            $imagePath = 'uploads/services/' . $filename;

        } elseif ($removeImage) {
            $imagePath = null; // reset to default
        }

        $this->serviceModel->updateDetails($id, [
            'price'       => $price,
            'capacity'    => $capacity,
            'description' => $desc,
            'status'      => $status,
            'image'       => $imagePath,
        ]);

        Flash::set('success', "Updated specifications for \"{$service['name']}\".");
        header('Location: ' . app_url('admin/services'));
        exit;
    }
}
