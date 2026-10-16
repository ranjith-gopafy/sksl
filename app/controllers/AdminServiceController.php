<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;
use App\Helpers\ImageUpload;

/**
 * Admin Service Controller
 *
 * Manages modalities: viewing specs, active/inactive toggling, pricing, and capacities.
 */
class AdminServiceController
{
    /** Pricing guard rails (₹). A service must be chargeable; the ceiling catches typos. */
    public const MIN_PRICE    = 1.00;
    public const MAX_PRICE    = 100000.00;
    public const MAX_CAPACITY = 50;

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
        $durationOptionModel = new \App\Models\DurationOptionModel();
        foreach ($services as &$s) {
            $s['pricing'] = ServiceModel::calculatePricing((float) $s['price'], (float) ($s['gst_percent'] ?? 18.0));
            $s['duration_options'] = $durationOptionModel->getByServiceId((int) $s['id'], false);
        }
        unset($s);

        $title = 'Manage Recovery Modalities — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/services.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
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

        $priceRaw    = trim((string) ($_POST['price'] ?? $service['price']));
        $capacityRaw = trim((string) ($_POST['capacity'] ?? $service['capacity']));
        $desc        = trim((string) ($_POST['description'] ?? $service['description']));
        $status      = trim((string) ($_POST['status'] ?? $service['status']));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $service['status'];
        }

        // Money: a paid service must cost something (a ₹0 price would create free bookings
        // and a zero-amount Razorpay order), be a plain decimal, and stay within a sane range.
        if (!is_numeric($priceRaw) || !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $priceRaw)) {
            Flash::set('error', 'Price must be a number with at most two decimals (e.g. 149.00).');
            header('Location: ' . app_url('admin/services'));
            exit;
        }
        $price = round((float) $priceRaw, 2);
        if ($price < self::MIN_PRICE || $price > self::MAX_PRICE) {
            Flash::set('error', sprintf('Price must be between ₹%s and ₹%s.', number_format(self::MIN_PRICE, 2), number_format(self::MAX_PRICE, 2)));
            header('Location: ' . app_url('admin/services'));
            exit;
        }
        if (!ctype_digit($capacityRaw) || (int) $capacityRaw < 1 || (int) $capacityRaw > self::MAX_CAPACITY) {
            Flash::set('error', 'Capacity must be a whole number between 1 and ' . self::MAX_CAPACITY . '.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }
        $capacity = (int) $capacityRaw;
        if (mb_strlen($desc) > 1000) {
            Flash::set('error', 'Description must be 1000 characters or fewer.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        // --- Image Handling (see App\Helpers\ImageUpload for the validation rules) ---
        $imagePath = $service['image']; // default: keep existing
        $publicDir = dirname(__DIR__, 2) . '/public';

        $removeImage = !empty($_POST['remove_image']);
        $hasUpload   = isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            $result = ImageUpload::store($_FILES['image'], $publicDir . '/uploads/services', 'service_' . $id);
            if (!$result['ok']) {
                Flash::set('error', $result['error'] ?? 'Image upload failed.');
                header('Location: ' . app_url('admin/services'));
                exit;
            }

            // Replace: remove the previous admin upload (never the seeded images/ assets).
            ImageUpload::deleteWithin((string) ($service['image'] ?? ''), $publicDir, 'uploads/services');
            $imagePath = 'uploads/services/' . $result['filename'];
        } elseif ($removeImage) {
            ImageUpload::deleteWithin((string) ($service['image'] ?? ''), $publicDir, 'uploads/services');
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

    /**
     * POST /admin/services/{id}/duration-options
     *
     * @param array<string, string> $params
     */
    public function addDurationOption(array $params = []): void
    {
        AdminAuth::handle();

        $serviceId = (int) ($params['id'] ?? $_POST['service_id'] ?? 0);
        $service = $this->serviceModel->findById($serviceId);

        if (!$service) {
            Flash::set('error', 'Service not found.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $duration = (int) ($_POST['duration_minutes'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 100);

        if ($duration < 5 || $duration > 360) {
            Flash::set('error', 'Duration must be between 5 and 360 minutes.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        // Option prices are GST-inclusive (what the customer pays), like the service price.
        // Practitioner services are priced at the centre, so their options carry no price.
        if (($service['pricing_type'] ?? '') === 'pay_at_centre') {
            $price = 0.0;
        } elseif ($price < 1 || $price > 100000) {
            Flash::set('error', 'Price (including GST) must be between ₹1 and ₹1,00,000.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $durationOptionModel = new \App\Models\DurationOptionModel();
        $existing = $durationOptionModel->findByServiceAndDuration($serviceId, $duration);
        if ($existing) {
            Flash::set('error', "A duration option of {$duration} minutes already exists for this service.");
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $durationOptionModel->create([
            'service_id'       => $serviceId,
            'duration_minutes' => $duration,
            'price'            => $price,
            'label'            => $label ?: ($duration . ' Mins Session'),
            'status'           => 'active',
        ]);

        Flash::set('success', "Added {$duration} min duration option to {$service['name']}.");
        header('Location: ' . app_url('admin/services'));
        exit;
    }

    /**
     * POST /admin/services/duration-options/{id}/delete
     *
     * @param array<string, string> $params
     */
    public function deleteDurationOption(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $durationOptionModel = new \App\Models\DurationOptionModel();
        $option = $durationOptionModel->findById($id);

        if (!$option) {
            Flash::set('error', 'Duration option not found.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        // Bookings and live holds keep pointing at the option they were made with,
        // so an option in use is switched off rather than erased.
        if ($durationOptionModel->isReferenced($id)) {
            $durationOptionModel->update($id, ['status' => 'inactive']);
            Flash::set('success', 'That duration option is used by existing bookings, so it was deactivated instead of deleted. It no longer appears for new bookings.');
        } else {
            $durationOptionModel->delete($id);
            Flash::set('success', 'Duration option removed successfully.');
        }
        header('Location: ' . app_url('admin/services'));
        exit;
    }
}
