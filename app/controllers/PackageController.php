<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PackageService;
use App\Middleware\CustomerAuth;
use App\Helpers\Response;

/**
 * Package Controller
 *
 * Handles customer-facing combo packages, package checkout, and customer credit dashboard.
 */
class PackageController
{
    private PackageService $packageService;

    public function __construct(?PackageService $packageService = null)
    {
        $this->packageService = $packageService ?? new PackageService();
    }

    /**
     * Show public Combo Packages catalog page.
     */
    public function index(): void
    {
        $packages = $this->packageService->getActivePackages();
        $title = 'Combo Packages & Modality Bundles — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/packages.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * JSON API: List all active packages with items.
     */
    public function apiIndex(): void
    {
        $packages = $this->packageService->getActivePackages();
        Response::json([
            'success' => true,
            'data'    => $packages,
        ]);
    }

    /**
     * Customer Dashboard: My Packages & Modality Credits.
     */
    public function myPackages(): void
    {
        CustomerAuth::handle();
        $userId = (int) $_SESSION['user_id'];

        $all = $this->packageService->getCustomerPackages($userId);
        // Unpaid and abandoned checkouts are not "packages" the customer owns...
        $customerPackages = array_values(array_filter(
            $all,
            static fn(array $cp): bool => ($cp['payment_status'] ?? '') === 'paid'
        ));
        // ...but an offline request that is still waiting for payment is shown, with how to pay.
        $pendingRequests = array_values(array_filter(
            $all,
            static fn(array $cp): bool => ($cp['payment_status'] ?? '') === 'pending'
                && ($cp['payment_channel'] ?? '') === 'offline'
                && ($cp['status'] ?? '') === 'active'
                && strtotime((string) ($cp['payment_due_at'] ?? '')) > time()
        ));
        $title = 'My Modality Packages & Credits — Sara Kinetic Sports Lab';
        $viewFile = dirname(__DIR__) . '/views/pages/my-packages.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * GET /my-packages/{ref}/invoice — GST tax invoice for a paid package purchase.
     */
    public function downloadInvoice(array $params = []): void
    {
        CustomerAuth::handle();
        $reference = (string) ($params['ref'] ?? '');
        (new \App\Services\InvoiceService())->downloadPackageInvoice($reference, (int) $_SESSION['user_id']);
    }

    /**
     * Initiate package purchase order via Razorpay.
     * POST /api/packages/{id}/purchase
     */
    public function purchase(array $params): void
    {
        CustomerAuth::handle();
        $userId = (int) $_SESSION['user_id'];
        $packageId = (int) ($params['id'] ?? 0);

        if ($packageId <= 0) {
            Response::error('Invalid package ID.', [], 422);
        }

        if (\App\Helpers\OfflinePayment::offlineMode()) {
            // Offline mode: the package is requested now and activated when staff approve the payment.
            $result = $this->packageService->requestOfflinePurchase($userId, $packageId);
            if (!$result['success']) {
                Response::error($result['message'], [], 422);
            }
            $payload = ['success' => true, 'message' => $result['message'], 'data' => $result['data'] + ['offline' => true]];
            if (!empty($result['data']['created'])) {
                $svc = $this->packageService;
                $cpId = (int) $result['data']['customer_package_id'];
                Response::jsonThen($payload, static function () use ($svc, $cpId): void {
                    $svc->sendOfflineRequestNotices($cpId);
                });
            }
            Response::json($payload);
        }

        $result = $this->packageService->createPurchaseOrder($userId, $packageId);
        if (!$result['success']) {
            Response::error($result['message'], [], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    /**
     * Verify payment and provision credits.
     * POST /api/packages/verify
     */
    public function verifyPurchase(): void
    {
        CustomerAuth::handle();
        $userId = (int) $_SESSION['user_id'];

        $orderId   = trim((string) ($_POST['razorpay_order_id'] ?? ''));
        $paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
        $signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            $json = request_json();
            if (is_array($json)) {
                $orderId   = trim((string) ($json['razorpay_order_id'] ?? ''));
                $paymentId = trim((string) ($json['razorpay_payment_id'] ?? ''));
                $signature = trim((string) ($json['razorpay_signature'] ?? ''));
            }
        }

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            Response::error('Missing payment verification parameters.', [], 422);
        }

        $result = $this->packageService->verifyPurchase($userId, $orderId, $paymentId, $signature);
        if (!$result['success']) {
            Response::error($result['message'], [], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }
}
