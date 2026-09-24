<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Hero Banner Model
 *
 * Manages dynamic hero banners (up to 5) for the homepage carousel.
 */
class HeroBannerModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get the first active banner.
     *
     * @return array<string, mixed>|null
     */
    public function getActive(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM hero_banners WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $banner = $stmt->fetch() ?: null;
        if ($banner) {
            return $banner;
        }
        $all = $this->getAllActive();
        return !empty($all) ? $all[0] : null;
    }

    /**
     * Get the default/first banner.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM hero_banners ORDER BY id ASC LIMIT 1');
        return $stmt->fetch() ?: null;
    }

    /**
     * Update the primary hero banner (backward compatibility).
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): bool
    {
        $first = $this->getAll();
        $id = !empty($first) ? (int) $first[0]['id'] : 1;
        return $this->updateById($id, $data);
    }

    /**
     * Get all banners ordered by id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM hero_banners ORDER BY id ASC LIMIT 5');
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get banner by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hero_banners WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get active banners for homepage carousel (up to 5).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM hero_banners WHERE is_active = 1 ORDER BY id ASC LIMIT 5'
        );
        $slides = $stmt->fetchAll() ?: [];

        if (empty($slides)) {
            $all = $this->getAll();
            if (!empty($all)) {
                return $all;
            }
            return [
                [
                    'id'          => 1,
                    'badge_text'  => 'Sports Science & High-Performance Lab',
                    'headline'    => "Recover Faster.\nRecharge Fully.\nPerform at Your Peak.",
                    'subheadline' => 'Science-backed hot, cold, and hydrotherapy recovery protocols for peak athletic regeneration.',
                    'image_url'   => 'images/hero-banner.jpg',
                    'cta_text'    => 'Reserve Recovery Session',
                    'cta_link'    => '/booking',
                    'is_active'   => 1,
                ]
            ];
        }

        return $slides;
    }

    /**
     * Count total banners.
     */
    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM hero_banners')->fetchColumn();
    }

    /**
     * Insert a new banner record (max 5 allowed).
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        if ($this->count() >= 5) {
            throw new \RuntimeException('Maximum 5 hero banners allowed.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO hero_banners (badge_text, headline, subheadline, image_url, cta_text, cta_link, is_active)
             VALUES (:badge_text, :headline, :subheadline, :image_url, :cta_text, :cta_link, :is_active)'
        );

        $stmt->execute([
            'badge_text'  => $data['badge_text'] ?? 'Sports Science & High-Performance Lab',
            'headline'    => $data['headline'] ?? 'Elite Athletic Recovery Lab',
            'subheadline' => $data['subheadline'] ?? '',
            'image_url'   => $data['image_url'] ?? 'images/hero-banner.jpg',
            'cta_text'    => $data['cta_text'] ?? 'Reserve Recovery Session',
            'cta_link'    => $data['cta_link'] ?? '/booking',
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update banner by ID.
     *
     * @param array<string, mixed> $data
     */
    public function updateById(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE hero_banners SET
                badge_text  = :badge_text,
                headline    = :headline,
                subheadline = :subheadline,
                image_url   = :image_url,
                cta_text    = :cta_text,
                cta_link    = :cta_link,
                is_active   = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            'badge_text'  => $data['badge_text'] ?? 'Sports Science & High-Performance Lab',
            'headline'    => $data['headline'] ?? 'Elite Athletic Recovery Lab',
            'subheadline' => $data['subheadline'] ?? '',
            'image_url'   => $data['image_url'] ?? 'images/hero-banner.jpg',
            'cta_text'    => $data['cta_text'] ?? 'Reserve Recovery Session',
            'cta_link'    => $data['cta_link'] ?? '/booking',
            'is_active'   => (int) ($data['is_active'] ?? 1),
            'id'          => $id,
        ]);
    }

    /**
     * Delete banner by ID. Ensures at least 1 banner remains.
     */
    public function delete(int $id): bool
    {
        if ($this->count() <= 1) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM hero_banners WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
