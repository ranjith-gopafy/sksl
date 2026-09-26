<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Payment Model
 *
 * Manages database records for payments linked to bookings.
 * Enforces unique order and payment IDs and idempotent status updates.
 */
class PaymentModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Create a new pending payment record.
     *
     * @param array<string, mixed> $data
     * @return int New payment record ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (
                booking_id, razorpay_order_id, amount, currency, status
             ) VALUES (
                :booking_id, :order_id, :amount, :currency, :status
             )'
        );
        $stmt->execute([
            'booking_id' => $data['booking_id'],
            'order_id'   => $data['razorpay_order_id'],
            'amount'     => $data['amount'],
            'currency'   => $data['currency'] ?? 'INR',
            'status'     => $data['status'] ?? 'pending',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Find payment record by Razorpay order ID.
     */
    public function findByOrderId(string $orderId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payments WHERE razorpay_order_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find payment record by internal booking ID.
     */
    public function findByBookingId(int $bookingId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find the settled (paid) payment for a booking, if any.
     */
    public function findPaidByBookingId(int $bookingId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM payments WHERE booking_id = ? AND status = 'paid' ORDER BY paid_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark payment as paid. Idempotent: a row that is already paid is never
     * overwritten, so a late duplicate callback cannot replace the payment id.
     */
    public function markPaid(int $id, string $paymentId, string $signature, ?string $gatewayResponse = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE payments
             SET razorpay_payment_id = ?,
                 razorpay_signature  = ?,
                 status              = 'paid',
                 paid_at             = NOW(),
                 gateway_response    = ?
             WHERE id = ? AND status != 'paid'"
        );
        return $stmt->execute([$paymentId, $signature, $gatewayResponse, $id]);
    }

    /**
     * Mark payment as failed.
     */
    public function markFailed(int $id, ?string $gatewayResponse = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE payments
             SET status           = 'failed',
                 gateway_response = ?
             WHERE id = ? AND status != 'paid'"
        );
        return $stmt->execute([$gatewayResponse, $id]);
    }
}
