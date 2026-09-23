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

    /**
     * Retrieve all bookings for a user with service and payment details.
     *
     * @param int $userId
     * @param string|null $filter 'upcoming' | 'completed' | 'cancelled' | null
     * @return array<int, array<string, mixed>>
     */
    public function findByUser(int $userId, ?string $filter = null): array
    {
        $sql = "SELECT b.*, s.name as service_name, s.slug as service_slug,
                       p.razorpay_payment_id, p.status as payment_record_status
                FROM bookings b
                JOIN services s ON b.service_id = s.id
                LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'paid'
                WHERE b.user_id = :user_id";

        $params = ['user_id' => $userId];

        if ($filter === 'upcoming') {
            $sql .= " AND b.booking_status = 'confirmed' 
                      AND (CONCAT(b.booking_date, ' ', b.end_time) >= NOW() OR b.booking_date >= CURDATE())";
        } elseif ($filter === 'completed') {
            $sql .= " AND (b.booking_status = 'completed' OR (b.booking_status = 'confirmed' AND CONCAT(b.booking_date, ' ', b.end_time) < NOW() AND b.booking_date < CURDATE()))";
        } elseif ($filter === 'cancelled') {
            $sql .= " AND b.booking_status = 'cancelled'";
        } else {
            // For 'all' or default: return real bookings (confirmed, completed, cancelled), excluding abandoned pending holds
            $sql .= " AND b.booking_status != 'pending'";
        }

        $sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Check if a booking is eligible for cancellation by the customer.
     * Policy: Confirmed booking, owned by customer, and at least 2 hours before session start time.
     *
     * @param array<string, mixed> $booking
     * @return array{can_cancel: bool, reason?: string, hours_remaining?: float}
     */
    public static function checkCancellationEligibility(array $booking, int $userId): array
    {
        if ((int) $booking['user_id'] !== $userId) {
            return ['can_cancel' => false, 'reason' => 'Unauthorized booking access.'];
        }

        if ($booking['booking_status'] !== 'confirmed') {
            return ['can_cancel' => false, 'reason' => 'Only confirmed bookings can be cancelled.'];
        }

        $sessionStart = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
        $now = time();

        if ($sessionStart === false || $sessionStart <= $now) {
            return ['can_cancel' => false, 'reason' => 'Past sessions cannot be cancelled.'];
        }

        $secondsRemaining = $sessionStart - $now;
        $hoursRemaining = round($secondsRemaining / 3600, 1);

        if ($secondsRemaining < 7200) { // 2 hours = 7200 seconds
            return [
                'can_cancel' => false,
                'reason' => 'Cancellations must be made at least 2 hours before the session start time. (Currently ' . $hoursRemaining . ' hrs remaining).',
                'hours_remaining' => $hoursRemaining,
            ];
        }

        return [
            'can_cancel' => true,
            'hours_remaining' => $hoursRemaining,
        ];
    }

    /**
     * Count bookings grouped by status matching search and date filters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
     public function getAdminStatusCounts(array $filters = []): array
     {
         $sql = "SELECT b.booking_status, COUNT(*) as cnt
                 FROM bookings b
                 JOIN services s ON b.service_id = s.id
                 JOIN users u ON b.user_id = u.id
                 WHERE 1=1";

         $params = [];

         if (!empty($filters['date'])) {
             $sql .= " AND b.booking_date = :date";
             $params['date'] = $filters['date'];
         }

         if (!empty($filters['service_id'])) {
             $sql .= " AND b.service_id = :service_id";
             $params['service_id'] = (int) $filters['service_id'];
         }

         if (!empty($filters['search'])) {
             $sql .= " AND (b.booking_reference LIKE :s1 OR u.name LIKE :s2 OR u.email LIKE :s3 OR u.mobile LIKE :s4)";
             $term = '%' . $filters['search'] . '%';
             $params['s1'] = $term;
             $params['s2'] = $term;
             $params['s3'] = $term;
             $params['s4'] = $term;
         }

         $sql .= " GROUP BY b.booking_status";

         $stmt = $this->db->prepare($sql);
         $stmt->execute($params);
         $rows = $stmt->fetchAll() ?: [];

         $counts = [
             'all'       => 0,
             'confirmed' => 0,
             'completed' => 0,
             'cancelled' => 0,
             'pending'   => 0,
         ];

         foreach ($rows as $r) {
             $st = (string) $r['booking_status'];
             $c = (int) $r['cnt'];
             if (isset($counts[$st])) {
                 $counts[$st] = $c;
             }
             $counts['all'] += $c;
         }

         return $counts;
     }

    /**
     * Search and retrieve bookings for admin management.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAdminBookings(array $filters = []): array
    {
        $sql = "SELECT b.*, s.name as service_name, s.slug as service_slug,
                       u.name as user_name, u.email as user_email, u.mobile as user_mobile,
                       p.razorpay_payment_id, p.razorpay_order_id, p.status as payment_record_status
                FROM bookings b
                JOIN services s ON b.service_id = s.id
                JOIN users u ON b.user_id = u.id
                LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'paid'
                WHERE 1=1";

        $params = [];

        if (!empty($filters['date'])) {
            $sql .= " AND b.booking_date = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND b.service_id = :service_id";
            $params['service_id'] = (int) $filters['service_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND b.booking_status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (b.booking_reference LIKE :s1 OR u.name LIKE :s2 OR u.email LIKE :s3 OR u.mobile LIKE :s4)";
            $term = '%' . $filters['search'] . '%';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $sql .= " ORDER BY b.booking_date DESC, b.start_time DESC LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
