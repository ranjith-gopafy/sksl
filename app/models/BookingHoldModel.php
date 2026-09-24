<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Booking Hold Model
 *
 * Manages temporary 10-minute holds while customer completes payment.
 * Holds count toward capacity calculation only while active and non-expired.
 */
class BookingHoldModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Count active, non-expired holds overlapping a slot interval.
     * Overlap formula: existing_start < requested_end AND existing_end > requested_start
     */
    public function countActiveOverlapping(int $serviceId, string $date, string $startTime, string $endTime): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM booking_holds
             WHERE service_id = :service_id
               AND booking_date = :booking_date
               AND start_time < :end_time
               AND end_time > :start_time
               AND status = 'active'
               AND expires_at > NOW()"
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
     * Check if customer already has an active, non-expired hold overlapping this slot.
     * When serviceId is provided, checks for conflicting holds on that specific modality.
     */
    public function hasCustomerActiveHold(int $userId, string $date, string $startTime, string $endTime, ?int $serviceId = null): bool
    {
        $sql = "SELECT 1 FROM booking_holds
              WHERE user_id = :user_id
                AND booking_date = :booking_date
                AND start_time < :end_time
                AND end_time > :start_time
                AND status = 'active'
                AND expires_at > NOW()";

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
     * Create a new temporary hold.
     *
     * @param array<string, mixed> $data
     * @return int New hold ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO booking_holds (
                booking_reference, user_id, service_id, booking_date,
                start_time, end_time, expires_at, status
             ) VALUES (
                :ref, :user_id, :service_id, :booking_date,
                :start_time, :end_time, :expires_at, 'active'
             )"
        );
        $stmt->execute([
            'ref'          => $data['booking_reference'],
            'user_id'      => $data['user_id'],
            'service_id'   => $data['service_id'],
            'booking_date' => $data['booking_date'],
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'expires_at'   => $data['expires_at'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Find active, non-expired hold by booking reference.
     */
    public function findActiveByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM booking_holds
             WHERE booking_reference = ?
               AND status = 'active'
               AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$reference]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Release an active hold (e.g. customer cancels checkout or payment failed).
     */
    public function release(string $reference): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE booking_holds SET status = 'released' WHERE booking_reference = ?"
        );
        return $stmt->execute([$reference]);
    }

    /**
     * Mark expired holds as 'expired'. Called by cron or background tasks.
     * Availability logic independently ignores expired holds even before this runs.
     */
    public function expireStale(): int
    {
        $stmt = $this->db->query(
            "UPDATE booking_holds
             SET status = 'expired'
             WHERE status = 'active'
               AND expires_at <= NOW()"
        );
        return $stmt->rowCount();
    }
}
