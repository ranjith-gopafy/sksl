<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use Razorpay\Api\Api as RazorpayApi;

/**
 * Payment Service
 *
 * Server-side Razorpay order creation, payment signature verification,
 * idempotent status transitions, and webhook reconciliation.
 *
 * Security model (fail closed):
 *  - A signature is ALWAYS verified. There is no "secret missing, skip check" path.
 *  - Mock mode (no Razorpay network calls) is only allowed when APP_ENV is local/testing,
 *    or when explicitly injected by tests. Even in mock mode the signature is verified
 *    against a fixed local secret that the server itself hands to the mock checkout.
 *  - Webhooks require RAZORPAY_WEBHOOK_SECRET. Without it the endpoint answers 503 and
 *    confirms nothing.
 *  - Only a booking that is still `pending` can become `confirmed`. A payment captured
 *    for a cancelled booking is recorded and flagged for staff; it never re-opens the slot.
 */
class PaymentService
{
    /** Signing secret used in local mock mode only. Never valid in production. */
    private const LOCAL_MOCK_SECRET = 'sksl-local-mock-signing-secret';

    private \PDO $db;
    private PaymentModel $paymentModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;
    private ServiceModel $serviceModel;
    private UserModel $userModel;

    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;
    private bool $mockMode;

    /**
     * @param array{key_id?: string, key_secret?: string, webhook_secret?: string, mock?: bool}|null $gateway
     *        Optional gateway override (used by tests). Defaults to .env values.
     */
    public function __construct(
        ?\PDO $db = null,
        ?PaymentModel $paymentModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null,
        ?ServiceModel $serviceModel = null,
        ?UserModel $userModel = null,
        ?array $gateway = null
    ) {
        $this->db            = $db ?? getDb();
        $this->paymentModel  = $paymentModel ?? new PaymentModel();
        $this->bookingModel  = $bookingModel ?? new BookingModel();
        $this->holdModel     = $holdModel ?? new BookingHoldModel();
        $this->serviceModel  = $serviceModel ?? new ServiceModel();
        $this->userModel     = $userModel ?? new UserModel();

        $this->keyId         = trim((string) ($gateway['key_id']         ?? $_ENV['RAZORPAY_KEY_ID']         ?? ''));
        $this->keySecret     = trim((string) ($gateway['key_secret']     ?? $_ENV['RAZORPAY_KEY_SECRET']     ?? ''));
        $this->webhookSecret = trim((string) ($gateway['webhook_secret'] ?? $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? ''));

        if ($gateway !== null && array_key_exists('mock', $gateway)) {
            $this->mockMode = (bool) $gateway['mock'];
        } else {
            $env = (string) config('app.env', 'production');
            $keysMissing = ($this->keyId === '' || $this->keySecret === '');
            $this->mockMode = $keysMissing && in_array($env, ['local', 'testing'], true);
        }
    }

    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Secret used to verify checkout signatures. Empty string means "cannot verify".
     */
    private function signingSecret(): string
    {
        if ($this->keySecret !== '') {
            return $this->keySecret;
        }
        return $this->mockMode ? self::LOCAL_MOCK_SECRET : '';
    }

    public static function signCheckout(string $orderId, string $paymentId, string $secret): string
    {
        return hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    }

