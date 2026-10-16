<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PackageModel;
use App\Models\CustomerPackageModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use App\Helpers\OfflinePayment;
use App\Helpers\RateLimit;
use App\Helpers\TimeHelper;
use Razorpay\Api\Api as RazorpayApi;

/**
 * Package Service — Combo Packages Engine
 *
 * Handles combo package purchases, Razorpay payment processing, and credit provisioning.
 *
 * Settlement has a single path (settlePaidPurchase) shared by the browser callback
 * (verifyPurchase) and the Razorpay webhook, so a customer who pays and closes the tab
 * still receives their credits. It locks the purchase row, so concurrent callbacks
 * provision credits exactly once.
 */
class PackageService
{
    private const LOCAL_MOCK_SECRET = 'sksl-local-mock-signing-secret';
    /** A pending purchase younger than this is reused instead of opening another gateway order. */
    private const REUSE_PENDING_MINUTES = 30;

    private \PDO $db;
    private PackageModel $packageModel;
    private CustomerPackageModel $customerPackageModel;
    private PaymentModel $paymentModel;
    private UserModel $userModel;

    private string $keyId;
    private string $keySecret;
    private bool $mockMode;

    public function __construct(
        ?\PDO $db = null,
        ?PackageModel $packageModel = null,
        ?CustomerPackageModel $customerPackageModel = null,
        ?PaymentModel $paymentModel = null,
        ?UserModel $userModel = null,
        ?array $gateway = null
    ) {
        $this->db                   = $db ?? getDb();
        $this->packageModel         = $packageModel ?? new PackageModel();
        $this->customerPackageModel = $customerPackageModel ?? new CustomerPackageModel();
        $this->paymentModel         = $paymentModel ?? new PaymentModel();
        $this->userModel            = $userModel ?? new UserModel();

        $this->keyId     = trim((string) ($gateway['key_id']     ?? $_ENV['RAZORPAY_KEY_ID']     ?? ''));
        $this->keySecret = trim((string) ($gateway['key_secret'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? ''));

        if ($gateway !== null && array_key_exists('mock', $gateway)) {
            $this->mockMode = (bool) $gateway['mock'];
        } else {
            $env = (string) config('app.env', 'production');
            $keysMissing = ($this->keyId === '' || $this->keySecret === '');
            $this->mockMode = $keysMissing && in_array($env, ['local', 'testing'], true) && payments_mock_allowed();
        }
    }

    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    private function signingSecret(): string
    {
        if ($this->keySecret !== '') {
            return $this->keySecret;
        }
        return $this->mockMode ? self::LOCAL_MOCK_SECRET : '';
    }

    /**
     * Get all active combo packages.
     */
    public function getActivePackages(): array
    {
        return $this->packageModel->getAll(true);
    }

    /**
     * Get all purchased packages with remaining credits for a customer.
     */
    public function getCustomerPackages(int $userId, bool $activeOnly = false): array
    {
        return $this->customerPackageModel->getByUserId($userId, $activeOnly);
    }

    /**
     * Initiate a Combo Package purchase order via Razorpay.
     *
     * The gateway order is created before any row is written, so no database
     * transaction is held open across a network call, and a customer who clicks
     * "Buy" repeatedly is handed the same pending order instead of a new one.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function createPurchaseOrder(int $userId, int $packageId): array
    {
        if (!OfflinePayment::onlineEnabled()) {
            return ['success' => false, 'message' => 'Online payment is not available right now. Please request the package and pay as instructed.'];
        }

        $package = $this->packageModel->findById($packageId);
        if (!$package || $package['status'] !== 'active') {
            return ['success' => false, 'message' => 'Requested package is not available.'];
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User account not found.'];
        }

        if (!RateLimit::attempt(RateLimit::key('package_purchase', (string) $userId), 15, 3600)) {
            return ['success' => false, 'message' => 'Too many purchase attempts. Please try again later.'];
        }

        // Reuse a recent unpaid purchase of the same package
        $pending = $this->customerPackageModel->findRecentPending($userId, $packageId, self::REUSE_PENDING_MINUTES);
        if ($pending) {
            $payment = $this->paymentModel->findByCustomerPackageId((int) $pending['id']);
            if ($payment && $payment['status'] === 'pending') {
                return [
                    'success' => true,
                    'message' => 'Payment order initiated.',
                    'data'    => $this->orderResponse($user, $pending, (string) $payment['razorpay_order_id']),
                ];
            }
        }

        try {
            $purchaseRef  = 'SKSL-PKG-' . TimeHelper::now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            // packages.price is the GST-inclusive price; the customer is charged exactly that.
            $gstPercent   = (float) ($package['gst_percent'] ?? 18.00);
            $split        = \App\Models\ServiceModel::calculatePricing((float) $package['price'], $gstPercent);
            $baseAmount   = $split['base_price'];
            $gstAmount    = $split['gst_amount'];
            $totalAmount  = $split['total_amount'];
            $validityDays = max(1, (int) ($package['validity_days'] ?? 30));
            $amountPaise  = (int) round($totalAmount * 100);

            // 1. Gateway order first (no DB transaction open)
            if (!$this->mockMode) {
                if ($this->keyId === '' || $this->keySecret === '') {
                    error_log('PackageService: Razorpay keys are not configured; refusing to create order.');
                    return ['success' => false, 'message' => 'Online payments are temporarily unavailable. Please contact SKSL.'];
                }
                try {
                    $api   = new RazorpayApi($this->keyId, $this->keySecret);
                    $order = $api->order->create([
                        'receipt'  => $purchaseRef,
                        'amount'   => $amountPaise,
                        'currency' => 'INR',
                        'notes'    => [
                            'purchase_reference' => $purchaseRef,
                            'package_name'       => (string) $package['name'],
                        ],
                    ]);
                    $orderId = (string) $order['id'];
                } catch (\Throwable $e) {
                    error_log('Package Razorpay Order Create Exception: ' . $e->getMessage());
                    return ['success' => false, 'message' => 'Unable to connect to payment gateway. Please try again.'];
                }
            } else {
                $orderId = 'order_mock_pkg_' . bin2hex(random_bytes(6));
            }

            // 2. Local rows in one short transaction
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare(
                    "INSERT INTO customer_packages (
                        user_id, package_id, purchase_reference,
                        package_name_snapshot, validity_days_snapshot,
                        base_amount, gst_percent, gst_amount, total_amount,
                        payment_status, expires_at, status
                     ) VALUES (
                        :user_id, :pkg_id, :ref,
                        :name_snap, :validity_snap,
                        :base, :gst_pct, :gst_amt, :total,
                        'pending', :expires_at, 'active'
                     )"
                );
                $stmt->execute([
                    'user_id'       => $userId,
                    'pkg_id'        => $packageId,
                    'ref'           => $purchaseRef,
                    'name_snap'     => (string) $package['name'],
                    'validity_snap' => $validityDays,
                    // Provisional: validity restarts from the moment payment succeeds (and runs to the end of the last day).
                    'base'          => $baseAmount,
                    'gst_pct'       => $gstPercent,
                    'gst_amt'       => $gstAmount,
                    'total'         => $totalAmount,
                    'expires_at'    => TimeHelper::now()->modify("+{$validityDays} days")->format('Y-m-d H:i:s'),
                ]);
                $customerPackageId = (int) $this->db->lastInsertId();

                // payments.amount is in rupees everywhere (bookings and packages alike).
                $this->paymentModel->create([
                    'payment_source'      => 'package',
                    'booking_id'          => null,
                    'customer_package_id' => $customerPackageId,
                    'razorpay_order_id'   => $orderId,
                    'amount'              => $totalAmount,
                    'currency'            => 'INR',
                    'status'              => 'pending',
                ]);

                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }

            $purchase = $this->customerPackageModel->findById($customerPackageId);

            return [
                'success' => true,
                'message' => 'Payment order initiated.',
                'data'    => $this->orderResponse($user, $purchase ?? [], $orderId),
            ];
        } catch (\Throwable $e) {
            error_log('PackageService::createPurchaseOrder Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not start this purchase. Please try again.'];
        }
    }

    /**
     * Offline mode: request a combo package without paying. The purchase waits (24 h by default) for
     * staff to receive the money and approve it; the credits and the invoice come on approval.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function requestOfflinePurchase(int $userId, int $packageId): array
    {
        if (!OfflinePayment::offlineMode()) {
            return ['success' => false, 'message' => 'Offline package requests are not open. Please pay online.'];
        }
        $package = $this->packageModel->findById($packageId);
        if (!$package || $package['status'] !== 'active') {
            return ['success' => false, 'message' => 'Requested package is not available.'];
        }
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User account not found.'];
        }
        if (!RateLimit::attempt(RateLimit::key('package_purchase', (string) $userId), 15, 3600)) {
            return ['success' => false, 'message' => 'Too many purchase attempts. Please try again later.'];
        }

        $pending = $this->customerPackageModel->findPendingOffline($userId, $packageId);
        if ($pending) {
            return ['success' => true, 'message' => 'Your package request is waiting for payment.', 'data' => $this->offlinePurchaseResponse($pending) + ['created' => false]];
        }

        try {
            $purchaseRef = 'SKSL-PKG-' . TimeHelper::now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $gstPercent  = (float) ($package['gst_percent'] ?? 18.00);
            $split       = \App\Models\ServiceModel::calculatePricing((float) $package['price'], $gstPercent);
            $validity    = max(1, (int) ($package['validity_days'] ?? 30));
            $due         = OfflinePayment::packageDueAt()->format('Y-m-d H:i:s');

            $this->db->beginTransaction();
            try {
                $this->db->prepare(
                    "INSERT INTO customer_packages (
                        user_id, package_id, purchase_reference, package_name_snapshot, validity_days_snapshot,
                        base_amount, gst_percent, gst_amount, total_amount,
                        payment_status, payment_channel, payment_due_at, expires_at, status
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'offline', ?, ?, 'active')"
                )->execute([
                    $userId, $packageId, $purchaseRef, (string) $package['name'], $validity,
                    $split['base_price'], $gstPercent, $split['gst_amount'], $split['total_amount'],
                    $due, TimeHelper::now()->modify("+{$validity} days")->format('Y-m-d H:i:s'), // provisional: validity restarts on approval
                ]);
                $customerPackageId = (int) $this->db->lastInsertId();
                $this->paymentModel->create([
                    'payment_source'      => 'package',
                    'booking_id'          => null,
                    'customer_package_id' => $customerPackageId,
                    'razorpay_order_id'   => 'offline_' . $purchaseRef,
                    'amount'              => $split['total_amount'],
                    'currency'            => 'INR',
                    'status'              => 'pending',
                ]);
                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
            $purchase = $this->customerPackageModel->findById($customerPackageId);
            return ['success' => true, 'message' => 'Package request received. Please pay to activate it.', 'data' => $this->offlinePurchaseResponse($purchase ?? []) + ['created' => true]];
        } catch (\Throwable $e) {
            error_log('PackageService::requestOfflinePurchase Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not start this purchase. Please try again.'];
        }
    }

    /**
     * @param array<string, mixed> $purchase
     * @return array<string, mixed>
     */
    public function offlinePurchaseResponse(array $purchase): array
    {
        $amount = (float) ($purchase['total_amount'] ?? 0);
        return [
            'customer_package_id' => (int) ($purchase['id'] ?? 0),
            'purchase_reference'  => (string) ($purchase['purchase_reference'] ?? ''),
            'package_name'        => (string) ($purchase['package_name_snapshot'] ?? ''),
            'amount_rupees'       => $amount,
            'payment_due_at'      => $purchase['payment_due_at'] ?? null,
            'upi_link'            => OfflinePayment::upiLink($amount, (string) ($purchase['purchase_reference'] ?? '')),
            'redirect'            => app_url('my-packages'),
        ];
    }

    /** Emails after an offline package request (customer: how to pay; admin: a request is waiting). */
    public function sendOfflineRequestNotices(int $customerPackageId): void
    {
        try {
            $p = $this->customerPackageModel->findById($customerPackageId);
            if (!$p) {
                return;
            }
            $mail = new EmailService();
            $mail->sendOfflinePackageReceived($p);
            $mail->sendAdminAlert(
                '[Action needed] Combo package waiting for payment approval',
                'A combo package request is waiting for payment',
                'When the payment has arrived, open Package Purchases and approve it. The credits are created and the invoice is emailed when you approve.',
                [
                    'Reference'         => (string) $p['purchase_reference'],
                    'Customer'          => (string) $p['user_name'] . ' <' . (string) $p['user_email'] . '> ' . (string) ($p['user_mobile'] ?? ''),
                    'Package'           => (string) $p['package_name_snapshot'],
                    'Amount to receive' => '₹' . number_format((float) $p['total_amount'], 2),
                    'Pay by'            => date('d M Y, g:i A', strtotime((string) $p['payment_due_at'])) . ' IST',
                ],
                'admin_offline_package_request'
            );
        } catch (\Throwable $e) {
            error_log('Offline package request notices failed: ' . $e->getMessage());
        }
    }

    /**
     * Staff confirm the money for an offline package purchase has arrived: credits are created, validity
     * starts now and the invoice is emailed, exactly as after an online payment. A request that lapsed
     * while the money was on its way can still be approved.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function approveOfflinePurchase(int $customerPackageId, int $adminId, string $method, string $reference): array
    {
        $reference = trim($reference, " 	");
        if (!isset(OfflinePayment::METHODS[$method])) {
            return ['success' => false, 'message' => 'Choose how the payment was received.'];
        }
        if ($reference === '' || mb_strlen($reference) > 100 || preg_match('/[\x00-\x1F\x7F]/', $reference)) {
            return ['success' => false, 'message' => 'Enter the payment reference (UPI transaction id, bank reference or receipt number), up to 100 characters.'];
        }

        $purchase = $this->customerPackageModel->findById($customerPackageId);
        if (!$purchase || ($purchase['payment_channel'] ?? '') !== 'offline') {
            return ['success' => false, 'message' => 'This is not an offline payment purchase.'];
        }
        if ($purchase['payment_status'] === 'paid') {
            return ['success' => false, 'message' => 'This package has already been approved.'];
        }
        $payment = $this->paymentModel->findByCustomerPackageId($customerPackageId);
        if (!$payment) {
            return ['success' => false, 'message' => 'The payment record for this purchase is missing.'];
        }

        // Method and reference first, so the invoice that settlement sends already shows them.
        $this->db->prepare(
            "UPDATE customer_packages SET payment_method = ?, payment_reference = ?, approved_by_admin_id = ?
             WHERE id = ? AND payment_status <> 'paid'"
        )->execute([$method, $reference, $adminId, $customerPackageId]);

        $result = $this->settlePaidPurchase($payment, 'offline_' . $purchase['purchase_reference'], 'offline_approved', 'offline_approval', null);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message']];
        }
        return ['success' => true, 'message' => 'Payment approved. The credits are active and the customer has been emailed.', 'data' => $result['data'] ?? []];
    }

    /**
     * Checkout payload for the browser (and mock-mode credentials in local development).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $purchase
     * @return array<string, mixed>
     */
    private function orderResponse(array $user, array $purchase, string $orderId): array
    {
        $total = (float) ($purchase['total_amount'] ?? 0);
        $data = [
            'customer_package_id' => (int) ($purchase['id'] ?? 0),
            'purchase_reference'  => (string) ($purchase['purchase_reference'] ?? ''),
            'package_name'        => (string) ($purchase['package_name_snapshot'] ?? ''),
            'order_id'            => $orderId,
            'amount_paise'        => (int) round($total * 100),
            'total_amount'        => $total,
            'currency'            => 'INR',
            'key_id'              => $this->keyId,
            'is_mock'             => $this->mockMode,
            'customer'            => [
                'name'   => $user['name'],
                'email'  => $user['email'],
                'mobile' => $user['mobile'],
            ],
        ];

        if ($this->mockMode) {
            $mockPaymentId = 'pay_mock_pkg_' . bin2hex(random_bytes(6));
            $data['mock_payment_id'] = $mockPaymentId;
            $data['mock_signature']  = hash_hmac('sha256', $orderId . '|' . $mockPaymentId, $this->signingSecret());
        }

        return $data;
    }

    /**
     * Verify the browser callback signature and provision credits.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function verifyPurchase(
        int $userId,
        string $orderId,
        string $paymentId,
        string $signature
    ): array {
        $secret = $this->signingSecret();
        if ($secret === '') {
            return ['success' => false, 'message' => 'Payment gateway secret is not configured.'];
        }

        $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expectedSig, $signature)) {
            return ['success' => false, 'message' => 'Payment signature verification failed.'];
        }

        $payment = $this->paymentModel->findByOrderId($orderId);
        if (!$payment || empty($payment['customer_package_id'])) {
            return ['success' => false, 'message' => 'Payment record not found for this order.'];
        }

        return $this->settlePaidPurchase($payment, $paymentId, $signature, 'checkout_signature_verified', $userId);
    }

    /**
     * Settle a package payment reported by the (signature-verified, amount-checked) webhook.
     *
     * @param array<string, mixed> $payment  payments row
     * @return array{success: bool, message: string, data?: array}
     */
    public function settleFromWebhook(array $payment, string $paymentId): array
    {
        if (empty($payment['customer_package_id'])) {
            return ['success' => false, 'message' => 'Payment is not linked to a package purchase.'];
        }
        return $this->settlePaidPurchase($payment, $paymentId, 'webhook_signature_verified', 'webhook', null);
    }

    /**
     * Single settlement path: marks the payment paid, starts validity from now,
     * provisions credits once, then sends the invoice email.
     *
     * @param array<string, mixed> $payment
     * @param int|null $expectUserId  When set, the purchase must belong to this customer
     * @return array{success: bool, message: string, data?: array}
     */
    private function settlePaidPurchase(
        array $payment,
        string $paymentId,
        string $signature,
        string $source,
        ?int $expectUserId
    ): array {
        $customerPackageId = (int) $payment['customer_package_id'];

        $this->db->beginTransaction();
        try {
            // Lock the purchase so concurrent callbacks serialise here.
            $stmt = $this->db->prepare('SELECT * FROM customer_packages WHERE id = ? FOR UPDATE');
            $stmt->execute([$customerPackageId]);
            $purchase = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$purchase || ($expectUserId !== null && (int) $purchase['user_id'] !== $expectUserId)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Package purchase record mismatch.'];
            }

            if ($purchase['payment_status'] === 'paid') {
                $this->db->rollBack();
                return [
                    'success' => true,
                    'message' => 'Package has already been confirmed and credits provisioned.',
                    'data'    => $this->customerPackageModel->findById($customerPackageId),
                ];
            }

            $this->paymentModel->markPaid(
                (int) $payment['id'],
                $paymentId,
                $signature,
                json_encode(['verified_at' => date('Y-m-d H:i:s'), 'source' => $source])
            );

            // Validity runs from the moment of payment, not from when the order was opened.
            $upd = $this->db->prepare(
                "UPDATE customer_packages
                 SET payment_status = 'paid',
                     purchased_at   = NOW(),
                     expires_at     = CONCAT(DATE(DATE_ADD(NOW(), INTERVAL validity_days_snapshot DAY)), ' 23:59:59'),
                     status         = 'active'
                 WHERE id = ? AND payment_status <> 'paid'"
            );
            $upd->execute([$customerPackageId]);

            // Credits are inserted once; the (customer_package_id, service_id) unique key
            // makes any accidental repeat fail loudly instead of resetting used credits.
            $creditStmt = $this->db->prepare(
                "INSERT INTO customer_package_credits (
                    customer_package_id, service_id, duration_minutes,
                    allocated_quantity, used_quantity, remaining_quantity
                 ) VALUES (?, ?, ?, ?, 0, ?)"
            );
            foreach ($this->packageModel->getItems((int) $purchase['package_id']) as $item) {
                $qty = (int) $item['quantity'];
                $creditStmt->execute([$customerPackageId, (int) $item['service_id'], (int) $item['duration_minutes'], $qty, $qty]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('PackageService::settlePaidPurchase Exception: ' . $e->getMessage());
            return [
                'success'   => false,
                'retryable' => true,
                'message'   => 'We could not activate your package. Please contact SKSL with Payment ID: ' . $paymentId,
            ];
        }

        $updated = $this->customerPackageModel->findById($customerPackageId);
        if ($updated) {
            $this->notifyPurchase($updated);
        }

        return [
            'success' => true,
            'message' => 'Package successfully purchased! Modality credits are now ready to use.',
            'data'    => $updated,
        ];
    }

    /** Best-effort purchase email with the tax invoice attached (runs once, after commit). */
    private function notifyPurchase(array $purchase): void
    {
        try {
            $pdfPath = null;
            try {
                $pdfPath = (new InvoiceService($this->db))->generatePackageInvoicePdf($purchase);
            } catch (\Throwable $e) {
                error_log('Package invoice generation failed: ' . $e->getMessage());
            }

            $sessions = [];
            foreach ((array) ($purchase['credits'] ?? []) as $credit) {
                $sessions[] = (int) $credit['allocated_quantity'] . ' × ' . $credit['service_name'];
            }

            (new EmailService())->sendNotice(
                (string) $purchase['user_email'],
                (string) $purchase['user_name'],
                'Package confirmed: ' . $purchase['package_name_snapshot'] . ' (' . $purchase['purchase_reference'] . ')',
                'Your package is ready',
                'Payment received. Your session credits are active now' . ($pdfPath ? ' and your GST tax invoice is attached.' : '.'),
                [
                    'Reference'  => (string) $purchase['purchase_reference'],
                    'Package'    => (string) $purchase['package_name_snapshot'],
                    'Sessions'   => implode(', ', $sessions),
                    'Valid until' => date('d M Y', strtotime((string) $purchase['expires_at'])),
                    'Total paid' => 'INR ' . number_format((float) $purchase['total_amount'], 2) . ' (incl. GST)',
                ],
                'package_purchase',
                null,
                $pdfPath
            );
        } catch (\Throwable $e) {
            error_log('Package purchase notification exception: ' . $e->getMessage());
        }
    }
}
