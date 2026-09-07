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
 * Implements server-side Razorpay order creation, payment signature verification,
 * idempotent status transitions, and webhook reconciliation.
 */
class PaymentService
{
    private \PDO $db;
    private PaymentModel $paymentModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;
    private ServiceModel $serviceModel;
    private UserModel $userModel;

    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;

    public function __construct(
        ?\PDO $db = null,
        ?PaymentModel $paymentModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null,
        ?ServiceModel $serviceModel = null,
        ?UserModel $userModel = null
    ) {
        $this->db            = $db ?? getDb();
        $this->paymentModel  = $paymentModel ?? new PaymentModel();
        $this->bookingModel  = $bookingModel ?? new BookingModel();
        $this->holdModel     = $holdModel ?? new BookingHoldModel();
        $this->serviceModel  = $serviceModel ?? new ServiceModel();
        $this->userModel     = $userModel ?? new UserModel();

        $this->keyId         = trim((string) ($_ENV['RAZORPAY_KEY_ID'] ?? ''));
        $this->keySecret     = trim((string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? ''));
        $this->webhookSecret = trim((string) ($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? ''));
    }

    /**
     * Create Razorpay order for an active booking hold.
     * Amount is authoritatively derived from server-calculated totals in integer paise.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function createOrder(int $userId, string $bookingReference): array
    {
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
        if (!$booking) {
            $service = $this->serviceModel->findById((int) $hold['service_id']);
            if (!$service) {
                return ['success' => false, 'message' => 'Service not found.'];
            }

            $pricing = ServiceModel::calculatePricing(
                (float) $service['price'],
                (float) ($service['gst_percent'] ?? 18.0)
            );

            $bookingId = $this->bookingModel->create([
                'booking_reference'        => $bookingReference,
                'user_id'                  => $userId,
                'service_id'               => (int) $hold['service_id'],
                'booking_date'             => $hold['booking_date'],
                'start_time'               => $hold['start_time'],
                'end_time'                 => $hold['end_time'],
                'service_duration_minutes' => (int) $service['duration_minutes'],
                'base_amount'              => $pricing['base_price'],
                'gst_amount'               => $pricing['gst_amount'],
                'convenience_fee'          => 0.00,
                'total_amount'             => $pricing['total_amount'],
                'booking_status'           => 'pending',
                'payment_status'           => 'pending',
            ]);

            $booking = $this->bookingModel->findById($bookingId);
        }

        // 3. Idempotent check: has a pending payment order already been issued?
        $existingPayment = $this->paymentModel->findByBookingId((int) $booking['id']);
        if ($existingPayment && $existingPayment['status'] === 'pending') {
            $orderId = $existingPayment['razorpay_order_id'];
        } else {
            // Amount in integer paise (₹529.82 -> 52982 paise)
            $totalRupees = (float) $booking['total_amount'];
            $amountPaise = (int) round($totalRupees * 100);

            if ($this->keyId !== '' && $this->keySecret !== '') {
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
                // Local Dev / Mock Mode when Razorpay keys are not yet configured in .env
                $orderId = 'order_mock_' . bin2hex(random_bytes(8));
            }

            // Save order into payments table
            $this->paymentModel->create([
                'booking_id'        => (int) $booking['id'],
                'razorpay_order_id' => $orderId,
                'amount'            => (float) $booking['total_amount'],
                'currency'          => 'INR',
                'status'            => 'pending',
            ]);
        }

        $user = $this->userModel->findById($userId);

        return [
            'success' => true,
            'message' => 'Razorpay order created.',
            'data'    => [
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
                'is_mock'           => ($this->keyId === ''),
            ],
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

        // Idempotency: If already verified, return success safely
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

        // Signature Verification
        if ($this->keySecret !== '') {
            $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
            if (!hash_equals($expectedSignature, $signature)) {
                $this->paymentModel->markFailed((int) $payment['id'], 'Signature mismatch');
                return [
                    'success' => false,
                    'message' => 'Payment signature verification failed. Please contact support.',
                ];
            }
        }

        // Atomic confirmation inside database transaction
        $this->db->beginTransaction();

        try {
            $this->paymentModel->markPaid(
                (int) $payment['id'],
                $paymentId,
                $signature,
                json_encode(['verified_at' => date('Y-m-d H:i:s')])
            );

            $this->bookingModel->updateStatus((int) $booking['id'], 'confirmed', 'paid');
            $this->holdModel->release($bookingReference);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Payment confirmation transaction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error during confirmation. Please contact support with Payment ID: ' . $paymentId,
            ];
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
     * Process Razorpay Webhook event idempotently.
     */
    public function handleWebhook(string $rawBody, string $signatureHeader): array
    {
        if ($this->webhookSecret !== '') {
            $expectedSignature = hash_hmac('sha256', $rawBody, $this->webhookSecret);
            if (!hash_equals($expectedSignature, $signatureHeader)) {
                return ['success' => false, 'message' => 'Invalid webhook signature.', 'code' => 400];
            }
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return ['success' => false, 'message' => 'Invalid JSON payload.', 'code' => 400];
        }

        $eventType = (string) ($event['event'] ?? '');

        if ($eventType === 'payment.captured' || $eventType === 'order.paid') {
            $paymentEntity = $event['payload']['payment']['entity'] ?? null;
            if ($paymentEntity) {
                $orderId   = (string) ($paymentEntity['order_id'] ?? '');
                $paymentId = (string) ($paymentEntity['id'] ?? '');

                if ($orderId !== '' && $paymentId !== '') {
                    $payment = $this->paymentModel->findByOrderId($orderId);
                    if ($payment && $payment['status'] !== 'paid') {
                        $this->db->beginTransaction();
                        try {
                            $this->paymentModel->markPaid(
                                (int) $payment['id'],
                                $paymentId,
                                'webhook_signature_verified',
                                json_encode(['webhook_event' => $eventType])
                            );
                            $this->bookingModel->updateStatus((int) $payment['booking_id'], 'confirmed', 'paid');
                            $this->db->commit();
                        } catch (\Throwable $e) {
                            if ($this->db->inTransaction()) {
                                $this->db->rollBack();
                            }
                            error_log('Webhook reconciliation exception: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        return ['success' => true, 'message' => 'Webhook received and processed.', 'code' => 200];
    }
}
