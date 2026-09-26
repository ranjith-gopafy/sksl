<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BookingModel;
use App\Middleware\CustomerAuth;

/**
 * Customer Booking Controller
 *
 * Handles the customer dashboard and booking history. Customers cannot cancel
 * online — cancellations and refunds are requested from SKSL staff (see the
 * Cancellation & Refund Policy) and applied by an admin.
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
        }

        $business = (array) config('business', []);

        $title = 'My Recovery Sessions — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/my-bookings.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }
}
