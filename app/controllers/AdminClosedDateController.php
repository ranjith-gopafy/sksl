<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ClosedDateModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Closed Date Controller
 *
 * Manages facility closures to prevent bookings on holidays, maintenance days, or tournaments.
 */
class AdminClosedDateController
{
    private ClosedDateModel $closedDateModel;

    public function __construct(?ClosedDateModel $closedDateModel = null)
    {
        $this->closedDateModel = $closedDateModel ?? new ClosedDateModel();
    }

    /**
     * GET /admin/closed-dates
     */
    public function index(): void
    {
        AdminAuth::handle();

        $closedDates = $this->closedDateModel->getAll();

        $title = 'Facility Closed Dates — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/closed-dates.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/closed-dates
     */
    public function create(): void
    {
        AdminAuth::handle();

        $date   = trim((string) ($_POST['closed_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Flash::set('error', 'Invalid date format (YYYY-MM-DD required).');
            header('Location: ' . app_url('admin/closed-dates'));
            exit;
        }

        if ($this->closedDateModel->isDateClosed($date)) {
            Flash::set('error', "Facility is already marked closed on {$date}.");
            header('Location: ' . app_url('admin/closed-dates'));
            exit;
        }

        $this->closedDateModel->add($date, $reason ?: null);

        Flash::set('success', "Facility marked closed on {$date}. All slots on this date are now blocked.");
        header('Location: ' . app_url('admin/closed-dates'));
        exit;
    }

    /**
     * POST /admin/closed-dates/{id}/delete
     * Re-opens facility on designated date.
     *
     * @param array<string, string> $params
     */
    public function delete(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $deleted = $this->closedDateModel->delete($id);

        if ($deleted) {
            Flash::set('success', 'Closed date removed. Normal slot schedule restored.');
        } else {
            Flash::set('error', 'Unable to remove closed date.');
        }

        header('Location: ' . app_url('admin/closed-dates'));
        exit;
    }
}
