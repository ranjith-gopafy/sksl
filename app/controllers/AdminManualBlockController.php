<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ManualBlockModel;
use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;
use App\Helpers\AdminInput;
use App\Models\BookingModel;

/**
 * Admin Manual Block Controller
 *
 * Manages administrative time blocking for equipment maintenance, private bookings, or facility repairs.
 */
class AdminManualBlockController
{
    private ManualBlockModel $manualBlockModel;
    private ServiceModel $serviceModel;

    public function __construct(
        ?ManualBlockModel $manualBlockModel = null,
        ?ServiceModel $serviceModel = null
    ) {
        $this->manualBlockModel = $manualBlockModel ?? new ManualBlockModel();
        $this->serviceModel     = $serviceModel ?? new ServiceModel();
    }

    /**
     * GET /admin/manual-blocks
     */
    public function index(): void
    {
        AdminAuth::handle();

        $blocks = $this->manualBlockModel->getAll(false);
        $services = $this->serviceModel->getAllActive('all');

        $title = 'Manual Time Blocks & Maintenance — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/manual-blocks.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * POST /admin/manual-blocks
     */
    public function create(): void
    {
        AdminAuth::handle();

        $back = static function (string $type, string $message): never {
            Flash::set($type, $message);
            header('Location: ' . app_url('admin/manual-blocks'));
            exit;
        };

        $serviceId = !empty($_POST['service_id']) ? (int) $_POST['service_id'] : null;
        if ($serviceId !== null && !$this->serviceModel->findById($serviceId)) {
            $back('error', 'Selected service does not exist.');
        }

        $blockType = AdminInput::oneOf(trim((string) ($_POST['block_type'] ?? '')), ['specific_date', 'recurring_weekday']);
        if ($blockType === null) {
            $back('error', 'Please choose a valid block type.');
        }

        $startTime = AdminInput::time((string) ($_POST['start_time'] ?? ''));
        $endTime   = AdminInput::time((string) ($_POST['end_time'] ?? ''));
        if ($startTime === null || $endTime === null) {
            $back('error', 'Start and end time must be valid times (HH:MM).');
        }
        if ($endTime <= $startTime) {
            $back('error', 'End time must be after start time.');
        }

        $specificDate = null;
        $weekday      = null;
        if ($blockType === 'specific_date') {
            $specificDate = AdminInput::date((string) ($_POST['specific_date'] ?? ''));
            if ($specificDate === null) {
                $back('error', 'Please provide a valid date (YYYY-MM-DD).');
            }
        } else {
            $weekday = AdminInput::weekday($_POST['weekday'] ?? null);
            if ($weekday === null) {
                $back('error', 'Please select a valid weekday.');
            }
        }

        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);

        $this->manualBlockModel->create([
            'service_id'          => $serviceId,
            'block_type'          => $blockType,
            'specific_date'       => $specificDate,
            'weekday'             => $weekday,
            'start_time'          => $startTime,
            'end_time'            => $endTime,
            'reason'              => $reason !== '' ? $reason : 'Administrative Maintenance Block',
            'created_by_admin_id' => (int) $_SESSION['admin_id'],
            'status'              => 'active',
        ]);

        // A block never cancels anything by itself; tell staff who is already booked in the window.
        $clashes = (new BookingModel())->countConfirmedInWindow($serviceId, $specificDate, $weekday, $startTime, $endTime);
        if ($clashes > 0) {
            $back('info', "Time block created, but {$clashes} confirmed booking(s) already fall inside it. Contact those athletes or cancel the bookings from Bookings.");
        }
        $back('success', 'Time block created successfully.');
    }

    /**
     * POST /admin/manual-blocks/{id}/delete
     */
    public function delete(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $block = $this->manualBlockModel->findById($id);

        if (!$block) {
            Flash::set('error', 'Block record not found.');
            header('Location: ' . app_url('admin/manual-blocks'));
            exit;
        }

        $this->manualBlockModel->delete($id);

        Flash::set('success', 'Time block removed.');
        header('Location: ' . app_url('admin/manual-blocks'));
        exit;
    }
}
