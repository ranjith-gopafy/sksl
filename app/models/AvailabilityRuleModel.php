<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Availability Rule Model
 *
 * Manages recurring weekday schedules and specific-date practitioner hours / exceptions.
 */
class AvailabilityRuleModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get all availability rules for a service.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByServiceId(int $serviceId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM availability_rules WHERE service_id = :service_id";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY rule_type ASC, weekday ASC, specific_date ASC, start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['service_id' => $serviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find rule by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM availability_rules WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get applicable rules for a service on a given date.
     * Specific-date rules take priority over recurring weekday rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRulesForDate(int $serviceId, string $date): array
    {
        // 1. Check for specific-date override
        $stmtSpecific = $this->db->prepare(
            "SELECT * FROM availability_rules
             WHERE service_id = ?
               AND rule_type = 'specific_date'
               AND specific_date = ?
               AND status = 'active'
             ORDER BY start_time ASC"
        );
        $stmtSpecific->execute([$serviceId, $date]);
        $specificRules = $stmtSpecific->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (!empty($specificRules)) {
            return $specificRules;
        }

        // 2. Fall back to recurring weekday schedule
        $weekday = (int) date('w', strtotime($date)); // 0=Sunday..6=Saturday
        $stmtRecurring = $this->db->prepare(
            "SELECT * FROM availability_rules
             WHERE service_id = ?
               AND rule_type = 'recurring_weekday'
               AND weekday = ?
               AND status = 'active'
             ORDER BY start_time ASC"
        );
        $stmtRecurring->execute([$serviceId, $weekday]);
        return $stmtRecurring->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Bookable windows for a service on a date, as [start, end] pairs of "HH:MM:SS".
     *
     * - Working hours come from the specific-date "available" rules when the date has any,
     *   otherwise from the recurring weekday schedule.
     * - "Unavailable" rules (specific date, or recurring) are subtracted, so a 10:00–12:00
     *   exception removes those two hours instead of closing the whole day.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function getAvailableWindows(int $serviceId, string $date): array
    {
        $specific  = $this->getRulesForDate($serviceId, $date);
        $weekday   = (int) date('w', strtotime($date));
        $stmt = $this->db->prepare(
            "SELECT * FROM availability_rules
             WHERE service_id = ? AND rule_type = 'recurring_weekday' AND weekday = ? AND status = 'active'
             ORDER BY start_time ASC"
        );
        $stmt->execute([$serviceId, $weekday]);
        $recurring = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // getRulesForDate() returns the specific-date rules when present, else the recurring ones.
        $specificOnly = array_values(array_filter($specific, static fn (array $r) => $r['rule_type'] === 'specific_date'));

        $specificBlocks = array_filter($specificOnly, static fn (array $r) => empty($r['is_available']));
        $workRules = array_filter($specificOnly, static fn (array $r) => !empty($r['is_available']));
        if (!empty($workRules)) {
            // Special hours for this exact date replace the weekly schedule entirely, including any
            // recurring "unavailable" window (e.g. a standing lunch break), so staff can reopen it.
            $blockRules = $specificBlocks;
        } else {
            $workRules  = array_filter($recurring, static fn (array $r) => !empty($r['is_available']));
            $blockRules = array_merge($specificBlocks, array_filter($recurring, static fn (array $r) => empty($r['is_available'])));
        }

        $windows = [];
        foreach ($workRules as $r) {
            $windows[] = [(string) $r['start_time'], (string) $r['end_time']];
        }

        foreach ($blockRules as $b) {
            $bs = (string) $b['start_time'];
            $be = (string) $b['end_time'];
            $next = [];
            foreach ($windows as [$s, $e]) {
                if ($bs >= $e || $be <= $s) {
                    $next[] = [$s, $e];
                    continue;
                }
                if ($bs > $s) {
                    $next[] = [$s, $bs];
                }
                if ($be < $e) {
                    $next[] = [$be, $e];
                }
            }
            $windows = $next;
        }

        usort($windows, static fn (array $a, array $b) => strcmp($a[0], $b[0]));
        return $windows;
    }

    /**
     * Check if a service has ANY configured availability rules.
     */
    public function hasRules(int $serviceId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM availability_rules WHERE service_id = ? AND status = 'active' AND is_available = 1 LIMIT 1"
        );
        $stmt->execute([$serviceId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Create a new availability rule.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO availability_rules (
                service_id, rule_type, weekday, specific_date,
                start_time, end_time, is_available, notes, status
             ) VALUES (
                :service_id, :rule_type, :weekday, :specific_date,
                :start_time, :end_time, :is_available, :notes, :status
             )"
        );
        $stmt->execute([
            'service_id'    => (int) $data['service_id'],
            'rule_type'     => $data['rule_type'] ?? 'recurring_weekday',
            'weekday'       => isset($data['weekday']) ? (int) $data['weekday'] : null,
            'specific_date' => $data['specific_date'] ?? null,
            'start_time'    => $data['start_time'],
            'end_time'      => $data['end_time'],
            'is_available'  => isset($data['is_available']) ? (int) $data['is_available'] : 1,
            'notes'         => $data['notes'] ?? null,
            'status'        => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an availability rule.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['rule_type', 'weekday', 'specific_date', 'start_time', 'end_time', 'is_available', 'notes', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE availability_rules SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE availability_rules
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete an availability rule.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM availability_rules WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
