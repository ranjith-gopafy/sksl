<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Package Model
 *
 * Manages combo packages, credit allocations, and package definitions.
 */
class PackageModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get all packages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM packages";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY price ASC, id ASC";

        $stmt = $this->db->query($sql);
        $packages = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($packages as &$pkg) {
            $pkg['items'] = $this->getItems((int) $pkg['id']);
        }
        return $packages;
    }

    /**
     * Find package by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($pkg) {
            $pkg['items'] = $this->getItems((int) $pkg['id']);
        }
        return $pkg;
    }

    /**
     * Find package by slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($pkg) {
            $pkg['items'] = $this->getItems((int) $pkg['id']);
        }
        return $pkg;
    }

    /**
     * Get items (services + quantities) for a package.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItems(int $packageId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pi.id, pi.package_id, pi.service_id, pi.quantity, s.name as service_name, s.slug as service_slug,
                    COALESCE(pi.duration_minutes, s.duration_minutes) AS duration_minutes, s.image
             FROM package_items pi
             JOIN services s ON pi.service_id = s.id
             WHERE pi.package_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->execute([$packageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a package with items.
     *
     * @param array<string, mixed> $data
     * @param array<int, array{service_id: int, quantity: int}> $items
     */
    public function create(array $data, array $items = []): int
    {
        $this->db->beginTransaction();
        try {
            // price is the GST-inclusive amount the customer pays; total_price mirrors it.
            $price = (float) ($data['price'] ?? 0.0);
            $gstPercent = (float) ($data['gst_percent'] ?? 18.0);
            $totalPrice = $price;

            $stmt = $this->db->prepare(
                "INSERT INTO packages (
                    name, slug, description, price, gst_percent, total_price,
                    validity_days, badge_text, status
                 ) VALUES (
                    :name, :slug, :description, :price, :gst_percent, :total_price,
                    :validity_days, :badge_text, :status
                 )"
            );
            $stmt->execute([
                'name'          => trim((string) $data['name']),
                'slug'          => trim((string) $data['slug']),
                'description'   => $data['description'] ?? null,
                'price'         => $price,
                'gst_percent'   => $gstPercent,
                'total_price'   => $totalPrice,
                'validity_days' => (int) ($data['validity_days'] ?? 30),
                'badge_text'    => $data['badge_text'] ?? null,
                'status'        => $data['status'] ?? 'active',
            ]);
            $packageId = (int) $this->db->lastInsertId();

            if (!empty($items)) {
                $itemStmt = $this->db->prepare(
                    "INSERT INTO package_items (package_id, service_id, quantity, duration_minutes)
                     VALUES (?, ?, ?, ?)"
                );
                foreach ($items as $item) {
                    if ((int) $item['quantity'] > 0) {
                        $itemStmt->execute([$packageId, (int) $item['service_id'], (int) $item['quantity'], !empty($item['duration_minutes']) ? (int) $item['duration_minutes'] : null]);
                    }
                }
            }

            $this->db->commit();
            return $packageId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update package details.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'slug', 'description', 'validity_days', 'badge_text', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (isset($data['price']) || isset($data['gst_percent'])) {
            $price = isset($data['price']) ? (float) $data['price'] : null;
            $gst = isset($data['gst_percent']) ? (float) $data['gst_percent'] : null;

            if ($price !== null) {
                $fields[] = "price = :price";
                $params['price'] = $price;
            }
            if ($gst !== null) {
                $fields[] = "gst_percent = :gst_percent";
                $params['gst_percent'] = $gst;
            }

            // Recalculate total_price
            $existing = $this->findById($id);
            $finalPrice = $price ?? (float) ($existing['price'] ?? 0);
            $finalGst = $gst ?? (float) ($existing['gst_percent'] ?? 18);
            $fields[] = "total_price = :total_price";
            $params['total_price'] = $finalPrice; // price is GST-inclusive
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE packages SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Replace items for a package.
     *
     * @param array<int, array{service_id: int, quantity: int}> $items
     */
    public function setItems(int $packageId, array $items): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM package_items WHERE package_id = ?")->execute([$packageId]);

            $stmt = $this->db->prepare(
                "INSERT INTO package_items (package_id, service_id, quantity) VALUES (?, ?, ?)"
            );
            foreach ($items as $item) {
                if ((int) $item['quantity'] > 0) {
                    $stmt->execute([$packageId, (int) $item['service_id'], (int) $item['quantity']]);
                }
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE packages
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete a package.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM packages WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
