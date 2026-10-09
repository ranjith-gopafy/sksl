<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Booking Controller
 *
 * Manages facility-wide bookings: search, filters, session completion, and cancellations.
 * Cancellations are staff-only (customers contact SKSL) and follow
 * BookingModel::ADMIN_TRANSITIONS.
 */
class AdminBookingController
{
    private BookingModel $bookingModel;
    private ServiceModel $serviceModel;
    private BookingHoldModel $holdModel;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?ServiceModel $serviceModel = null,
        ?BookingHoldModel $holdModel = null
    ) {
        $this->bookingModel = $bookingModel ?? new BookingModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
        $this->holdModel    = $holdModel ?? new BookingHoldModel();
    }

    /**
     * GET /admin/bookings
     * Search and manage athlete bookings.
     */
    public function index(): void
    {
        AdminAuth::handle();

        $rawStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        // Default to 'confirmed' if status parameter not provided; unknown values fall back too
        $activeStatus = in_array($rawStatus, ['all', 'confirmed', 'visited', 'completed', 'no_show', 'cancelled', 'pending', 'expired'], true)
            ? $rawStatus
            : 'confirmed';

        // Housekeeping: close abandoned checkouts and stale holds before listing,
        // so staff never see "pending" rows that can no longer be paid.
        $this->holdModel->expireStale();
        $this->bookingModel->expireStalePending();
        (new \App\Models\CustomerPackageModel())->expireStalePending();

        $filters = [
            'date'       => trim((string) ($_GET['date'] ?? '')),
            'service_id' => !empty($_GET['service_id']) ? (int) $_GET['service_id'] : null,
            'status'     => $activeStatus,
            'search'     => trim((string) ($_GET['search'] ?? '')),
        ];

        $statusCounts = $this->bookingModel->getAdminStatusCounts($filters);
        $bookings = $this->bookingModel->getAdminBookings($filters);
        $services = $this->serviceModel->getAll();

        $title = 'Bookings Operations — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/bookings.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /**
     * POST /admin/bookings/{id}/status
     * Transition booking status (completed, cancelled, confirmed).
     *
     * @param array<string, string> $params
     */
    public function updateStatus(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if (!in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
            Flash::set('error', 'Invalid booking status.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $booking = $this->bookingModel->findById($id);
        if (!$booking) {
            Flash::set('error', 'Booking record not found.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $check = BookingModel::checkAdminTransition($booking, $status);
        if (!$check['allowed']) {
            Flash::set('error', $check['reason'] ?? 'That status change is not allowed.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $success = $this->bookingModel->transitionStatus($id, (string) $booking['booking_status'], $status);
        if ($success) {
            if ($status === 'cancelled') {
                // Free the slot immediately for other athletes.
                $this->holdModel->release((string) $booking['booking_reference']);
            }
            $note = '';
            if ($status === 'cancelled') {
                // A package session returns its credit (same policy as no-show). The guarded
                // transition above means this runs once; a booking already marked no-show
                // had its credit returned then.
                if (($booking['visit_status'] ?? 'pending') !== 'no_show' && $this->restorePackageCredit($booking)) {
                    $note = ' The package credit has been returned to the customer.';
                } elseif (($booking['payment_mode'] ?? 'payment_required') === 'payment_required'
                    && ($booking['payment_status'] ?? '') === 'paid') {
                    $note = ' Remember to process the refund through the Razorpay dashboard.';
                }
            }
            Flash::set('success', "Booking #{$booking['booking_reference']} updated to " . ucfirst($status) . '.' . $note);
        } else {
            Flash::set('error', 'Booking status changed in the meantime; please review and try again.');
        }

        header('Location: ' . app_url('admin/bookings'));
        exit;
    }

    /**
     * POST /admin/bookings/{id}/visit
     * Mark athlete as visited (once, for confirmed/completed bookings still pending attendance).
     */
    public function markVisited(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $booking = $this->bookingModel->findById($id);
        if (!$booking) {
            Flash::set('error', 'Booking record not found.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $adminId = (int) $_SESSION['admin_id'];
        if (!$this->bookingModel->markVisited($id, $adminId)) {
            Flash::set('error', 'Only a confirmed booking that has not been marked yet, and whose session is starting or has started, can be set to visited.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        // Attending the session completes it (goes through the guarded transition).
        if ($booking['booking_status'] === 'confirmed') {
            $this->bookingModel->transitionStatus($id, 'confirmed', 'completed');
        }

        Flash::set('success', "Booking #{$booking['booking_reference']} marked as VISITED.");
        header('Location: ' . app_url('admin/bookings'));
        exit;
    }

    /**
     * POST /admin/bookings/{id}/no-show
     * Mark athlete as No-Show. For combo package bookings the credit is returned
     * exactly once: it is restored only if this request is the one that flipped
     * the booking from pending to no-show.
     */
    public function markNoShow(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? $_POST['id'] ?? 0);
        $booking = $this->bookingModel->findById($id);
        if (!$booking) {
            Flash::set('error', 'Booking record not found.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $adminId = (int) $_SESSION['admin_id'];
        if (!$this->bookingModel->markNoShow($id, $adminId)) {
            Flash::set('error', 'Only a confirmed booking that has not been marked yet, and whose session has already started, can be set to no-show.');
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }

        $restoredMsg = '';
        if ($this->restorePackageCredit($booking)) {
            $restoredMsg = ' 1 Package credit has been restored to customer balance.';
        }

        Flash::set('success', "Booking #{$booking['booking_reference']} marked as NO-SHOW." . $restoredMsg);
        header('Location: ' . app_url('admin/bookings'));
        exit;
    }

    /**
     * Give a package credit back for a package booking. Callers invoke this only
     * after winning a conditional state change, which is what makes it once-only.
     *
     * @param array<string, mixed> $booking
     */
    private function restorePackageCredit(array $booking): bool
    {
        if (($booking['booking_type'] ?? '') !== 'package' || empty($booking['package_credit_id'])) {
            return false;
        }
        $restored = (new \App\Models\CustomerPackageModel())
            ->restoreCredit((int) $booking['package_credit_id'])['success'];

        if ($restored && !empty($booking['customer_package_id'])) {
            $pkg = (new \App\Models\CustomerPackageModel())->findById((int) $booking['customer_package_id']);
            if ($pkg && strtotime((string) $pkg['expires_at']) < time()) {
                Flash::set('info', 'The credit was returned, but that package has already expired, so the customer cannot use it. Contact them if you want to extend it.');
            }
        }

        return $restored;
    }
}
