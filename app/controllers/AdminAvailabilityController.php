<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AvailabilityRuleModel;
use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;
use App\Helpers\AdminInput;
use App\Models\BookingModel;

/**
 * Admin Availability Controller
 *
 * Manages recurring weekly practitioner schedules and specific-date working hours / exceptions.
 */
class AdminAvailabilityController
{
    private AvailabilityRuleModel $ruleModel;
    private ServiceModel $serviceModel;

    public function __construct(
        ?AvailabilityRuleModel $ruleModel = null,
        ?ServiceModel $serviceModel = null
    ) {
        $this->ruleModel    = $ruleModel ?? new AvailabilityRuleModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * GET /admin/availability
     */
    public function index(): void
    {
        AdminAuth::handle();

        $selectedServiceId = !empty($_GET['service_id']) ? (int) $_GET['service_id'] : null;
        $professionalServices = $this->serviceModel->getProfessionalServices();

        if ($selectedServiceId === null && !empty($professionalServices)) {
            $selectedServiceId = (int) $professionalServices[0]['id'];
        }

        $rules = $selectedServiceId ? $this->ruleModel->getByServiceId($selectedServiceId, false) : [];

        $title = 'Professional Schedules & Availability — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/availability.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * POST /admin/availability
     */
    public function create(): void
    {
        AdminAuth::handle();

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $back = static function (string $type, string $message) use ($serviceId): never {
            Flash::set($type, $message);
            header('Location: ' . app_url('admin/availability' . ($serviceId > 0 ? '?service_id=' . $serviceId : '')));
            exit;
        };

        $service = $serviceId > 0 ? $this->serviceModel->findById($serviceId) : null;
        if (!$service || ($service['category'] ?? '') !== 'professional') {
            $back('error', 'Schedules can only be set for professional services.');
        }

        $ruleType = AdminInput::oneOf(trim((string) ($_POST['rule_type'] ?? '')), ['recurring_weekday', 'specific_date']);
        if ($ruleType === null) {
            $back('error', 'Please choose a valid rule type.');
        }

        $startTime = AdminInput::time((string) ($_POST['start_time'] ?? ''));
        $endTime   = AdminInput::time((string) ($_POST['end_time'] ?? ''));
        if ($startTime === null || $endTime === null) {
            $back('error', 'Start and end time must be valid times (HH:MM).');
        }
        if ($endTime <= $startTime) {
            $back('error', 'End time must be after start time.');
        }

        $weekday = null;
        $specificDate = null;
        if ($ruleType === 'recurring_weekday') {
            $weekday = AdminInput::weekday($_POST['weekday'] ?? null);
            if ($weekday === null) {
                $back('error', 'Please select a valid day of the week (Sunday - Saturday).');
            }
        } else {
            $specificDate = AdminInput::date((string) ($_POST['specific_date'] ?? ''));
            if ($specificDate === null) {
                $back('error', 'Please provide a valid specific date (YYYY-MM-DD).');
            }
        }

        $isAvailable = (isset($_POST['is_available']) && (string) $_POST['is_available'] === '0') ? 0 : 1;
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255);

        $this->ruleModel->create([
            'service_id'    => $serviceId,
            'rule_type'     => $ruleType,
            'weekday'       => $weekday,
            'specific_date' => $specificDate,
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'is_available'  => $isAvailable,
            'notes'         => $notes,
            'status'        => 'active',
        ]);

        // An "unavailable" exception does not cancel anything; warn about who is already booked.
        if ($isAvailable === 0) {
            $clashes = (new BookingModel())->countConfirmedInWindow($serviceId, $specificDate, $weekday, $startTime, $endTime);
            if ($clashes > 0) {
                $back('info', "Rule added, but {$clashes} confirmed appointment(s) already fall in this window. Contact those athletes or cancel them from Bookings.");
            }
        }

        $back('success', 'Availability schedule rule added successfully.');
    }

    /**
     * POST /admin/availability/{id}/delete
     */
    public function delete(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $rule = $this->ruleModel->findById($id);

        if (!$rule) {
            Flash::set('error', 'Rule not found.');
            header('Location: ' . app_url('admin/availability'));
            exit;
        }

        $serviceId = (int) $rule['service_id'];
        $this->ruleModel->delete($id);

        Flash::set('success', 'Availability schedule rule deleted.');
        header('Location: ' . app_url('admin/availability?service_id=' . $serviceId));
        exit;
    }
}
