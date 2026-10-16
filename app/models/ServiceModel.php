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
        $sql = "SELECT id, name, slug, category, category_id, description, price, pricing_type, gst_percent,
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
            "SELECT s.id, s.name, s.slug, s.category, s.category_id, c.name AS category_name,
                    s.description, s.price, s.pricing_type, s.gst_percent,
                    s.duration_minutes, s.capacity, s.image, s.status, s.created_at, s.updated_at
             FROM services s
             LEFT JOIN service_categories c ON c.id = s.category_id
             ORDER BY s.id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /** Is there already a service with this name (any status)? Names are compared case-insensitively. */
    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM services WHERE name = ? AND id <> ? LIMIT 1');
        $stmt->execute([$name, $exceptId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    /** A unique URL key built from the name ("Cryo Chamber" -> "cryo-chamber", "cryo-chamber-2", ...). */
    public function uniqueSlug(string $name): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $base = substr($base !== '' ? $base : 'modality', 0, 120);
        $slug = $base;
        $stmt = $this->db->prepare('SELECT 1 FROM services WHERE slug = ? LIMIT 1');
        for ($n = 2; ; $n++) {
            $stmt->execute([$slug]);
            if (!$stmt->fetchColumn()) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
    }

    /**
     * Add a recovery modality. Prices are GST-inclusive like every other price; GST comes from the
     * column default (18%) unless given. Returns the new id.
     *
     * @param array<string, mixed> $data name, category_id, description, price, capacity, duration_minutes, status, image
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO services
                (name, slug, category, category_id, description, price, pricing_type, gst_percent,
                 duration_minutes, duration_mode, capacity, image, status)
             VALUES
                (:name, :slug, 'modality', :category_id, :description, :price, 'fixed', :gst_percent,
                 :duration_minutes, 'single', :capacity, :image, :status)"
        );
        $stmt->execute([
            'name'             => $data['name'],
            'slug'             => $this->uniqueSlug((string) $data['name']),
            'category_id'      => $data['category_id'],
            'description'      => $data['description'] !== '' ? $data['description'] : null,
            'price'            => $data['price'],
            'gst_percent'      => $data['gst_percent'] ?? (float) config('app.booking.gst_rate', 18),
            'duration_minutes' => $data['duration_minutes'],
            'capacity'         => $data['capacity'],
            'image'            => $data['image'] ?? null,
            'status'           => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Does anything keep pointing at this service: a booking, a live hold, a combo package that includes
     * it, or package credits? Then it can be hidden but not erased without breaking that history.
     */
    public function isReferenced(int $id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT (SELECT COUNT(*) FROM bookings WHERE service_id = :a)
                  + (SELECT COUNT(*) FROM booking_holds WHERE service_id = :b)
                  + (SELECT COUNT(*) FROM package_items WHERE service_id = :c)
                  + (SELECT COUNT(*) FROM customer_package_credits WHERE service_id = :d)'
        );
        $stmt->execute(['a' => $id, 'b' => $id, 'c' => $id, 'd' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Remove a service nothing refers to. Its duration options, schedules and blocks go with it. */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM services WHERE id = ?');
        return $stmt->execute([$id]);
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
        $set = [
            'price'       => $data['price'],
            'capacity'    => $data['capacity'],
            'description' => $data['description'],
            'status'      => $data['status'] ?? 'active',
            'image'       => $data['image'] ?? null,
        ];
        // Optional: only changed when given (name, session length, category). The URL key (slug) never changes
        // on a rename, so links and bookmarks to the modality keep working.
        foreach (['name', 'duration_minutes', 'category_id'] as $optional) {
            if (array_key_exists($optional, $data)) {
                $set[$optional] = $data[$optional];
            }
        }
        $assign = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($set)));
        $stmt = $this->db->prepare("UPDATE services SET $assign WHERE id = :id");
        return $stmt->execute($set + ['id' => $id]);
    }
}
