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
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * POST /admin/closed-dates
     */
    public function create(): void
    {
        AdminAuth::handle();

        $date   = trim((string) ($_POST['closed_date'] ?? ''));
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);

        if (\App\Helpers\AdminInput::date($date) === null) {
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

        // Closing a day cancels nothing by itself: tell staff who is already booked on it.
        $clashes = (new \App\Models\BookingModel())->countConfirmedInWindow(null, $date, null, '00:00:00', '23:59:59');
        if ($clashes > 0) {
            Flash::set('info', "Facility closed on {$date}, but {$clashes} confirmed booking(s) already exist that day. Contact those athletes or cancel the bookings from Bookings.");
            header('Location: ' . app_url('admin/closed-dates'));
            exit;
        }

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
