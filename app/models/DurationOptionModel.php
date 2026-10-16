<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Duration Option Model
 *
 * Manages configurable duration and pricing options per service modality.
 */
class DurationOptionModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get all duration options for a service.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByServiceId(int $serviceId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM service_duration_options WHERE service_id = :service_id";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY sort_order ASC, duration_minutes ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['service_id' => $serviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a duration option by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM service_duration_options WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find option by service ID and duration minutes.
     */
    public function findByServiceAndDuration(int $serviceId, int $durationMinutes): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM service_duration_options WHERE service_id = ? AND duration_minutes = ? LIMIT 1"
        );
        $stmt->execute([$serviceId, $durationMinutes]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new duration option.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO service_duration_options (
                service_id, duration_minutes, price, label, sort_order, status
             ) VALUES (
                :service_id, :duration_minutes, :price, :label, :sort_order, :status
             )"
        );
        $stmt->execute([
            'service_id'       => (int) $data['service_id'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'price'            => (float) ($data['price'] ?? 0.0),
            'label'            => $data['label'] ?? null,
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
            'status'           => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing duration option.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['duration_minutes'])) {
            $fields[] = 'duration_minutes = :duration_minutes';
            $params['duration_minutes'] = (int) $data['duration_minutes'];
        }
        if (isset($data['price'])) {
            $fields[] = 'price = :price';
            $params['price'] = (float) $data['price'];
        }
        if (array_key_exists('label', $data)) {
            $fields[] = 'label = :label';
            $params['label'] = $data['label'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = :sort_order';
            $params['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE service_duration_options SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE service_duration_options
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete a duration option.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM service_duration_options WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * True when bookings or live holds point at this option, so it must be
     * deactivated rather than deleted (history keeps its meaning).
     */
    public function isReferenced(int $id): bool
    {
        $stmt = $this->db->prepare(
            "SELECT (SELECT COUNT(*) FROM bookings WHERE duration_option_id = :id1)
                  + (SELECT COUNT(*) FROM booking_holds WHERE duration_option_id = :id2)"
        );
        $stmt->execute(['id1' => $id, 'id2' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
