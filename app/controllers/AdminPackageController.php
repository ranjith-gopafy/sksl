<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageModel;
use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Package Controller
 *
 * Manages combo packages, credit allocations, and bundle status.
 */
class AdminPackageController
{
    private PackageModel $packageModel;
    private ServiceModel $serviceModel;

    public function __construct(
        ?PackageModel $packageModel = null,
        ?ServiceModel $serviceModel = null
    ) {
        $this->packageModel = $packageModel ?? new PackageModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * GET /admin/packages
     */
    public function index(): void
    {
        AdminAuth::handle();

        $packages = $this->packageModel->getAll(false);
        $services = $this->serviceModel->getAllActive('modality');

        $title = 'Combo Packages & Bundles — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/packages.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * GET /admin/package-purchases
     */
    public function purchases(): void
    {
        AdminAuth::handle();

        $purchases = (new \App\Models\CustomerPackageModel())->getAllPurchases(100, 0);

        $title = 'Package Purchases — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/package-purchases.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * POST /admin/packages
     */
    public function create(): void
    {
        AdminAuth::handle();

        $back = static function (string $type, string $message): never {
            Flash::set($type, $message);
            header('Location: ' . app_url('admin/packages'));
            exit;
        };

        $name        = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $slug        = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price       = (float) ($_POST['price'] ?? 0);
        $gstPercent  = (float) ($_POST['gst_percent'] ?? 18.00);
        $validity    = (int) ($_POST['validity_days'] ?? 30);
        $badgeText   = mb_substr(trim((string) ($_POST['badge_text'] ?? '')), 0, 50);

        if ($name === '') {
            $back('error', 'Package name is required.');
        }
        // Price is what the customer pays, GST included, like every catalogue price.
        if ($price < 1 || $price > 500000) {
            $back('error', 'Price (including GST) must be between ₹1 and ₹5,00,000.');
        }
        if ($gstPercent < 0 || $gstPercent > 28) {
            $back('error', 'GST must be between 0% and 28%.');
        }
        if ($validity < 1 || $validity > 730) {
            $back('error', 'Validity must be between 1 and 730 days.');
        }

        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug !== '' ? $slug : $name), '-'));
        if ($slug === '' || strlen($slug) > 150) {
            $back('error', 'Could not build a valid URL slug from that name.');
        }
        if ($this->packageModel->findBySlug($slug)) {
            $back('error', "A package with the slug '{$slug}' already exists. Choose a different name or slug.");
        }

        // Items: modalities only, each once, quantity 1–50.
        $itemServices   = is_array($_POST['service_id'] ?? null) ? $_POST['service_id'] : [];
        $itemQuantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
        $itemMinutes    = is_array($_POST['duration_minutes'] ?? null) ? $_POST['duration_minutes'] : [];
        $items = [];
        foreach ($itemServices as $idx => $sId) {
            $sId = (int) $sId;
            $qty = isset($itemQuantities[$idx]) ? (int) $itemQuantities[$idx] : 1;
            if ($sId <= 0) {
                continue;
            }
            if ($qty < 1 || $qty > 50) {
                $back('error', 'Each item quantity must be between 1 and 50.');
            }
            $service = $this->serviceModel->findById($sId);
            if (!$service || ($service['category'] ?? '') !== 'modality') {
                $back('error', 'Packages can only include recovery modalities.');
            }
            if (isset($items[$sId])) {
                $back('error', 'Each modality may appear only once in a package; raise its quantity instead.');
            }
            // Optional session length for this item (blank = the modality's normal length).
            $minutes = (isset($itemMinutes[$idx]) && $itemMinutes[$idx] !== '') ? (int) $itemMinutes[$idx] : null;
            if ($minutes !== null && ($minutes < 5 || $minutes > 360)) {
                $back('error', 'Item session length must be between 5 and 360 minutes.');
            }
            $items[$sId] = ['service_id' => $sId, 'quantity' => $qty, 'duration_minutes' => $minutes];
        }
        if (empty($items)) {
            $back('error', 'Package must include at least one modality item.');
        }

        try {
            $this->packageModel->create([
                'name'          => $name,
                'slug'          => $slug,
                'description'   => $description,
                'price'         => $price,
                'gst_percent'   => $gstPercent,
                'validity_days' => $validity,
                'badge_text'    => $badgeText !== '' ? $badgeText : null,
                'status'        => 'active',
            ], array_values($items));
        } catch (\PDOException $e) {
            error_log('AdminPackageController::create failed: ' . $e->getMessage());
            $back('error', 'The package could not be saved (it may duplicate an existing one).');
        }

        $back('success', "Combo package '{$name}' created successfully.");
    }

    /**
     * POST /admin/packages/{id}/toggle
     */
    public function toggle(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $package = $this->packageModel->findById($id);

        if (!$package) {
            Flash::set('error', 'Package not found.');
            header('Location: ' . app_url('admin/packages'));
            exit;
        }

        $newStatus = ($package['status'] === 'active') ? 'inactive' : 'active';
        $this->packageModel->update($id, ['status' => $newStatus]);

        Flash::set('success', "Package status updated to " . ucfirst($newStatus) . '.');
        header('Location: ' . app_url('admin/packages'));
        exit;
    }
}
