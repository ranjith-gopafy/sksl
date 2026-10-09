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
            $this->mockMode = $keysMissing && in_array($env, ['local', 'testing'], true) && payments_mock_allowed();
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
        // Two simultaneous requests for the same hold (a double click, two tabs) would otherwise both
        // pass "no booking yet" and the second would hit a duplicate-key error or open a second order.
        $result = \App\Helpers\RateLimit::serialize('create_order:' . $bookingReference, fn () => $this->doCreateOrder($userId, $bookingReference));

        return $result ?? ['success' => false, 'message' => 'The server is busy. Please try again in a moment.'];
    }

    /**
     * @return array{success: bool, message: string, data?: array}
     */
    private function doCreateOrder(int $userId, string $bookingReference): array
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

            $durationMinutes  = (int) $service['duration_minutes'];
            $basePrice        = (float) $service['price'];
            $durationOptionId = !empty($hold['duration_option_id']) ? (int) $hold['duration_option_id'] : null;

            if ($durationOptionId !== null) {
                $durationOptionModel = new \App\Models\DurationOptionModel();
                $option = $durationOptionModel->findById($durationOptionId);
                if (!$option || (int) $option['service_id'] !== (int) $service['id']) {
                    // The hold reserved this option's time window; silently falling back to the service's
                    // default price would bill a longer session at the shorter session's price.
                    return ['success' => false, 'message' => 'The chosen session length is no longer available. Please select your slot again.'];
                }
                $durationMinutes = (int) $option['duration_minutes'];
                $basePrice       = (float) $option['price'];
            }

            $gstPercent = (float) ($service['gst_percent'] ?? config('app.booking.gst_rate', 18));
            $pricing = ServiceModel::calculatePricing($basePrice, $gstPercent);

            $bookingId = $this->bookingModel->create([
                'booking_reference'        => $bookingReference,
                'user_id'                  => $userId,
                'service_id'               => (int) $hold['service_id'],
                'booking_type'             => 'standard',
                'payment_mode'             => 'payment_required',
                'duration_option_id'       => $durationOptionId,
                'booking_date'             => $hold['booking_date'],
                'start_time'               => $hold['start_time'],
                'end_time'                 => $hold['end_time'],
                'service_name_snapshot'    => (string) $service['name'],
                'service_duration_minutes' => $durationMinutes,
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

        // Already settled, but the booking is closed: staff were flagged the first time.
        if ($payment['status'] === 'paid') {
            return [
                'success' => false,
                'message' => 'Your payment was received, but this booking is ' . $booking['booking_status'] . '. Please contact SKSL with Payment ID: ' . $payment['razorpay_payment_id'],
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
            // Deliberately NOT marking the payment failed: any customer can post a bad signature, and a
            // failed row would make createOrder open a second, payable order for the same booking.
            error_log('PaymentService: signature mismatch for order ' . $orderId);
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
            try {
                (new EmailService())->sendAdminAlert(
                    '[Action needed] Payment for an unknown order',
                    'Razorpay payment with no matching order',
                    'A captured payment arrived for an order that does not exist in SKSL. Check the Razorpay dashboard and refund or link it manually.',
                    ['Order' => $orderId, 'Payment' => $paymentId],
                    'admin_payment_unknown_order'
                );
            } catch (\Throwable $mailEx) {
                error_log('Unknown order alert failed: ' . $mailEx->getMessage());
            }
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
            // Money may genuinely have been taken, so the payment is left as it is and staff are told.
            try {
                (new EmailService())->sendAdminAlert(
                    '[Action needed] Payment amount mismatch',
                    'Payment amount does not match the order',
                    'Razorpay reported a payment whose amount or currency differs from the order SKSL created. Nothing was confirmed. Check it in the Razorpay dashboard and refund or resolve it manually.',
                    ['Order' => $orderId, 'Payment' => $paymentId, 'Expected (paise)' => (string) $expectedPaise, 'Received' => $gotPaise . ' ' . $gotCurrency],
                    'admin_payment_mismatch'
                );
            } catch (\Throwable $mailEx) {
                error_log('Amount mismatch alert failed: ' . $mailEx->getMessage());
            }
            return ['success' => true, 'message' => 'Amount mismatch recorded.', 'code' => 200];
        }

        // Combo-package purchases settle through PackageService (credits + invoice).
        if (($payment['payment_source'] ?? 'booking') === 'package') {
            $result = (new PackageService($this->db))->settleFromWebhook($payment, $paymentId);
            if (!$result['success'] && !empty($result['retryable'])) {
                return ['success' => false, 'message' => 'Temporary failure, please retry.', 'code' => 500];
            }
            return [
                'success' => true,
                'message' => $result['success'] ? 'Webhook processed.' : ('Webhook recorded: ' . $result['message']),
                'code'    => 200,
            ];
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

        if (!$result['success'] && !empty($result['retryable'])) {
            // Database trouble, not a business outcome: let Razorpay retry (settlement is idempotent).
            return ['success' => false, 'message' => 'Temporary failure, please retry.', 'code' => 500];
        }

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
            $paidNow = $this->paymentModel->markPaid(
                (int) $payment['id'],
                $paymentId,
                $signature,
                json_encode(['verified_at' => date('Y-m-d H:i:s'), 'source' => $source])
            );

            // A hold lasts 10 minutes but a pending booking lives much longer, so by the time the
            // money arrives the slot may have been sold to someone else. Re-check under the service
            // lock before confirming; if it is gone the booking is closed and staff refund instead.
            $transitioned = false;
            if ($this->slotStillAvailable($booking, $bookingReference)) {
                $transitioned = $this->bookingModel->confirmIfPending((int) $booking['id']);
            } else {
                $this->bookingModel->transitionStatus((int) $booking['id'], 'pending', 'expired');
            }
            $this->holdModel->release($bookingReference);

            if (!$transitioned) {
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
                'success'   => false,
                'retryable' => true,
                'message'   => 'Database error during confirmation. Please contact support with Payment ID: ' . $paymentId,
            ];
        }

        $updatedBooking = $this->bookingModel->findById((int) $booking['id']);

        if (!$transitioned) {
            $status = (string) ($updatedBooking['booking_status'] ?? 'unknown');
            if ($status === 'confirmed' || $status === 'completed') {
                // The booking was already paid. If THIS call recorded a different successful payment,
                // the customer has been charged twice: tell staff to refund the extra one.
                if ($paidNow) {
                    $this->alertAdmin(
                        '[Action needed] Customer charged twice',
                        'Duplicate payment for one booking',
                        'A second payment was captured for a booking that was already paid. Refund the extra payment in the Razorpay dashboard.',
                        ['Booking' => $bookingReference, 'Extra payment' => $paymentId, 'Order' => (string) $payment['razorpay_order_id']],
                        'admin_duplicate_payment'
                    );
                }
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
            if ($paidNow && $updatedBooking) {
                try {
                    (new EmailService())->sendAdminPaymentAfterCloseAlert($updatedBooking, $paymentId);
                } catch (\Throwable $mailEx) {
                    error_log('Post-close payment alert exception: ' . $mailEx->getMessage());
                }
            }

            return [
                'success' => false,
                'message' => 'Your payment was received, but this booking is ' . $status . '. Please contact SKSL with Payment ID: ' . $paymentId,
            ];
        }

        // Post-commit (only the path that confirmed sends these). The invoice, the customer email
        // and the admin alert are independent: a PDF or business-identity problem must never stop
        // the customer or staff hearing about a paid booking.
        $pdfPath = null;
        try {
            $invoiceService = new InvoiceService($this->db, $this->bookingModel, $this->paymentModel);
            $pdfPath = $invoiceService->generateInvoicePdf($bookingReference);
        } catch (\Throwable $invEx) {
            error_log('Invoice generation failed for ' . $bookingReference . ': ' . $invEx->getMessage());
            $this->alertAdmin(
                '[Action needed] Invoice could not be generated',
                'Tax invoice failed for a paid booking',
                'The booking is confirmed and the customer was emailed without an invoice. Fix the cause (business GSTIN/address settings, PDF library, disk) and resend the invoice.',
                ['Booking' => $bookingReference, 'Reason' => $invEx->getMessage()],
                'admin_invoice_failed'
            );
        }

        if ($updatedBooking) {
            try {
                (new EmailService())->sendBookingConfirmation($updatedBooking, $pdfPath);
            } catch (\Throwable $mailEx) {
                error_log('Booking confirmation email exception: ' . $mailEx->getMessage());
            }
            try {
                (new EmailService())->sendAdminBookingNotification($updatedBooking);
            } catch (\Throwable $mailEx) {
                error_log('Admin booking notification exception: ' . $mailEx->getMessage());
            }
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


    /**
     * Is the slot of a pending booking still free for it? Takes the service row lock first so
     * concurrent settlements and new holds are serialised, and ignores the booking's own hold.
     * Also refuses if the athlete has meanwhile confirmed an overlapping session.
     *
     * @param array<string, mixed> $booking
     */
    private function slotStillAvailable(array $booking, string $bookingReference): bool
    {
        $stmt = $this->db->prepare('SELECT capacity FROM services WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $booking['service_id']]);
        $capacity = (int) $stmt->fetchColumn();
        if ($capacity <= 0) {
            return false;
        }

        $serviceId = (int) $booking['service_id'];
        $date      = (string) $booking['booking_date'];
        $start     = (string) $booking['start_time'];
        $end       = (string) $booking['end_time'];

        $occupied = $this->bookingModel->countActiveOverlapping($serviceId, $date, $start, $end)
                  + $this->holdModel->countActiveOverlapping($serviceId, $date, $start, $end, $bookingReference);
        if ($occupied >= $capacity) {
            return false;
        }

        return !$this->bookingModel->hasCustomerOverlap((int) $booking['user_id'], $date, $start, $end);
    }

    /** Best-effort admin alert that can never break the payment flow. */
    private function alertAdmin(string $subject, string $heading, string $intro, array $rows, string $type): void
    {
        try {
            (new EmailService())->sendAdminAlert($subject, $heading, $intro, $rows, $type);
        } catch (\Throwable $e) {
            error_log('Admin alert failed (' . $type . '): ' . $e->getMessage());
        }
    }
}
