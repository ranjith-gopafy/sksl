<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Service Model
 *
 * Provides database queries for facility recovery services.
 * All pricing, duration, capacity, and GST rules are authoritatively queried from here.
 */
class ServiceModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Retrieve all active services for customer catalog and booking dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllActive(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, description, price, gst_percent,
                    duration_minutes, capacity, image, status, created_at
             FROM services
             WHERE status = 'active'
             ORDER BY id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Retrieve all services (including inactive) for admin management.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, slug, description, price, gst_percent,
                    duration_minutes, capacity, image, status, created_at, updated_at
             FROM services
             ORDER BY id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a single service by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM services WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a single active service by ID.
     */
    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM services WHERE id = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a single active service by slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM services WHERE slug = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Calculate authoritative price breakdown (base + GST = total).
     *
     * @param float $basePrice
     * @param float $gstPercent  Defaults to 18%
     * @return array{base_price: float, gst_percent: float, gst_amount: float, total_amount: float}
     */
    public static function calculatePricing(float $basePrice, float $gstPercent = 18.0): array
    {
        $gstAmount   = round($basePrice * ($gstPercent / 100.0), 2);
        $totalAmount = round($basePrice + $gstAmount, 2);

        return [
            'base_price'   => $basePrice,
            'gst_percent'  => $gstPercent,
            'gst_amount'   => $gstAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Update active/inactive status of a service.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE services SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Update editable attributes of a service (price, capacity, description, status, image).
     *
     * @param array<string, mixed> $data
     */
    public function updateDetails(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE services 
             SET price = :price, capacity = :capacity, description = :description, status = :status, image = :image
             WHERE id = :id'
        );
        return $stmt->execute([
            'price'       => $data['price'],
            'capacity'    => $data['capacity'],
            'description' => $data['description'],
            'status'      => $data['status'] ?? 'active',
            'image'       => $data['image'] ?? null,
            'id'          => $id,
        ]);
    }
}
