-- Migration: 20261009_020_inclusive_prices_and_package_minutes
-- Purpose: catalogue prices are what the customer pays, GST included.
--   services.price, service_duration_options.price and packages.price now hold the
--   GST-inclusive figure. The taxable base and the GST are derived from it at checkout
--   (ServiceModel::calculatePricing), so the total always equals the listed price.
--   Absolute values, safe to re-run.
-- Also: package items carry their own session length (e.g. sauna 10 min + ice bath 10 min).

-- 1. Services (single-duration modalities) and their lengths
UPDATE `services` SET `price` = 999.00, `duration_minutes` = 30 WHERE `slug` = 'massages';
UPDATE `services` SET `price` = 599.00, `duration_minutes` = 15 WHERE `slug` = 'sauna';
UPDATE `services` SET `price` = 450.00, `duration_minutes` = 15 WHERE `slug` = 'steam';
UPDATE `services` SET `price` = 399.00, `duration_minutes` = 15 WHERE `slug` = 'ice-bath';
UPDATE `services` SET `price` = 599.00, `duration_minutes` = 30 WHERE `slug` = 'endless-pool';
UPDATE `services` SET `price` = 175.00, `duration_minutes` = 15 WHERE `slug` IN ('cycle', 'walker', 'treadmill');
UPDATE `services` SET `price` = 499.00, `duration_minutes` = 45 WHERE `slug` = 'lap-pool';

-- 2. Duration options (same convention)
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 999.00  WHERE s.slug = 'massages' AND o.duration_minutes = 30;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 1499.00 WHERE s.slug = 'massages' AND o.duration_minutes = 60;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 1799.00 WHERE s.slug = 'massages' AND o.duration_minutes = 90;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 599.00  WHERE s.slug IN ('sauna', 'endless-pool');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 450.00  WHERE s.slug = 'steam';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 399.00  WHERE s.slug = 'ice-bath';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 175.00  WHERE s.slug IN ('cycle', 'walker', 'treadmill');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id SET o.price = 499.00  WHERE s.slug = 'lap-pool';

-- 3. Per-item session length for packages and for the credits a purchase receives
ALTER TABLE `package_items` ADD COLUMN `duration_minutes` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `quantity`;
ALTER TABLE `customer_package_credits` ADD COLUMN `duration_minutes` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `service_id`;

-- 4. Packages: price is the GST-inclusive figure (total_price kept equal for older readers)
UPDATE `packages` SET `price` = `total_price`;
UPDATE `package_items` pi JOIN `packages` p ON p.id = pi.package_id JOIN `services` s ON s.id = pi.service_id
SET pi.duration_minutes = CASE
    WHEN p.slug = 'sauna-ice-bath-contrast'      AND s.slug = 'sauna'     THEN 10
    WHEN p.slug = 'sauna-ice-bath-contrast'      AND s.slug = 'ice-bath'  THEN 10
    WHEN p.slug = 'steam-ice-bath-flush'         AND s.slug = 'steam'     THEN 10
    WHEN p.slug = 'steam-ice-bath-flush'         AND s.slug = 'ice-bath'  THEN 10
    WHEN p.slug = 'massage-steam-ice-reset'      AND s.slug = 'massages'  THEN 30
    WHEN p.slug = 'massage-steam-ice-reset'      AND s.slug = 'steam'     THEN 10
    WHEN p.slug = 'massage-steam-ice-reset'      AND s.slug = 'ice-bath'  THEN 5
    WHEN p.slug = 'massage-shower-ice-protocol'  AND s.slug = 'massages'  THEN 30
    WHEN p.slug = 'massage-shower-ice-protocol'  AND s.slug = 'ice-bath'  THEN 10
    WHEN p.slug = 'hydrotherapy-circuit-45m'     THEN 15
    ELSE pi.duration_minutes END;

UPDATE `packages` SET `description` = '10 min Infrared Sauna followed by 10 min Ice Bath: thermal immersion then cryo contrast.' WHERE `slug` = 'sauna-ice-bath-contrast';
UPDATE `packages` SET `description` = '10 min Eucalyptus Steam followed by 10 min Ice Bath for a rapid contrast flush.' WHERE `slug` = 'steam-ice-bath-flush';
UPDATE `packages` SET `description` = '30 min Massage, 10 min Steam and 5 min Ice Bath for a complete recovery reset.' WHERE `slug` = 'massage-steam-ice-reset';
UPDATE `packages` SET `description` = '30 min Massage and 10 min Ice Bath, with a complimentary 5 min shower at the centre.' WHERE `slug` = 'massage-shower-ice-protocol';
UPDATE `packages` SET `description` = '45 min aquatic circuit: 15 min Aqua Cycle, 15 min Aqua Walker and 15 min Aqua Treadmill.' WHERE `slug` = 'hydrotherapy-circuit-45m';
UPDATE `packages` SET `name` = 'Hydrotherapy Circuit 45 Min' WHERE `slug` = 'hydrotherapy-circuit-45m';
