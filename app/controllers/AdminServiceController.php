<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Models\ServiceCategoryModel;
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
        $categories = (new ServiceCategoryModel())->getAll();
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

        [$input, $error] = $this->collectInput($service);
        if ($input === null) {
            Flash::set('error', (string) $error);
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

        $this->serviceModel->updateDetails($id, $input + ['image' => $imagePath]);

        Flash::set('success', "Updated specifications for \"{$input['name']}\".");
        header('Location: ' . app_url('admin/services'));
        exit;
    }

    /**
     * Read and check the modality form (add and edit use the same rules).
     * Returns [data, null] when valid, or [null, message] with a plain-language reason.
     *
     * @param array<string, mixed>|null $service the modality being edited, null when adding
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    private function collectInput(?array $service): array
    {
        $isPractitioner = $service !== null && ($service['category'] ?? 'modality') === 'professional';
        $currentId = $service !== null ? (int) $service['id'] : null;

        $name = trim((string) ($_POST['name'] ?? ($service['name'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return [null, 'Name must be 2 to 100 characters.'];
        }
        if (!ServiceCategoryModel::isValidName($name)) {
            return [null, 'Name: ' . ServiceCategoryModel::NAME_RULE];
        }
        if ($this->serviceModel->nameExists($name, $currentId)) {
            return [null, "There is already a modality called \"{$name}\". Choose a different name."];
        }

        // Category: required for recovery modalities. The practitioner services are not grouped.
        $categoryId = null;
        if (!$isPractitioner) {
            $categoryId = (int) ($_POST['category_id'] ?? ($service['category_id'] ?? 0));
            if ($categoryId < 1 || (new ServiceCategoryModel())->findById($categoryId) === null) {
                return [null, 'Please choose a category for this modality.'];
            }
        }

        $priceRaw    = trim((string) ($_POST['price'] ?? ($service['price'] ?? '')));
        $capacityRaw = trim((string) ($_POST['capacity'] ?? ($service['capacity'] ?? '')));
        $durationRaw = trim((string) ($_POST['duration_minutes'] ?? ($service['duration_minutes'] ?? '')));
        $desc        = trim((string) ($_POST['description'] ?? ($service['description'] ?? '')));
        $status      = trim((string) ($_POST['status'] ?? ($service['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = (string) ($service['status'] ?? 'active');
        }

        // Money: a paid service must cost something (a ₹0 price would create free bookings
        // and a zero-amount Razorpay order), be a plain decimal, and stay within a sane range.
        if (!is_numeric($priceRaw) || !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $priceRaw)) {
            return [null, 'Price must be a number with at most two decimals (e.g. 149.00).'];
        }
        $price = round((float) $priceRaw, 2);
        if ($price < self::MIN_PRICE || $price > self::MAX_PRICE) {
            return [null, sprintf('Price must be between ₹%s and ₹%s.', number_format(self::MIN_PRICE, 2), number_format(self::MAX_PRICE, 2))];
        }
        if (!ctype_digit($capacityRaw) || (int) $capacityRaw < 1 || (int) $capacityRaw > self::MAX_CAPACITY) {
            return [null, 'Capacity must be a whole number between 1 and ' . self::MAX_CAPACITY . '.'];
        }
        if (!ctype_digit($durationRaw) || (int) $durationRaw < 5 || (int) $durationRaw > 360) {
            return [null, 'Session length must be a whole number of minutes between 5 and 360.'];
        }
        if (mb_strlen($desc) > 1000) {
            return [null, 'Description must be 1000 characters or fewer.'];
        }

        $data = [
            'name'             => $name,
            'price'            => $price,
            'capacity'         => (int) $capacityRaw,
            'duration_minutes' => (int) $durationRaw,
            'description'      => $desc,
            'status'           => $status,
        ];
        if (!$isPractitioner) {
            $data['category_id'] = $categoryId;
        }
        return [$data, null];
    }

    /**
     * POST /admin/services/create
     * Add a recovery modality. It can be booked as soon as it is active; extra session lengths are added
     * afterwards with "Durations".
     */
    public function create(): void
    {
        AdminAuth::handle();

        [$input, $error] = $this->collectInput(null);
        if ($input === null) {
            Flash::set('error', (string) $error);
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $imagePath = null;
        $hasUpload = isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload) {
            $result = ImageUpload::store($_FILES['image'], dirname(__DIR__, 2) . '/public/uploads/services', 'service_new');
            if (!$result['ok']) {
                Flash::set('error', $result['error'] ?? 'Image upload failed.');
                header('Location: ' . app_url('admin/services'));
                exit;
            }
            $imagePath = 'uploads/services/' . $result['filename'];
        }

        $this->serviceModel->create($input + ['image' => $imagePath]);

        Flash::set('success', "Added modality \"{$input['name']}\"." . ($input['status'] === 'active' ? ' It is now open for booking.' : ' It is inactive, so customers cannot see it yet.'));
        header('Location: ' . app_url('admin/services'));
        exit;
    }

    /**
     * POST /admin/services/{id}/delete
     * Delete a modality nothing refers to. One with bookings, holds or combo packages is hidden
     * (deactivated) instead, so history and invoices stay intact.
     *
     * @param array<string, string> $params
     */
    public function delete(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $service = $this->serviceModel->findById($id);
        if (!$service) {
            Flash::set('error', 'Service not found.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }
        if (($service['category'] ?? 'modality') === 'professional') {
            Flash::set('error', 'Practitioner services cannot be deleted. Deactivate it to hide it.');
            header('Location: ' . app_url('admin/services'));
            exit;
        }

        $deleted = false;
        if (!$this->serviceModel->isReferenced($id)) {
            try {
                $deleted = $this->serviceModel->delete($id);
            } catch (\PDOException $e) {
                $deleted = false; // something still points at it: fall through to hiding it
            }
        }

        if ($deleted) {
            ImageUpload::deleteWithin((string) ($service['image'] ?? ''), dirname(__DIR__, 2) . '/public', 'uploads/services');
            Flash::set('success', "Deleted modality \"{$service['name']}\".");
        } else {
            $this->serviceModel->updateStatus($id, 'inactive');
            Flash::set('success', "\"{$service['name']}\" has bookings or is part of a combo package, so it was hidden (set to Inactive) instead of deleted. Past bookings and invoices are unchanged.");
        }
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
