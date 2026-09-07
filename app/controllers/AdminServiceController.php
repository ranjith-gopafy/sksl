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
     * Update service price and capacity.
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

        $this->serviceModel->updateDetails($id, [
            'price'       => $price,
            'capacity'    => $capacity,
            'description' => $desc,
            'status'      => $status,
        ]);

        Flash::set('success', "Updated specifications for \"{$service['name']}\".");
        header('Location: ' . app_url('admin/services'));
        exit;
    }
}
