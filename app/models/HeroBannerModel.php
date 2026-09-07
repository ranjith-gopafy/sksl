<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Hero Banner Model
 *
 * Manages the dynamic, admin-controlled hero banner on the homepage.
 */
class HeroBannerModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get the currently active hero banner for display on the homepage.
     *
     * @return array<string, mixed>|null
     */
    public function getActive(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM hero_banners WHERE is_active = 1 ORDER BY id DESC LIMIT 1'
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Get the latest banner record for administrative management.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM hero_banners ORDER BY id DESC LIMIT 1'
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Update the hero banner configurations.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): bool
    {
        $existing = $this->get();

        if ($existing) {
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
                'id'          => (int) $existing['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO hero_banners (badge_text, headline, subheadline, image_url, cta_text, cta_link, is_active)
             VALUES (:badge_text, :headline, :subheadline, :image_url, :cta_text, :cta_link, :is_active)'
        );

        return $stmt->execute([
            'badge_text'  => $data['badge_text'] ?? 'Sports Science & High-Performance Lab',
            'headline'    => $data['headline'] ?? 'Elite Athletic Recovery Lab',
            'subheadline' => $data['subheadline'] ?? '',
            'image_url'   => $data['image_url'] ?? 'images/hero-banner.jpg',
            'cta_text'    => $data['cta_text'] ?? 'Reserve Recovery Session',
            'cta_link'    => $data['cta_link'] ?? '/booking',
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ]);
    }
}
