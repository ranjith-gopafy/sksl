<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Flash;
use App\Models\BookingModel;
use App\Services\BookingService;
use App\Middleware\CustomerAuth;

/**
 * Customer Booking Controller
 *
 * Handles the customer dashboard, booking history and self-service cancellation
 * (up to the notice period before the session; paid bookings are refunded manually
 * by SKSL minus the cancellation charge, see the Cancellation & Refund Policy).
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

        // Counts come from the same filters as the lists. A confirmed session
        // whose end time has already passed (including earlier today) is completed.
        $upcomingCount  = count($this->bookingModel->findByUser($userId, 'upcoming'));
        $completedCount = count($this->bookingModel->findByUser($userId, 'completed'));
        $cancelledCount = count($this->bookingModel->findByUser($userId, 'cancelled'));
        $totalCount     = count($allBookings);

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

    /**
     * POST /my-bookings/{ref}/cancel
     * Cancel one of the signed-in customer's own confirmed sessions (see BookingRules::cancellationQuote).
     *
     * @param array<string, string> $params
     */
    public function cancel(array $params = []): void
    {
        CustomerAuth::handle();

        $userId = (int) $_SESSION['user_id'];
        $ref    = trim((string) ($params['ref'] ?? ''));

        $service = new BookingService();
        $notice  = null;
        if (!\App\Helpers\RateLimit::attempt(\App\Helpers\RateLimit::key('booking_cancel', (string) $userId), 20, 3600)) {
            Flash::set('error', 'Too many cancellation attempts. Please try again later or contact SKSL.');
        } else {
            $result = $service->cancelByCustomer($userId, $ref, false);
            Flash::set($result['success'] ? 'success' : 'error', $result['message']);
            $notice = $result['data']['notice'] ?? null;
        }

        // Answer the customer first; the confirmation emails (seconds over SMTP) go out afterwards.
        ignore_user_abort(true);
        session_write_close();
        header('Location: ' . app_url('my-bookings?tab=cancelled'));
        header('Content-Length: 1');
        header('Connection: close');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo ' ';
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        if ($notice !== null) {
            $service->sendCancellationNotices(...$notice);
        }
        exit;
    }
}
