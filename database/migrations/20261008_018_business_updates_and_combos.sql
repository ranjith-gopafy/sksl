-- Migration: 20261008_018_business_updates_and_combos.sql
-- Purpose: Apply user business updates for pricing, modalities, combos, and banners

-- 1. Remove/deactivate Hot Bath
UPDATE `services` SET `status` = 'inactive' WHERE `slug` = 'hot-bath';

-- 2. Rename Spa to Massages
UPDATE `services` 
SET `name` = 'Massages', 
    `slug` = 'massages', 
    `description` = 'Targeted therapeutic sports massage, myofascial release, and athletic muscular tension alleviation.',
    `duration_minutes` = 30,
    `price` = 846.61, -- 846.61 + 18% GST (152.39) = 999.00
    `duration_mode` = 'multiple'
WHERE `slug` = 'spa' OR `slug` = 'massages';

-- 3. Update single modality durations & base prices in services table
UPDATE `services` SET `duration_minutes` = 15, `price` = 507.63 WHERE `slug` = 'sauna';
UPDATE `services` SET `duration_minutes` = 15, `price` = 381.36 WHERE `slug` = 'steam';
UPDATE `services` SET `duration_minutes` = 15, `price` = 338.14 WHERE `slug` = 'ice-bath';
UPDATE `services` SET `duration_minutes` = 30, `price` = 507.63 WHERE `slug` = 'endless-pool';
UPDATE `services` SET `name` = 'Aqua Cycle', `duration_minutes` = 15, `price` = 148.31 WHERE `slug` = 'cycle';
UPDATE `services` SET `name` = 'Aqua Walker', `duration_minutes` = 15, `price` = 148.31 WHERE `slug` = 'walker';
UPDATE `services` SET `name` = 'Aqua Treadmill', `duration_minutes` = 15, `price` = 148.31 WHERE `slug` = 'treadmill';
UPDATE `services` SET `duration_minutes` = 45, `price` = 422.88 WHERE `slug` = 'lap-pool';

-- Clean existing duration options for all updated services
DELETE FROM `service_duration_options` 
WHERE `service_id` IN (SELECT id FROM `services` WHERE `slug` IN ('sauna', 'steam', 'ice-bath', 'endless-pool', 'cycle', 'walker', 'treadmill', 'lap-pool', 'massages', 'spa'));

-- Insert Clean Duration Options. Prices are the ex-GST base (same convention as services.price);
-- GST is added at checkout: 846.61 + 18% = 999, 1270.34 + 18% = 1499, 1524.58 + 18% = 1799.
-- Massages (30m=999, 60m=1499, 90m=1799)
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 30, 846.61, '30 Minutes Session', 1, 'active' FROM `services` s WHERE s.slug = 'massages'
UNION ALL
SELECT s.id, 60, 1270.34, '60 Minutes (1 Hour)', 2, 'active' FROM `services` s WHERE s.slug = 'massages'
UNION ALL
SELECT s.id, 90, 1524.58, '90 Minutes Session', 3, 'active' FROM `services` s WHERE s.slug = 'massages';

-- Sauna: 15 min = 599
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 507.63, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'sauna';

-- Steam: 15 min = 450
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 381.36, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'steam';

-- Ice Bath: 15 min = 399
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 338.14, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'ice-bath';

-- Endless Pool: 30 min = 599
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 30, 507.63, '30 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'endless-pool';

-- Aqua Cycle: 15 min = 175
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 148.31, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'cycle';

-- Aqua Walker: 15 min = 175
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 148.31, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'walker';

-- Aqua Treadmill: 15 min = 175
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 148.31, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'treadmill';

-- Lap Pool: 45 min = 499
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 45, 422.88, '45 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'lap-pool';

-- 4. Deactivate the 'Sports Science & High-Performance Lab' slide from hero_banners
UPDATE `hero_banners` 
SET `is_active` = 0 
WHERE `badge_text` LIKE '%Sports Science%' 
   OR `headline` LIKE '%Sports Science%'
   OR `subheadline` LIKE '%Sports Science%';

-- 5. Set up the 5 new Combo Packages
UPDATE `packages` SET `status` = 'inactive'
WHERE `slug` IN ('ultimate-recovery-trio', 'hydro-cryo-pro-5-pack', 'endless-pool-lap-athlete-bundle');

-- Package 1: Sauna 10 min + Ice Bath 10 min = 499 (Base: 422.88, GST: 76.12)
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `gst_percent`, `total_price`, `validity_days`, `badge_text`, `status`)
VALUES (
    'Sauna & Ice Bath Contrast Circuit',
    'sauna-ice-bath-contrast',
    '10 min Infrared Sauna thermal immersion followed by 10 min Ice Bath cryo contrast protocol.',
    422.88, 18.00, 499.00, 30, 'Thermal & Cryo', 'active'
) ON DUPLICATE KEY UPDATE `price` = 422.88, `total_price` = 499.00, `status` = 'active';

