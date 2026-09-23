<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BookingModel;
use App\Models\ServiceModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Booking Controller
 *
 * Manages facility-wide bookings: search, filters, session completion, and cancellations.
 */
class AdminBookingController
{
    private BookingModel $bookingModel;
    private ServiceModel $serviceModel;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?ServiceModel $serviceModel = null
    ) {
        $this->bookingModel = $bookingModel ?? new BookingModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * GET /admin/bookings
     * Search and manage athlete bookings.
     */
    public function index(): void
    {
        AdminAuth::handle();

        $rawStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        // Default to 'confirmed' if status parameter not provided
        $activeStatus = ($rawStatus === null || $rawStatus === '') ? 'confirmed' : $rawStatus;

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
        require dirname(__DIR__) . '/views/layouts/main.php';
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

        $success = $this->bookingModel->updateStatus($id, $status);
        if ($success) {
            Flash::set('success', "Booking #{$booking['booking_reference']} updated to " . ucfirst($status) . '.');
        } else {
            Flash::set('error', 'Failed to update booking status.');
        }

        header('Location: ' . app_url('admin/bookings'));
        exit;
    }
}
