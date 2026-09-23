<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BookingModel;
use App\Middleware\CustomerAuth;
use App\Helpers\Flash;

/**
 * Customer Booking Controller
 *
 * Handles customer dashboard, booking history, and 2-hour policy cancellation requests.
 */
class CustomerBookingController
{
    private BookingModel $bookingModel;

    public function __construct(?BookingModel $bookingModel = null)
    {
        $this->bookingModel = $bookingModel ?? new BookingModel();
    }

    /**
     * GET /my-bookings
     * Display customer booking history with tab filters.
     */
    public function index(): void
    {
        CustomerAuth::handle();

        $userId = (int) $_SESSION['user_id'];
        $rawTab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : null;

        $allBookings = $this->bookingModel->findByUser($userId, null);

        $now = time();
        $today = date('Y-m-d');
        $upcomingCount  = 0;
        $completedCount = 0;
        $cancelledCount = 0;
        $totalCount     = count($allBookings);

        foreach ($allBookings as &$b) {
            $sessionEnd = strtotime($b['booking_date'] . ' ' . $b['end_time']);

            if ($b['booking_status'] === 'cancelled') {
                $cancelledCount++;
            } elseif ($b['booking_status'] === 'completed' || ($b['booking_status'] === 'confirmed' && $sessionEnd < $now && $b['booking_date'] < $today)) {
                $completedCount++;
            } elseif ($b['booking_status'] === 'confirmed') {
                $upcomingCount++;
            }

            $b['cancellation'] = BookingModel::checkCancellationEligibility($b, $userId);
        }
        unset($b);

        // Determine active tab
        if ($rawTab !== null && in_array($rawTab, ['all', 'upcoming', 'completed', 'cancelled'], true)) {
            $activeTab = $rawTab;
        } else {
            // Default to 'upcoming' if any upcoming, else 'all' if user has bookings
            $activeTab = ($upcomingCount > 0) ? 'upcoming' : (($totalCount > 0) ? 'all' : 'upcoming');
        }

        if ($activeTab === 'all') {
            $filteredBookings = $allBookings;
        } else {
            $filteredBookings = $this->bookingModel->findByUser($userId, $activeTab);
            foreach ($filteredBookings as &$fb) {
                $fb['cancellation'] = BookingModel::checkCancellationEligibility($fb, $userId);
            }
            unset($fb);
        }

        $title = 'My Recovery Sessions — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/my-bookings.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /my-bookings/{ref}/cancel
     * Process customer cancellation request enforcing 2-hour minimum notice.
     *
     * @param array<string, string> $params
     */
    public function cancel(array $params = []): void
    {
        CustomerAuth::handle();

        $userId    = (int) $_SESSION['user_id'];
        $reference = trim((string) ($params['ref'] ?? $_POST['booking_reference'] ?? ''));

        if ($reference === '') {
            Flash::set('error', 'Booking reference is required.');
            header('Location: ' . app_url('my-bookings'));
            exit;
        }

        $booking = $this->bookingModel->findByReference($reference);
        if (!$booking || (int) $booking['user_id'] !== $userId) {
            Flash::set('error', 'Booking not found or unauthorized.');
            header('Location: ' . app_url('my-bookings'));
            exit;
        }

        $eligibility = BookingModel::checkCancellationEligibility($booking, $userId);
        if (!$eligibility['can_cancel']) {
            Flash::set('error', $eligibility['reason'] ?? 'This session is not eligible for cancellation.');
            header('Location: ' . app_url('my-bookings'));
            exit;
        }

        $success = $this->bookingModel->updateStatus((int) $booking['id'], 'cancelled');
        if ($success) {
            Flash::set('success', "Session {$reference} has been cancelled successfully.");
        } else {
            Flash::set('error', 'Unable to cancel session. Please contact support.');
        }

        header('Location: ' . app_url('my-bookings?tab=cancelled'));
        exit;
    }
}
