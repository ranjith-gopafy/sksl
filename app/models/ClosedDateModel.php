<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Closed Date Model
 *
 * Checks facility-wide closures that prevent booking on designated dates.
 */
class ClosedDateModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Check if a specific date (Y-m-d) is marked as closed.
     */
    public function isDateClosed(string $date): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM closed_dates WHERE closed_date = ? LIMIT 1'
        );
        $stmt->execute([$date]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get all closed dates ordered by date ascending.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, closed_date, reason, created_at FROM closed_dates ORDER BY closed_date ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Add a closed date.
     */
    public function add(string $date, ?string $reason = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO closed_dates (closed_date, reason) VALUES (?, ?)'
        );
        $stmt->execute([$date, $reason]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Remove a closed date by ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM closed_dates WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }
}
