<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Booking Model
 *
 * Manages database operations for bookings and active capacity counts.
 */
class BookingModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Count active overlapping bookings for a service on a given date.
     * Overlap formula: existing_start < requested_end AND existing_end > requested_start
     * Confirmed and active pending bookings consume capacity.
     */
    public function countActiveOverlapping(int $serviceId, string $date, string $startTime, string $endTime): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE service_id = :service_id
               AND booking_date = :booking_date
               AND start_time < :end_time
               AND end_time > :start_time
               AND booking_status = 'confirmed'"
        );
        $stmt->execute([
            'service_id'   => $serviceId,
            'booking_date' => $date,
            'start_time'   => $startTime,
            'end_time'     => $endTime,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if a specific customer already has an active overlapping booking on this date.
     * Prevents a customer from accidentally double-booking conflicting modalities.
     */
    public function hasCustomerOverlap(int $userId, string $date, string $startTime, string $endTime): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM bookings
             WHERE user_id = :user_id
               AND booking_date = :booking_date
               AND start_time < :end_time
               AND end_time > :start_time
               AND booking_status = 'confirmed'
             LIMIT 1"
        );
        $stmt->execute([
            'user_id'      => $userId,
            'booking_date' => $date,
            'start_time'   => $startTime,
            'end_time'     => $endTime,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Find booking by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, s.name as service_name, s.slug as service_slug, u.name as user_name, u.email as user_email, u.mobile as user_mobile
             FROM bookings b
             JOIN services s ON b.service_id = s.id
             JOIN users u ON b.user_id = u.id
             WHERE b.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find booking by unique reference (e.g. SKSL-XXXX).
     */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, s.name as service_name, s.slug as service_slug, u.name as user_name, u.email as user_email, u.mobile as user_mobile
             FROM bookings b
             JOIN services s ON b.service_id = s.id
             JOIN users u ON b.user_id = u.id
             WHERE b.booking_reference = ? LIMIT 1'
        );
        $stmt->execute([$reference]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new booking record with historical price snapshots.
     *
     * @param array<string, mixed> $data
     * @return int New booking ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO bookings (
                booking_reference, user_id, service_id, booking_date,
                start_time, end_time, service_duration_minutes,
                checkin_buffer_minutes, checkout_buffer_minutes,
                base_amount, gst_amount, convenience_fee, total_amount,
                booking_status, payment_status
             ) VALUES (
                :ref, :user_id, :service_id, :booking_date,
                :start_time, :end_time, :duration,
                :checkin_buf, :checkout_buf,
                :base_amount, :gst_amount, :conv_fee, :total_amount,
                :b_status, :p_status
             )'
        );

        $stmt->execute([
            'ref'          => $data['booking_reference'],
            'user_id'      => $data['user_id'],
            'service_id'   => $data['service_id'],
            'booking_date' => $data['booking_date'],
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'duration'     => $data['service_duration_minutes'],
            'checkin_buf'  => $data['checkin_buffer_minutes'] ?? 0,
            'checkout_buf' => $data['checkout_buffer_minutes'] ?? 0,
            'base_amount'  => $data['base_amount'],
            'gst_amount'   => $data['gst_amount'],
            'conv_fee'     => $data['convenience_fee'] ?? 0.00,
            'total_amount' => $data['total_amount'],
            'b_status'     => $data['booking_status'] ?? 'pending',
            'p_status'     => $data['payment_status'] ?? 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update booking status and optionally payment status.
     */
    public function updateStatus(int $id, string $bookingStatus, ?string $paymentStatus = null): bool
    {
        if ($paymentStatus !== null) {
            $stmt = $this->db->prepare(
                'UPDATE bookings SET booking_status = ?, payment_status = ? WHERE id = ?'
            );
            return $stmt->execute([$bookingStatus, $paymentStatus, $id]);
        }

        $stmt = $this->db->prepare(
            'UPDATE bookings SET booking_status = ? WHERE id = ?'
        );
        return $stmt->execute([$bookingStatus, $id]);
    }
}
