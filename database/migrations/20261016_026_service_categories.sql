-- Migration: 20261016_026_service_categories
-- Purpose: modality categories become real, editable data (they used to be guessed from the modality name).
--   1. service_categories: the groups shown as filter buttons on the home page and the modalities page.
--   2. services.category_id: which group a modality belongs to (NULL for the practitioner services,
--      which are not grouped). RESTRICT keeps a category with modalities from being deleted.
--   3. The four groups and the existing modalities are filled in so the pages look exactly as before.

-- 1. categories
CREATE TABLE IF NOT EXISTS `service_categories` (
    `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)       NOT NULL,
    `slug`       VARCHAR(80)       NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_service_categories_name` (`name`),
    UNIQUE KEY `uq_service_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. link from modalities
ALTER TABLE `services`
    ADD COLUMN `category_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `category`,
    ADD KEY `idx_services_category_id` (`category_id`),
    ADD CONSTRAINT `fk_services_category`
        FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- 3. the starting groups and the current modalities
INSERT IGNORE INTO `service_categories` (`name`, `slug`, `sort_order`) VALUES
    ('Cold & Cryo Immersion', 'cold-cryo-immersion', 1),
    ('Thermal & Sauna',       'thermal-sauna',       2),
    ('Hydrotherapy',          'hydrotherapy',        3),
    ('Myotherapy',            'myotherapy',          4);

UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'cold-cryo-immersion'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('ice-bath');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'thermal-sauna'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('sauna', 'steam', 'hot-bath');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'hydrotherapy'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('endless-pool', 'cycle', 'treadmill', 'walker', 'lap-pool');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'myotherapy'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('massages');
