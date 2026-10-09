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
        $durationOptionId = !empty($_GET['duration_option_id']) ? (int) $_GET['duration_option_id'] : null;

        // Redeeming a package credit books the session length the package was sold with (e.g. 10 min),
        // which can differ from the service's normal length. Only the owner's own active package counts.
        $creditMinutes = null;
        $packageId = !empty($_GET['customer_package_id']) ? (int) $_GET['customer_package_id'] : 0;
        if ($packageId > 0 && $currentUserId !== null) {
            $package = (new \App\Models\CustomerPackageModel())->findActiveForUser($currentUserId, $packageId);
            foreach (($package['credits'] ?? []) as $credit) {
                if ((int) $credit['service_id'] === $serviceId) {
                    $creditMinutes = (int) $credit['duration_minutes'];
                    break;
                }
            }
        }

        $result = $this->availabilityService->getAvailableSlots($serviceId, $date, $currentUserId, $durationOptionId, $creditMinutes);

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