    /**
     * Create Razorpay order for an active booking hold.
     * Amount is authoritatively derived from server-calculated totals in integer paise.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function createOrder(int $userId, string $bookingReference): array
    {
        if (!$this->mockMode && ($this->keyId === '' || $this->keySecret === '')) {
            error_log('PaymentService: Razorpay keys are not configured; refusing to create order.');
            return [
                'success' => false,
                'message' => 'Online payments are temporarily unavailable. Please contact SKSL.',
            ];
        }

        // 1. Verify active, non-expired hold
        $hold = $this->holdModel->findActiveByReference($bookingReference);
        if (!$hold) {
            return [
                'success' => false,
                'message' => 'Booking hold has expired or was not found. Please select a slot again.',
            ];
        }

        if ((int) $hold['user_id'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Unauthorized booking hold access.',
            ];
        }

        // 2. Check or create pending booking record
        $booking = $this->bookingModel->findByReference($bookingReference);
        if ($booking && (int) $booking['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Unauthorized booking access.'];
        }

        if ($booking && ($booking['booking_status'] !== 'pending' || $booking['payment_status'] === 'paid')) {
            // Never open a second gateway order for a booking that is already settled.
            return [
                'success' => false,
                'message' => 'This booking is already ' . $booking['booking_status'] . '. No further payment is required.',
                'data'    => [
                    'booking_reference' => $bookingReference,
                    'booking_status'    => $booking['booking_status'],
                    'payment_status'    => $booking['payment_status'],
                ],
            ];
        }

        if (!$booking) {
            $service = $this->serviceModel->findById((int) $hold['service_id']);
            if (!$service) {
                return ['success' => false, 'message' => 'Service not found.'];
            }

            $gstPercent = (float) ($service['gst_percent'] ?? config('app.booking.gst_rate', 18));
            $pricing = ServiceModel::calculatePricing((float) $service['price'], $gstPercent);

            $bookingId = $this->bookingModel->create([
                'booking_reference'        => $bookingReference,
                'user_id'                  => $userId,
                'service_id'               => (int) $hold['service_id'],
                'booking_date'             => $hold['booking_date'],
                'start_time'               => $hold['start_time'],
                'end_time'                 => $hold['end_time'],
                'service_name_snapshot'    => (string) $service['name'],
                'service_duration_minutes' => (int) $service['duration_minutes'],
                'checkin_buffer_minutes'   => (int) config('app.booking.checkin_buffer_minutes', 0),
                'checkout_buffer_minutes'  => (int) config('app.booking.checkout_buffer_minutes', 0),
                'base_amount'              => $pricing['base_price'],
                'gst_percent'              => $gstPercent,
                'gst_amount'               => $pricing['gst_amount'],
                'convenience_fee'          => 0.00,
                'total_amount'             => $pricing['total_amount'],
                'booking_status'           => 'pending',
                'payment_status'           => 'pending',
                'health_declared_at'       => $hold['health_declared_at'] ?? null,
            ]);

            $booking = $this->bookingModel->findById($bookingId);
        }

        // 3. Idempotent check: has a pending payment order already been issued?
        $existingPayment = $this->paymentModel->findByBookingId((int) $booking['id']);
        if ($existingPayment && $existingPayment['status'] === 'pending') {
            $orderId = $existingPayment['razorpay_order_id'];
        } else {
            // Amount in integer paise (₹529.82 -> 52982 paise)
            $amountPaise = (int) round(((float) $booking['total_amount']) * 100);

            if (!$this->mockMode) {
                try {
                    $api = new RazorpayApi($this->keyId, $this->keySecret);
                    $order = $api->order->create([
                        'receipt'  => $bookingReference,
                        'amount'   => $amountPaise,
                        'currency' => 'INR',
                        'notes'    => [
                            'booking_reference' => $bookingReference,
                            'service_name'      => $booking['service_name'] ?? 'Recovery Modality',
                        ],
                    ]);
                    $orderId = (string) $order['id'];
                } catch (\Throwable $e) {
                    error_log('Razorpay Order Create Exception: ' . $e->getMessage());
                    return [
                        'success' => false,
                        'message' => 'Unable to initiate payment gateway. Please try again.',
                    ];
                }
            } else {
                $orderId = 'order_mock_' . bin2hex(random_bytes(8));
            }

            $this->paymentModel->create([
                'booking_id'        => (int) $booking['id'],
                'razorpay_order_id' => $orderId,
                'amount'            => (float) $booking['total_amount'],
                'currency'          => 'INR',
                'status'            => 'pending',
            ]);
        }

        $user = $this->userModel->findById($userId);

        $data = [
            'booking_reference' => $bookingReference,
            'razorpay_order_id' => $orderId,
            'key_id'            => $this->keyId,
            'amount_rupees'     => (float) $booking['total_amount'],
            'amount_paise'      => (int) round(((float) $booking['total_amount']) * 100),
            'currency'          => 'INR',
            'service_name'      => $booking['service_name'] ?? 'SKSL Service',
            'customer_name'     => $user['name'] ?? '',
            'customer_email'    => $user['email'] ?? '',
            'customer_mobile'   => $user['mobile'] ?? '',
            'is_mock'           => $this->mockMode,
        ];

        if ($this->mockMode) {
            // The mock checkout has no gateway to sign for it. The server issues a
            // payment id and a signature that the normal verification path will check.
            $mockPaymentId = 'pay_mock_' . bin2hex(random_bytes(6));
            $data['mock_payment_id'] = $mockPaymentId;
            $data['mock_signature']  = self::signCheckout($orderId, $mockPaymentId, $this->signingSecret());
        }

        return [
            'success' => true,
            'message' => 'Razorpay order created.',
            'data'    => $data,
        ];
    }

    /**
     * Verify payment signature server-side and transition booking to confirmed state.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function verifyPayment(
        int $userId,
        string $bookingReference,
        string $orderId,
        string $paymentId,
        string $signature
    ): array {
        if ($bookingReference === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return ['success' => false, 'message' => 'Missing required payment verification parameters.'];
        }

        $booking = $this->bookingModel->findByReference($bookingReference);
        if (!$booking || (int) $booking['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Unauthorized or invalid booking reference.'];
        }

        $payment = $this->paymentModel->findByOrderId($orderId);
        if (!$payment || (int) $payment['booking_id'] !== (int) $booking['id']) {
            return ['success' => false, 'message' => 'Order mismatch for this booking.'];
        }

        // Idempotency: already verified -> return success safely
        if ($payment['status'] === 'paid' && $booking['booking_status'] === 'confirmed') {
            return [
                'success' => true,
                'message' => 'Payment already verified.',
                'data'    => [
                    'booking_reference'   => $bookingReference,
                    'razorpay_payment_id' => $payment['razorpay_payment_id'],
                    'already_confirmed'   => true,
                ],
            ];
        }

        // Signature verification — mandatory
        $secret = $this->signingSecret();
        if ($secret === '') {
            error_log('PaymentService: no signing secret configured; refusing to verify payment ' . $paymentId);
            return [
                'success' => false,
                'message' => 'Payment verification is unavailable. Please contact SKSL with Payment ID: ' . $paymentId,
            ];
        }

        $expectedSignature = self::signCheckout($orderId, $paymentId, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            $this->paymentModel->markFailed((int) $payment['id'], 'Signature mismatch');
            return [
                'success' => false,
                'message' => 'Payment signature verification failed. Please contact support.',
            ];
        }

        return $this->settlePayment($payment, $booking, $paymentId, $signature, 'checkout', $bookingReference);
    }

    /**
     * Process Razorpay Webhook event idempotently.
     *
     * @return array{success: bool, message: string, code: int}
     */
    public function handleWebhook(string $rawBody, string $signatureHeader): array
    {
        if ($this->webhookSecret === '') {
            error_log('PaymentService: RAZORPAY_WEBHOOK_SECRET is not configured; webhook rejected.');
            return ['success' => false, 'message' => 'Webhook not configured.', 'code' => 503];
        }

        if ($signatureHeader === '') {
            return ['success' => false, 'message' => 'Missing webhook signature.', 'code' => 400];
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        if (!hash_equals($expectedSignature, $signatureHeader)) {
            return ['success' => false, 'message' => 'Invalid webhook signature.', 'code' => 400];
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return ['success' => false, 'message' => 'Invalid JSON payload.', 'code' => 400];
        }

        $eventType = (string) ($event['event'] ?? '');

        if ($eventType !== 'payment.captured' && $eventType !== 'order.paid') {
            return ['success' => true, 'message' => 'Event ignored.', 'code' => 200];
        }

        $paymentEntity = $event['payload']['payment']['entity'] ?? null;
        if (!is_array($paymentEntity)) {
            return ['success' => true, 'message' => 'No payment entity in payload.', 'code' => 200];
        }

        $orderId   = (string) ($paymentEntity['order_id'] ?? '');
        $paymentId = (string) ($paymentEntity['id'] ?? '');
        if ($orderId === '' || $paymentId === '') {
            return ['success' => true, 'message' => 'Payment entity incomplete.', 'code' => 200];
        }

        $payment = $this->paymentModel->findByOrderId($orderId);
        if (!$payment) {
            error_log("Webhook: unknown order {$orderId} for payment {$paymentId}");
            return ['success' => true, 'message' => 'Unknown order.', 'code' => 200];
        }

        if ($payment['status'] === 'paid') {
            return ['success' => true, 'message' => 'Already processed.', 'code' => 200];
        }

        // Amount and currency must match the order we created.
        $expectedPaise = (int) round(((float) $payment['amount']) * 100);
        $gotPaise      = (int) ($paymentEntity['amount'] ?? -1);
        $gotCurrency   = strtoupper((string) ($paymentEntity['currency'] ?? 'INR'));
        if ($gotPaise !== $expectedPaise || $gotCurrency !== 'INR') {
            error_log("Webhook: amount mismatch for {$orderId}: expected {$expectedPaise} INR, got {$gotPaise} {$gotCurrency}");
            $this->paymentModel->markFailed((int) $payment['id'], json_encode([
                'webhook_event' => $eventType,
                'reason'        => 'amount_mismatch',
                'expected'      => $expectedPaise,
                'received'      => $gotPaise,
                'currency'      => $gotCurrency,
            ]));
            return ['success' => true, 'message' => 'Amount mismatch recorded.', 'code' => 200];
        }

        $booking = $this->bookingModel->findById((int) $payment['booking_id']);
        if (!$booking) {
            return ['success' => true, 'message' => 'Booking not found.', 'code' => 200];
        }

        $result = $this->settlePayment(
            $payment,
            $booking,
            $paymentId,
            'webhook_signature_verified',
            'webhook:' . $eventType,
            (string) $booking['booking_reference']
        );

        return [
            'success' => true,
            'message' => $result['success'] ? 'Webhook processed.' : ('Webhook recorded: ' . $result['message']),
            'code'    => 200,
        ];
    }

    /**
     * Shared settlement path for checkout verification and webhooks.
     * Marks the payment paid, confirms the booking only if still pending,
     * releases the hold, and sends notifications exactly once.
     *
     * @param array<string, mixed> $payment
     * @param array<string, mixed> $booking
     * @return array{success: bool, message: string, data?: array}
     */
    private function settlePayment(
        array $payment,
        array $booking,
        string $paymentId,
        string $signature,
        string $source,
        string $bookingReference
    ): array {
        $this->db->beginTransaction();

        try {
            $this->paymentModel->markPaid(
                (int) $payment['id'],
                $paymentId,
                $signature,
                json_encode(['verified_at' => date('Y-m-d H:i:s'), 'source' => $source])
            );

            $transitioned = $this->bookingModel->confirmIfPending((int) $booking['id']);
            $this->holdModel->release($bookingReference);

            if (!$transitioned) {
                // Money arrived for a booking that is no longer pending (e.g. cancelled).
                // Record payment, never re-open the slot, and flag for staff follow-up.
                $fresh = $this->bookingModel->findById((int) $booking['id']);
                $status = (string) ($fresh['booking_status'] ?? 'unknown');
                if ($status !== 'confirmed' && $status !== 'completed') {
                    $this->bookingModel->markPaymentReceivedAfterClose((int) $booking['id']);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Payment settlement transaction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error during confirmation. Please contact support with Payment ID: ' . $paymentId,
            ];
        }

        $updatedBooking = $this->bookingModel->findById((int) $booking['id']);

        if (!$transitioned) {
            $status = (string) ($updatedBooking['booking_status'] ?? 'unknown');
            if ($status === 'confirmed' || $status === 'completed') {
                return [
                    'success' => true,
                    'message' => 'Payment already verified.',
                    'data'    => [
                        'booking_reference'   => $bookingReference,
                        'razorpay_payment_id' => $paymentId,
                        'already_confirmed'   => true,
                    ],
                ];
            }

            error_log("Payment {$paymentId} received for booking {$bookingReference} in status {$status}; flagged for staff.");
            try {
                if ($updatedBooking) {
                    (new EmailService())->sendAdminPaymentAfterCloseAlert($updatedBooking, $paymentId);
                }
            } catch (\Throwable $mailEx) {
                error_log('Post-close payment alert exception: ' . $mailEx->getMessage());
            }

            return [
                'success' => false,
                'message' => 'Your payment was received, but this booking is ' . $status . '. Please contact SKSL with Payment ID: ' . $paymentId,
            ];
        }

        // Post-commit: invoice + notifications (only the path that confirmed sends them)
        try {
            $invoiceService = new InvoiceService($this->db, $this->bookingModel, $this->paymentModel);
            $pdfPath = $invoiceService->generateInvoicePdf($bookingReference);

            if ($updatedBooking) {
                $emailService = new EmailService();
                $emailService->sendBookingConfirmation($updatedBooking, $pdfPath);
                $emailService->sendAdminBookingNotification($updatedBooking);
            }
        } catch (\Throwable $mailEx) {
            error_log('Post-payment notification exception: ' . $mailEx->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Payment successfully verified! Your recovery session is confirmed.',
            'data'    => [
                'booking_reference'   => $bookingReference,
                'service_name'        => $booking['service_name'],
                'booking_date'        => $booking['booking_date'],
                'start_time'          => $booking['start_time'],
                'end_time'            => $booking['end_time'],
                'amount'              => (float) $booking['total_amount'],
                'razorpay_payment_id' => $paymentId,
            ],
        ];
    }
}
