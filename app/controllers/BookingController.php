<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BookingService;
use App\Models\ServiceModel;
use App\Middleware\CustomerAuth;
use App\Helpers\Response;
use App\Helpers\Flash;

class BookingController
{
    private BookingService $bookingService;
    private ServiceModel $serviceModel;

    public function __construct(
        ?BookingService $bookingService = null,
        ?ServiceModel $serviceModel = null
    ) {
        $this->bookingService = $bookingService ?? new BookingService();
        $this->serviceModel   = $serviceModel ?? new ServiceModel();
    }

    /**
     * Show booking wizard page.
     * Requires customer authentication.
     */
    public function showBooking(): void
    {
        CustomerAuth::handle();

        $services = $this->serviceModel->getAllActive();
        $selectedServiceId = (int) ($_GET['service_id'] ?? ($services[0]['id'] ?? 1));

        $selectedService = null;
        foreach ($services as &$s) {
            $s['pricing'] = ServiceModel::calculatePricing((float) $s['price'], (float) ($s['gst_percent'] ?? 18.0));
            if ((int) $s['id'] === $selectedServiceId) {
                $selectedService = $s;
            }
        }
        unset($s);

        if (!$selectedService && !empty($services)) {
            $selectedService = $services[0];
        }

        $title = 'Reserve Recovery Session — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/booking.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /api/bookings/hold
     * Requires customer authentication.
     */
    public function createHold(): void
    {
        CustomerAuth::handle();

        $userId    = (int) $_SESSION['user_id'];
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $date      = trim((string) ($_POST['booking_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));

        // Also check raw JSON input if Content-Type is application/json
        if ($serviceId === 0 && empty($date)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $serviceId = (int) ($json['service_id'] ?? 0);
                $date      = trim((string) ($json['booking_date'] ?? ''));
                $startTime = trim((string) ($json['start_time'] ?? ''));
            }
        }

        if ($serviceId <= 0 || $date === '' || $startTime === '') {
            Response::error('service_id, booking_date, and start_time are required parameters.', [], 422);
        }

        $result = $this->bookingService->createHold($userId, $serviceId, $date, $startTime);

        if (!$result['success']) {
            Response::error($result['message'], [], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ], 200);
    }

    /**
     * POST /api/bookings/hold/release
     * Requires customer authentication.
     */
    public function releaseHold(): void
    {
        CustomerAuth::handle();

        $userId    = (int) $_SESSION['user_id'];
        $reference = trim((string) ($_POST['booking_reference'] ?? ''));

        if ($reference === '') {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            $reference = trim((string) ($json['booking_reference'] ?? ''));
        }

        if ($reference === '') {
            Response::error('booking_reference is required.', [], 422);
        }

        $released = $this->bookingService->releaseHold($userId, $reference);

        Response::json([
            'success' => true,
            'message' => $released ? 'Hold released successfully.' : 'Hold not found or already released.',
        ]);
    }
}
