-- Migration: 20260907_011_create_hero_banners_and_update_service_images
-- Purpose: Create hero_banners table for admin-controlled hero section and populate services images

CREATE TABLE IF NOT EXISTS `hero_banners` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `badge_text`  VARCHAR(100) NOT NULL DEFAULT 'Exclusive Athletic Recovery',
    `headline`    VARCHAR(255) NOT NULL DEFAULT 'Elite Athletic Recovery & Thermal Therapy Lab',
    `subheadline` TEXT         NOT NULL,
    `image_url`   VARCHAR(500) NULL DEFAULT 'images/hero-banner.jpg',
    `cta_text`    VARCHAR(100) NOT NULL DEFAULT 'Reserve Your Modality',
    `cta_link`    VARCHAR(255) NOT NULL DEFAULT '/booking',
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default active hero banner
INSERT INTO `hero_banners` (`badge_text`, `headline`, `subheadline`, `image_url`, `cta_text`, `cta_link`, `is_active`)
VALUES (
    'Sports Science & High-Performance Lab',
    'Recover Faster. Recharge Fully. Perform at Your Peak.',
    'Bengaluru\'s premier sports recovery center combining clinical contrast therapy, ice baths, infrared saunas, and aquatic conditioning for marathoners, triathletes, and competitive sports performers.',
    'images/hero-banner.jpg',
    'Reserve Recovery Session',
    '/booking',
    1
) ON DUPLICATE KEY UPDATE id=id;

-- Update image paths for all 10 services
UPDATE `services` SET `image` = 'images/services/spa.jpg' WHERE `slug` = 'spa';
UPDATE `services` SET `image` = 'images/services/sauna.jpg' WHERE `slug` = 'sauna';
UPDATE `services` SET `image` = 'images/services/steam.jpg' WHERE `slug` = 'steam';
UPDATE `services` SET `image` = 'images/services/ice-bath.jpg' WHERE `slug` = 'ice-bath';
UPDATE `services` SET `image` = 'images/services/hot-bath.jpg' WHERE `slug` = 'hot-bath';
UPDATE `services` SET `image` = 'images/services/endless-pool.jpg' WHERE `slug` = 'endless-pool';
UPDATE `services` SET `image` = 'images/services/cycle.jpg' WHERE `slug` = 'cycle';
UPDATE `services` SET `image` = 'images/services/treadmill.jpg' WHERE `slug` = 'treadmill';
UPDATE `services` SET `image` = 'images/services/walker.jpg' WHERE `slug` = 'walker';
UPDATE `services` SET `image` = 'images/services/lap-pool.jpg' WHERE `slug` = 'lap-pool';
