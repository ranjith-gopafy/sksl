<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AvailabilityService;
use App\Helpers\Response;

class AvailabilityController
{
    private AvailabilityService $availabilityService;

    public function __construct(?AvailabilityService $availabilityService = null)
    {
        $this->availabilityService = $availabilityService ?? new AvailabilityService();
    }

    /**
     * GET /api/availability?service_id=1&date=YYYY-MM-DD
     *
     * Returns dynamically generated available slots, remaining capacity,
     * and service pricing metadata.
     */
    public function index(): void
    {
        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $date      = trim((string) ($_GET['date'] ?? ''));

        if ($serviceId <= 0) {
            Response::error('service_id query parameter is required and must be a positive integer.', [], 422);
        }

        if ($date === '') {
            Response::error('date query parameter (YYYY-MM-DD) is required.', [], 422);
        }

        $currentUserId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $result = $this->availabilityService->getAvailableSlots($serviceId, $date, $currentUserId);

        if (!$result['success']) {
            Response::error($result['message'] ?? 'Unable to fetch availability.', [], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'Availability retrieved.',
            'data'    => $result,
        ]);
    }
}
