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
     * Get all active carousel slides.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM hero_banners WHERE is_active = 1 ORDER BY id ASC'
        );
        $slides = $stmt->fetchAll() ?: [];

        if (count($slides) <= 1) {
            $primary = $slides[0] ?? [
                'badge_text'  => 'Sports Science & High-Performance Lab',
                'headline'    => "Recover Faster.\nRecharge Fully.\nPerform at Your Peak.",
                'subheadline' => 'Science-backed hot, cold, and hydrotherapy recovery protocols. Optimizing athletic regeneration and physical performance.',
                'image_url'   => 'images/hero-banner.jpg',
                'cta_text'    => 'Reserve Recovery Session',
                'cta_link'    => '/booking',
            ];

            return [
                $primary,
                [
                    'badge_text'  => 'Extreme Cryo & Contrast Therapy',
                    'headline'    => "Ice Bath & Cryo Immersion Protocols",
                    'subheadline' => 'Medical-grade cold water immersion (8°C - 10°C) engineered to drastically flush lactic acid, eliminate systemic inflammation, and reset nervous balance.',
                    'image_url'   => 'images/services/ice-bath.jpg',
                    'cta_text'    => 'Book Cold Immersion',
                    'cta_link'    => '/booking?service_id=4',
                ],
                [
                    'badge_text'  => 'Infrared Heat & Detoxification',
                    'headline'    => "Thermal Finnish Sauna & Hydrotherapy",
                    'subheadline' => 'Elevate cardiovascular micro-circulation and release muscular tension with controlled Finnish dry sauna and deep therapeutic hot baths.',
                    'image_url'   => 'images/services/sauna.jpg',
                    'cta_text'    => 'Book Thermal Session',
                    'cta_link'    => '/booking?service_id=2',
                ],
                [
                    'badge_text'  => 'Low-Impact Aquatic Conditioning',
                    'headline'    => "Endless Lap Pool & Underwater Treadmill",
                    'subheadline' => 'Zero-gravity buoyant cardiovascular training for active rehabilitation, injury prevention, and athletic movement re-education.',
                    'image_url'   => 'images/services/endless-pool.jpg',
                    'cta_text'    => 'Explore Aquatic Protocols',
                    'cta_link'    => '/services',
                ],
            ];
        }

        return $slides;
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
