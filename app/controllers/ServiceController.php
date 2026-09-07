<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Helpers\Response;

class ServiceController
{
    private ServiceModel $serviceModel;

    public function __construct(?ServiceModel $serviceModel = null)
    {
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * Show public services catalog page.
     */
    public function index(): void
    {
        $services = $this->serviceModel->getAllActive();

        // Attach precomputed pricing breakdown
        foreach ($services as &$service) {
            $pricing = ServiceModel::calculatePricing(
                (float) $service['price'],
                (float) ($service['gst_percent'] ?? 18.0)
            );
            $service['pricing'] = $pricing;
        }
        unset($service);

        $title = 'Recovery Services & Pricing — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/services.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * JSON API: List all active services.
     */
    public function apiIndex(): void
    {
        $services = $this->serviceModel->getAllActive();
        Response::json([
            'success' => true,
            'message' => 'Active services retrieved.',
            'data'    => $services,
        ]);
    }

    /**
     * JSON API: Get single service detail.
     */
    public function apiShow(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $service = $this->serviceModel->findActiveById($id);

        if (!$service) {
            Response::notFound('Service not found or inactive.');
        }

        $service['pricing'] = ServiceModel::calculatePricing(
            (float) $service['price'],
            (float) ($service['gst_percent'] ?? 18.0)
        );

        Response::json([
            'success' => true,
            'message' => 'Service details retrieved.',
            'data'    => $service,
        ]);
    }
}
