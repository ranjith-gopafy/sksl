<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manual Block Model
 *
 * Manages administrative specific-date and recurring maintenance blocks.
 */
class ManualBlockModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get all manual blocks with service details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT b.*, s.name as service_name, s.slug as service_slug
                FROM manual_blocks b
                LEFT JOIN services s ON b.service_id = s.id";
        if ($activeOnly) {
            $sql .= " WHERE b.status = 'active'";
        }
        $sql .= " ORDER BY b.specific_date DESC, b.weekday ASC, b.start_time ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find block by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, s.name as service_name
             FROM manual_blocks b
             LEFT JOIN services s ON b.service_id = s.id
             WHERE b.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all active blocks affecting a service (or facility-wide) on a specific date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBlocksForDate(?int $serviceId, string $date): array
    {
        $weekday = (int) date('w', strtotime($date));

        $sql = "SELECT * FROM manual_blocks
                WHERE status = 'active'
                  AND (service_id = :service_id OR service_id IS NULL)
                  AND (
                      (block_type = 'specific_date' AND specific_date = :date)
                      OR
                      (block_type = 'recurring_weekday' AND weekday = :weekday)
                  )
                ORDER BY start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'service_id' => $serviceId,
            'date'       => $date,
            'weekday'    => $weekday,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if a time interval overlaps any active manual block on a date.
     */
    public function isRangeBlocked(?int $serviceId, string $date, string $startTime, string $endTime): bool
    {
        $blocks = $this->getBlocksForDate($serviceId, $date);
        foreach ($blocks as $b) {
            // Overlap condition: b.start < requested_end AND b.end > requested_start
            if ($b['start_time'] < $endTime && $b['end_time'] > $startTime) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a new manual block.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO manual_blocks (
                service_id, block_type, specific_date, weekday,
                start_time, end_time, reason, status, created_by_admin_id
             ) VALUES (
                :service_id, :block_type, :specific_date, :weekday,
                :start_time, :end_time, :reason, :status, :created_by_admin_id
             )"
        );
        $stmt->execute([
            'service_id'          => !empty($data['service_id']) ? (int) $data['service_id'] : null,
            'block_type'          => $data['block_type'] ?? 'specific_date',
            'specific_date'       => $data['specific_date'] ?? null,
            'weekday'             => isset($data['weekday']) ? (int) $data['weekday'] : null,
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
            'reason'              => !empty($data['reason']) ? trim((string) $data['reason']) : 'Maintenance / Administrative Block',
            'status'              => $data['status'] ?? 'active',
            'created_by_admin_id' => !empty($data['created_by_admin_id']) ? (int) $data['created_by_admin_id'] : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing manual block.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['service_id', 'block_type', 'specific_date', 'weekday', 'start_time', 'end_time', 'reason', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE manual_blocks SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE manual_blocks
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete a manual block.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM manual_blocks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
