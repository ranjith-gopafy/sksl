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
        $userId = (int) $_SESSION['user_id'];

        $services = $this->serviceModel->getAllActive('all');

        // Resolve requested service either by slug ('service') or by 'service_id'
        $serviceSlug = trim((string) ($_GET['service'] ?? ''));
        if ($serviceSlug === 'spa') {
            $serviceSlug = 'massages';
        }

        $selectedServiceId = (int) ($_GET['service_id'] ?? 0);
        $packageId = !empty($_GET['package_id']) ? (int) $_GET['package_id'] : null;

        $selectedService = null;
        foreach ($services as &$s) {
            $s['pricing'] = ($s['pricing_type'] ?? '') === 'pay_at_centre'
                ? [
                    'base_price'      => 0.00,
                    'gst_amount'      => 0.00,
                    'total_amount'    => 0.00,
                    'formatted_total' => '₹0.00',
                    'pay_at_centre'   => true,
                    'display_badge'   => 'Assessed & Paid at Centre',
                  ]
                : ServiceModel::calculatePricing((float) $s['price'], (float) ($s['gst_percent'] ?? 18.0));

            if ($serviceSlug !== '' && $s['slug'] === $serviceSlug) {
                $selectedService = $s;
            } elseif ($selectedService === null && $selectedServiceId > 0 && (int) $s['id'] === $selectedServiceId) {
                $selectedService = $s;
            }
        }
        unset($s);

        if (!$selectedService && !empty($services)) {
            $selectedService = $services[0];
        }

        $durationOptionModel = new \App\Models\DurationOptionModel();
        $durationOptions = $selectedService ? $durationOptionModel->getByServiceId((int) $selectedService['id'], true) : [];

        $customerPackageModel = new \App\Models\CustomerPackageModel();
        $activePackage = null;
        $packageCreditMinutes = null;
        if ($packageId !== null) {
            $activePackage = $customerPackageModel->findActiveForUser($userId, $packageId);

            // A package link that is expired, not yours, or for a modality the package does not include
            // must not silently turn into a full-price booking.
            foreach (($activePackage['credits'] ?? []) as $credit) {
                if ($selectedService && (int) $credit['service_id'] === (int) $selectedService['id']) {
                    $packageCreditMinutes = (int) $credit['duration_minutes'];
                }
            }
            if ($activePackage === null || $packageCreditMinutes === null) {
                Flash::set('error', 'That package is not active, has expired, or does not include this session. Open My Packages to choose one of your sessions.');
                header('Location: ' . app_url('my-packages'));
                exit;
            }
        }

        $title = (($selectedService['category'] ?? '') === 'professional')
            ? 'Book Consultation — ' . ($selectedService['name'] ?? 'Professional Service')
            : 'Reserve Recovery Session — Sara Kinetic Sports Lab';

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

        $userId           = (int) $_SESSION['user_id'];
        $serviceId        = (int) ($_POST['service_id'] ?? 0);
        $date             = trim((string) ($_POST['booking_date'] ?? ''));
        $startTime        = trim((string) ($_POST['start_time'] ?? ''));
        $declared         = (string) ($_POST['health_declared'] ?? '');
        $durationOptionId = !empty($_POST['duration_option_id']) ? (int) $_POST['duration_option_id'] : null;

        // Also check raw JSON input if Content-Type is application/json
        if ($serviceId === 0 && empty($date)) {
            $json = request_json();
            if (is_array($json)) {
                $serviceId        = (int) ($json['service_id'] ?? 0);
                $date             = trim((string) ($json['booking_date'] ?? ''));
                $startTime        = trim((string) ($json['start_time'] ?? ''));
                $declared         = (string) ($json['health_declared'] ?? '');
                $durationOptionId = !empty($json['duration_option_id']) ? (int) $json['duration_option_id'] : null;
            }
        }

        if ($serviceId <= 0 || $date === '' || $startTime === '') {
            Response::error('service_id, booking_date, and start_time are required parameters.', [], 422);
        }

        // The Terms & Health Declaration is a condition of every session and is
        // stored with the booking, so the server enforces it (not only the UI).
        if (!in_array($declared, ['1', 'true', 'on'], true)) {
            Response::error('Please accept the Terms & Health Declaration to reserve a slot.', ['health_declared' => 'required'], 422);
        }

        $result = $this->bookingService->createHold($userId, $serviceId, $date, $startTime, true, $durationOptionId);

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
     * POST /api/bookings/professional
     * Direct appointment booking for Physiotherapy & Sports Psychology (no online payment).
     */
    public function bookProfessional(): void
    {
        CustomerAuth::handle();

        $userId           = (int) $_SESSION['user_id'];
        $serviceId        = (int) ($_POST['service_id'] ?? 0);
        $date             = trim((string) ($_POST['booking_date'] ?? ''));
        $startTime        = trim((string) ($_POST['start_time'] ?? ''));
        $declared         = (string) ($_POST['health_declared'] ?? '');
        $durationOptionId = !empty($_POST['duration_option_id']) ? (int) $_POST['duration_option_id'] : null;

        if ($serviceId === 0 && empty($date)) {
            $json = request_json();
            if (is_array($json)) {
                $serviceId        = (int) ($json['service_id'] ?? 0);
                $date             = trim((string) ($json['booking_date'] ?? ''));
                $startTime        = trim((string) ($json['start_time'] ?? ''));
                $declared         = (string) ($json['health_declared'] ?? '');
                $durationOptionId = !empty($json['duration_option_id']) ? (int) $json['duration_option_id'] : null;
            }
        }

        if ($serviceId <= 0 || $date === '' || $startTime === '') {
            Response::error('service_id, booking_date, and start_time are required.', [], 422);
        }

        if (!in_array($declared, ['1', 'true', 'on'], true)) {
            Response::error('Please accept the Terms & Health Declaration.', ['health_declared' => 'required'], 422);
        }

        $result = $this->bookingService->createProfessionalBooking($userId, $serviceId, $date, $startTime, true, $durationOptionId);

        if (!$result['success']) {
            Response::error($result['message'], [], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['booking'],
        ], 200);
    }

    /**
     * POST /api/bookings/package
     * Book session using combo package credit.
     */
    public function bookPackage(): void
    {
        CustomerAuth::handle();

        $userId            = (int) $_SESSION['user_id'];
        $customerPackageId = (int) ($_POST['customer_package_id'] ?? 0);
        $serviceId         = (int) ($_POST['service_id'] ?? 0);
        $date              = trim((string) ($_POST['booking_date'] ?? ''));
        $startTime         = trim((string) ($_POST['start_time'] ?? ''));
        $declared          = (string) ($_POST['health_declared'] ?? '');

        if ($customerPackageId === 0 && empty($date)) {
            $json = request_json();
            if (is_array($json)) {
                $customerPackageId = (int) ($json['customer_package_id'] ?? 0);
                $serviceId         = (int) ($json['service_id'] ?? 0);
                $date              = trim((string) ($json['booking_date'] ?? ''));
                $startTime         = trim((string) ($json['start_time'] ?? ''));
                $declared          = (string) ($json['health_declared'] ?? '');
            }
        }

        if ($customerPackageId <= 0 || $serviceId <= 0 || $date === '' || $startTime === '') {
            Response::error('customer_package_id, service_id, booking_date, and start_time are required.', [], 422);
        }

        if (!in_array($declared, ['1', 'true', 'on'], true)) {
            Response::error('Please accept the Terms & Health Declaration.', ['health_declared' => 'required'], 422);
        }

        $result = $this->bookingService->createPackageBooking($userId, $customerPackageId, $serviceId, $date, $startTime, true);

        if (!$result['success']) {
            Response::error($result['message'], [], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['booking'],
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
            $json = request_json();
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
