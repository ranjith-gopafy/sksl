<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Service Category Model
 *
 * The groups (Cold & Cryo Immersion, Thermal & Sauna, ...) that recovery modalities belong to.
 * They drive the filter buttons on the home page and the modalities page.
 */
class ServiceCategoryModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /** Words for the name rule, shown to the admin when a name is refused. */
    public const NAME_RULE = "Use letters, numbers, spaces and & , . ' ( ) / + - only, with at least one letter.";

    /**
     * A name customers see on pages, invoices and emails: letters (any language), numbers, spaces and a few
     * punctuation marks, and at least one letter. No markup, quotes-and-semicolons tricks or emoji.
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[\p{L}\p{N} &,.\'’()\/+-]+$/u', $name) === 1 && preg_match('/\p{L}/u', $name) === 1;
    }

    /**
     * All categories in display order, each with how many modalities it holds
     * (all statuses, as the admin sees it) and how many of those are active (as customers see it).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            "SELECT c.id, c.name, c.slug, c.sort_order,
                    COUNT(s.id) AS modality_count,
                    COALESCE(SUM(s.status = 'active'), 0) AS active_count
             FROM service_categories c
             LEFT JOIN services s ON s.category_id = c.id
             GROUP BY c.id, c.name, c.slug, c.sort_order
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Categories that have at least one active modality (the ones worth a filter button for customers).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWithActiveModalities(): array
    {
        return array_values(array_filter(
            $this->getAll(),
            static fn (array $c): bool => (int) $c['active_count'] > 0
        ));
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM service_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Case-insensitive name lookup (the column collation is case-insensitive). */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM service_categories WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM service_categories WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $exceptId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    /** A unique URL-safe key built from the name ("Cold & Cryo" -> "cold-cryo", "cold-cryo-2", ...). */
    public function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'category';
        }
        $base = substr($base, 0, 70);
        $slug = $base;
        for ($n = 2; $this->slugExists($slug, $exceptId); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    public function nextSortOrder(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM service_categories')->fetchColumn();
    }

    public function create(string $name, int $sortOrder): int
    {
        $stmt = $this->db->prepare('INSERT INTO service_categories (name, slug, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([$name, $this->uniqueSlug($name), $sortOrder]);
        return (int) $this->db->lastInsertId();
    }

    /** Rename and reorder. The key (slug) follows the name so filter buttons stay in step with it. */
    public function update(int $id, string $name, int $sortOrder): bool
    {
        $stmt = $this->db->prepare('UPDATE service_categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?');
        return $stmt->execute([$name, $this->uniqueSlug($name, $id), $sortOrder, $id]);
    }

    public function modalityCount(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM services WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM service_categories WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