-- Package 2: Steam 10 min + Ice Bath 10 min = 399 (Base: 338.14, GST: 60.86)
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `gst_percent`, `total_price`, `validity_days`, `badge_text`, `status`)
VALUES (
    'Steam & Ice Bath Flush',
    'steam-ice-bath-flush',
    '10 min Eucalyptus Steam therapy followed by 10 min Ice Bath rapid contrast flush.',
    338.14, 18.00, 399.00, 30, 'Popular Contrast', 'active'
) ON DUPLICATE KEY UPDATE `price` = 338.14, `total_price` = 399.00, `status` = 'active';

-- Package 3: Massage 30 min + Steam 10 min + Ice Bath 5 min = 1399 (Base: 1185.59, GST: 213.41)
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `gst_percent`, `total_price`, `validity_days`, `badge_text`, `status`)
VALUES (
    'Massage, Steam & Ice Bath Reset',
    'massage-steam-ice-reset',
    '30 min Targeted Sports Massage, 10 min Steam therapy, and 5 min Ice Bath cellular recovery flush.',
    1185.59, 18.00, 1399.00, 30, 'Total Athlete Reset', 'active'
) ON DUPLICATE KEY UPDATE `price` = 1185.59, `total_price` = 1399.00, `status` = 'active';

-- Package 4: Massage 30 min + Shower 5 min + Ice Bath 10 min = 1299 (Base: 1100.85, GST: 198.15)
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `gst_percent`, `total_price`, `validity_days`, `badge_text`, `status`)
VALUES (
    'Recovery Massage & Cold Plunge',
    'massage-shower-ice-protocol',
    '30 min Therapeutic Massage and 10 min Ice Bath immersion with included 5 min invigorating shower protocol.',
    1100.85, 18.00, 1299.00, 30, 'Muscle & Contrast', 'active'
) ON DUPLICATE KEY UPDATE `price` = 1100.85, `total_price` = 1299.00, `status` = 'active';

-- Package 5: Hydrotherapy 45 min – Aqua Cycle 15m + Aqua Walker 15m + Aqua Treadmill 15m = 499 (Base: 422.88, GST: 76.12)
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `gst_percent`, `total_price`, `validity_days`, `badge_text`, `status`)
VALUES (
    'Hydrotherapy Circuit 45 Min',
    'hydrotherapy-circuit-45m',
    'Complete 45 min aquatic conditioning circuit bundling 15 min Aqua Cycle, 15 min Aqua Walker, and 15 min Aqua Treadmill.',
    422.88, 18.00, 499.00, 30, 'Aquatic Conditioning', 'active'
) ON DUPLICATE KEY UPDATE `price` = 422.88, `total_price` = 499.00, `status` = 'active';

-- 6. Allocate Package Items for the 5 packages
DELETE FROM `package_items` WHERE `package_id` IN (
    SELECT id FROM `packages` WHERE `slug` IN (
        'sauna-ice-bath-contrast',
        'steam-ice-bath-flush',
        'massage-steam-ice-reset',
        'massage-shower-ice-protocol',
        'hydrotherapy-circuit-45m'
    )
);

-- Items for Package 1 (Sauna + Ice Bath)
INSERT INTO `package_items` (`package_id`, `service_id`, `quantity`)
SELECT p.id, s.id, 1 FROM `packages` p, `services` s
WHERE p.slug = 'sauna-ice-bath-contrast' AND s.slug IN ('sauna', 'ice-bath');

-- Items for Package 2 (Steam + Ice Bath)
INSERT INTO `package_items` (`package_id`, `service_id`, `quantity`)
SELECT p.id, s.id, 1 FROM `packages` p, `services` s
WHERE p.slug = 'steam-ice-bath-flush' AND s.slug IN ('steam', 'ice-bath');

-- Items for Package 3 (Massages + Steam + Ice Bath)
INSERT INTO `package_items` (`package_id`, `service_id`, `quantity`)
SELECT p.id, s.id, 1 FROM `packages` p, `services` s
WHERE p.slug = 'massage-steam-ice-reset' AND s.slug IN ('massages', 'steam', 'ice-bath');

-- Items for Package 4 (Massages + Ice Bath)
INSERT INTO `package_items` (`package_id`, `service_id`, `quantity`)
SELECT p.id, s.id, 1 FROM `packages` p, `services` s
WHERE p.slug = 'massage-shower-ice-protocol' AND s.slug IN ('massages', 'ice-bath');

-- Items for Package 5 (Aqua Cycle + Aqua Walker + Aqua Treadmill)
INSERT INTO `package_items` (`package_id`, `service_id`, `quantity`)
SELECT p.id, s.id, 1 FROM `packages` p, `services` s
WHERE p.slug = 'hydrotherapy-circuit-45m' AND s.slug IN ('cycle', 'walker', 'treadmill');
