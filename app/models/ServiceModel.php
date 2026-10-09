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
     * Defaults to recovery modalities to preserve Phase 1 catalog structure.
     * Pass 'professional' for practitioner services, or null for all active services.
     *
     * @param string|null $category 'modality' | 'professional' | null
     * @return array<int, array<string, mixed>>
     */
    public function getAllActive(?string $category = 'modality'): array
    {
        $sql = "SELECT id, name, slug, category, description, price, pricing_type, gst_percent,
                       duration_minutes, duration_mode, capacity, image, status, created_at
                FROM services
                WHERE status = 'active'";
        $params = [];
        if ($category !== null && $category !== 'all') {
            $sql .= " AND category = :category";
            $params['category'] = $category;
        }
        $sql .= " ORDER BY id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Retrieve active professional services (Physiotherapy, Sports Psychology).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProfessionalServices(): array
    {
        return $this->getAllActive('professional');
    }

    /**
     * Retrieve active recovery modalities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModalities(): array
    {
        return $this->getAllActive('modality');
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
     * Split a GST-INCLUSIVE catalogue price into its tax breakdown.
     *
     * Every price in the catalogue (services, duration options, packages) is what the
     * customer pays, GST included. The total is therefore always exactly the listed
     * price; the taxable base and the GST are derived from it, never the other way round
     * (₹399 ÷ 1.18 is not a whole number of paise, so deriving the total would drift).
     *
     * @param float $inclusivePrice  Catalogue price, GST included
     * @param float $gstPercent      Defaults to 18%
     * @return array{base_price: float, gst_percent: float, gst_amount: float, total_amount: float}
     */
    public static function calculatePricing(float $inclusivePrice, float $gstPercent = 18.0): array
    {
        $totalAmount = round($inclusivePrice, 2);
        $rawGst      = $totalAmount - ($totalAmount / (1 + ($gstPercent / 100.0)));
        // GST is split into two equal halves (CGST + SGST), so it is always an even number of paise;
        // the taxable value absorbs the rounding and base + GST still equals the listed price exactly.
        $halfGst     = round($rawGst / 2, 2);
        $gstAmount   = round($halfGst * 2, 2);
        $basePrice   = round($totalAmount - $gstAmount, 2);

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
