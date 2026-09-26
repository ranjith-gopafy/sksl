<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BookingRules;
use App\Helpers\TimeHelper;

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
     * When serviceId is supplied, checks for conflicting bookings on that specific modality.
     */
    public function hasCustomerOverlap(int $userId, string $date, string $startTime, string $endTime, ?int $serviceId = null): bool
    {
        $sql = "SELECT 1 FROM bookings
             WHERE user_id = :user_id
               AND booking_date = :booking_date
               AND start_time < :end_time
               AND end_time > :start_time
               AND booking_status = 'confirmed'";

        $params = [
            'user_id'      => $userId,
            'booking_date' => $date,
            'start_time'   => $startTime,
            'end_time'     => $endTime,
        ];

        if ($serviceId !== null) {
            $sql .= " AND service_id = :service_id";
            $params['service_id'] = $serviceId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
        // service_name_snapshot is required: if the caller did not supply it, read it now
        // so historical bookings never depend on the live services row.
        $serviceName = trim((string) ($data['service_name_snapshot'] ?? ''));
        if ($serviceName === '') {
            $lookup = $this->db->prepare('SELECT name FROM services WHERE id = ? LIMIT 1');
            $lookup->execute([(int) $data['service_id']]);
            $serviceName = (string) ($lookup->fetchColumn() ?: 'Recovery Session');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO bookings (
                booking_reference, user_id, service_id, booking_date,
                start_time, end_time,
                service_name_snapshot, service_duration_minutes,
                checkin_buffer_minutes, checkout_buffer_minutes,
                base_amount, gst_percent, gst_amount, convenience_fee, total_amount,
                booking_status, payment_status
             ) VALUES (
                :ref, :user_id, :service_id, :booking_date,
                :start_time, :end_time,
                :service_name, :duration,
                :checkin_buf, :checkout_buf,
                :base_amount, :gst_percent, :gst_amount, :conv_fee, :total_amount,
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
            'service_name' => mb_substr($serviceName, 0, 150),
            'duration'     => $data['service_duration_minutes'],
            'checkin_buf'  => $data['checkin_buffer_minutes'] ?? 0,
            'checkout_buf' => $data['checkout_buffer_minutes'] ?? 0,
            'base_amount'  => $data['base_amount'],
            'gst_percent'  => $data['gst_percent'] ?? 18.00,
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
     * Atomically move a booking from pending -> confirmed/paid.
     * Returns true only if THIS call performed the transition. A cancelled,
     * completed, or already-confirmed booking is left untouched and returns false.
     */
    public function confirmIfPending(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings
             SET booking_status = 'confirmed', payment_status = 'paid'
             WHERE id = ? AND booking_status = 'pending'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Record that money was captured for a booking that had already been
     * cancelled/expired. Payment is marked paid so it shows in reconciliation,
     * and a note is appended for staff to arrange a refund.
     */
    public function markPaymentReceivedAfterClose(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings
             SET payment_status = 'paid',
                 notes = CONCAT_WS('\n', notes, ?)
             WHERE id = ? AND booking_status NOT IN ('confirmed', 'completed')"
        );
        return $stmt->execute([
            '[' . date('Y-m-d H:i:s') . '] Payment captured after booking was closed. Refund required.',
            $id,
        ]);
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

        // "Now" is taken from the application clock (Asia/Kolkata), not the DB
        // server's, so a session that ended an hour ago is no longer "upcoming"
        // even when the database runs in UTC.
        $today   = TimeHelper::today();
        $nowTime = TimeHelper::now()->format('H:i:s');

        if ($filter === 'upcoming') {
            $sql .= " AND b.booking_status = 'confirmed'
                      AND (b.booking_date > :today OR (b.booking_date = :today2 AND b.end_time > :now_time))";
            $params += ['today' => $today, 'today2' => $today, 'now_time' => $nowTime];
        } elseif ($filter === 'completed') {
            $sql .= " AND (b.booking_status = 'completed'
                      OR (b.booking_status = 'confirmed'
                          AND (b.booking_date < :today OR (b.booking_date = :today2 AND b.end_time <= :now_time))))";
            $params += ['today' => $today, 'today2' => $today, 'now_time' => $nowTime];
        } elseif ($filter === 'cancelled') {
            $sql .= " AND b.booking_status = 'cancelled'";
        } else {
            // 'all' / default: real bookings only — never abandoned (pending/expired) checkouts
            $sql .= " AND b.booking_status NOT IN ('pending', 'expired')";
        }

        $sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Allowed staff-driven status transitions.
     *
     * Customers cannot cancel online; SKSL staff cancel on request (see
     * cancellation policy). Cancelled and completed are terminal: a cancelled
     * booking is never resurrected because its slot may have been re-sold and
     * its payment may already be refunded — a new booking must be made instead.
     * pending -> confirmed is only performed by the payment flow (confirmIfPending).
     */
    public const ADMIN_TRANSITIONS = [
        'pending'   => ['cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'expired'   => [], // abandoned checkout, closed automatically — terminal
    ];

    /**
     * Expire abandoned checkouts: bookings still pending/unpaid after the hold
     * window plus a grace period become 'expired' so they stop cluttering the
     * admin list. A late webhook for such a row is still recorded by
     * settlePayment() (markPaymentReceivedAfterClose) and flagged for staff.
     *
     * @return int rows expired
     */
    public function expireStalePending(?int $olderThanMinutes = null): int
    {
        $minutes = $olderThanMinutes ?? BookingRules::pendingBookingExpiryMinutes();
        $cutoff  = TimeHelper::now()->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "UPDATE bookings
             SET booking_status = 'expired'
             WHERE booking_status = 'pending'
               AND payment_status = 'pending'
               AND created_at < ?"
        );
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    /**
     * Validate a staff-requested status change against the state machine.
     *
     * @param array<string, mixed> $booking
     * @return array{allowed: bool, reason?: string}
     */
    public static function checkAdminTransition(array $booking, string $to): array
    {
        $from = (string) ($booking['booking_status'] ?? '');

        if (!isset(self::ADMIN_TRANSITIONS[$from])) {
            return ['allowed' => false, 'reason' => 'Booking is in an unknown state.'];
        }
        if ($from === $to) {
            return ['allowed' => false, 'reason' => 'Booking is already ' . $to . '.'];
        }
        if ($from === 'cancelled') {
            return ['allowed' => false, 'reason' => 'A cancelled booking cannot be reopened. Ask the athlete to book a new session.'];
        }
        if ($from === 'completed') {
            return ['allowed' => false, 'reason' => 'A completed booking is final.'];
        }
        if ($from === 'expired') {
            return ['allowed' => false, 'reason' => 'This checkout expired without payment. Ask the athlete to book again.'];
        }
        if ($to === 'confirmed') {
            return ['allowed' => false, 'reason' => 'Bookings are confirmed automatically once payment is verified; they cannot be confirmed manually.'];
        }
        if ($to === 'completed' && ($booking['payment_status'] ?? '') !== 'paid') {
            return ['allowed' => false, 'reason' => 'Only paid bookings can be marked completed.'];
        }
        if (!in_array($to, self::ADMIN_TRANSITIONS[$from], true)) {
            return ['allowed' => false, 'reason' => "Cannot move a {$from} booking to {$to}."];
        }

        return ['allowed' => true];
    }

    /**
     * Atomically move a booking from one status to another. Returns true only
     * if the row was still in $from when the update ran, so two staff members
     * acting at once cannot both "win".
     */
    public function transitionStatus(int $id, string $from, string $to): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE bookings SET booking_status = ? WHERE id = ? AND booking_status = ?'
        );
        $stmt->execute([$to, $id, $from]);
        return $stmt->rowCount() === 1;
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
             'expired'   => 0,
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
