<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;

class HomeController
{
    private ServiceModel $serviceModel;

    public function __construct(?ServiceModel $serviceModel = null)
    {
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    /**
     * Homepage.
     */
    public function index(): void
    {
        $services = $this->serviceModel->getAllActive();

        // Attach precomputed pricing
        foreach ($services as &$service) {
            $service['pricing'] = ServiceModel::calculatePricing(
                (float) $service['price'],
                (float) ($service['gst_percent'] ?? 18.0)
            );
        }
        // Fetch active hero banner
        $bannerModel = new \App\Models\HeroBannerModel();
        $heroBanner = $bannerModel->getActive();

        $title = 'Sara Kinetic Sports Lab — Recover. Recharge. Perform.';
        $viewFile = dirname(__DIR__) . '/views/pages/home.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Privacy Policy page.
     */
    public function privacy(): void
    {
        $title = 'Privacy Policy — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/privacy-policy.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Terms of Service page.
     */
    public function terms(): void
    {
        $title = 'Terms of Service — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/terms.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Cancellation & Refund Policy page.
     */
    public function cancellation(): void
    {
        $title = 'Cancellation & Refund Policy — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/cancellation-refund.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * Contact Us page.
     */
    public function contact(): void
    {
        $title = 'Contact & Location — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/contact.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }
}
