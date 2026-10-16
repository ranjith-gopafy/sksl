-- Seed: services catalogue (final, GST-inclusive prices)
-- Purpose: the complete SKSL catalogue on a fresh install: 9 recovery modalities, the two
--   professional services, their duration options, the combo-package contents (with per-item
--   session lengths) and the default practitioner working hours.
-- Every statement is insert-if-missing, so re-running the seeds NEVER overwrites prices or
--   schedules an administrator has changed.
-- Prices are what the customer pays, GST (18%) included.
-- Run AFTER migrations (the tables and the combo packages must exist).

INSERT IGNORE INTO `services`
    (`name`, `slug`, `category`, `description`, `price`, `pricing_type`, `gst_percent`, `duration_minutes`, `duration_mode`, `capacity`, `image`, `status`)
VALUES
    ('Massages', 'massages', 'modality', 'Targeted therapeutic sports massage, myofascial release, and athletic muscular tension alleviation.', 999.00, 'fixed', 18.00, 30, 'multiple', 4, 'images/services/spa.jpg', 'active'),
    ('Sauna', 'sauna', 'modality', NULL, 599.00, 'fixed', 18.00, 15, 'single', 4, 'images/services/sauna.jpg', 'active'),
    ('Steam', 'steam', 'modality', NULL, 450.00, 'fixed', 18.00, 15, 'single', 8, 'images/services/steam.jpg', 'active'),
    ('Ice Bath', 'ice-bath', 'modality', NULL, 399.00, 'fixed', 18.00, 15, 'single', 8, 'images/services/ice-bath.jpg', 'active'),
    ('Endless Pool', 'endless-pool', 'modality', NULL, 599.00, 'fixed', 18.00, 30, 'single', 2, 'images/services/endless-pool.jpg', 'active'),
    ('Aqua Cycle', 'cycle', 'modality', NULL, 175.00, 'fixed', 18.00, 15, 'single', 1, 'images/services/cycle.jpg', 'active'),
    ('Aqua Treadmill', 'treadmill', 'modality', NULL, 175.00, 'fixed', 18.00, 15, 'single', 1, 'images/services/treadmill.jpg', 'active'),
    ('Aqua Walker', 'walker', 'modality', 'Low-impact walking treadmill designed for active recovery and mobility warm-ups.', 175.00, 'fixed', 18.00, 15, 'single', 1, 'images/services/walker.jpg', 'active'),
    ('Lap Pool', 'lap-pool', 'modality', NULL, 499.00, 'fixed', 18.00, 45, 'single', 1, 'images/services/lap-pool.jpg', 'active'),
    ('Physiotherapy & Sports Rehab', 'physiotherapy', 'professional', 'Clinical injury evaluation, musculoskeletal assessment, active rehabilitation and mobility restoration tailored to athlete recovery.', 0.00, 'pay_at_centre', 18.00, 45, 'single', 1, 'images/services/physiotherapy.jpg', 'active'),
    ('Sports Psychology & Mental Conditioning', 'sports-psychology', 'professional', 'Cognitive performance conditioning, athletic stress management, focus optimization and clinical psychological support.', 0.00, 'pay_at_centre', 18.00, 60, 'single', 1, 'images/services/sports-psychology.jpg', 'active');

-- Duration options (price is GST-inclusive)
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 30, 999.00, '30 Minutes Session', 1, 'active' FROM `services` s WHERE s.slug = 'massages';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 60, 1499.00, '60 Minutes (1 Hour)', 2, 'active' FROM `services` s WHERE s.slug = 'massages';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 90, 1799.00, '90 Minutes Session', 3, 'active' FROM `services` s WHERE s.slug = 'massages';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 599.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'sauna';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 450.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'steam';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 399.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'ice-bath';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 30, 599.00, '30 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'endless-pool';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 175.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'cycle';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 175.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'treadmill';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 15, 175.00, '15 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'walker';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 45, 499.00, '45 Minutes', 1, 'active' FROM `services` s WHERE s.slug = 'lap-pool';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 45, 0.00, '45 Minutes Session', 1, 'active' FROM `services` s WHERE s.slug = 'physiotherapy';
INSERT IGNORE INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 60, 0.00, '60 Minutes (1 Hour)', 1, 'active' FROM `services` s WHERE s.slug = 'sports-psychology';

-- Combo package contents with the session length each credit books
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'sauna-ice-bath-contrast' AND s.slug = 'sauna';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'sauna-ice-bath-contrast' AND s.slug = 'ice-bath';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'steam-ice-bath-flush' AND s.slug = 'steam';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'steam-ice-bath-flush' AND s.slug = 'ice-bath';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 30 FROM `packages` p, `services` s WHERE p.slug = 'massage-steam-ice-reset' AND s.slug = 'massages';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'massage-steam-ice-reset' AND s.slug = 'steam';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 5 FROM `packages` p, `services` s WHERE p.slug = 'massage-steam-ice-reset' AND s.slug = 'ice-bath';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 30 FROM `packages` p, `services` s WHERE p.slug = 'massage-shower-ice-protocol' AND s.slug = 'massages';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 10 FROM `packages` p, `services` s WHERE p.slug = 'massage-shower-ice-protocol' AND s.slug = 'ice-bath';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 15 FROM `packages` p, `services` s WHERE p.slug = 'hydrotherapy-circuit-45m' AND s.slug = 'cycle';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 15 FROM `packages` p, `services` s WHERE p.slug = 'hydrotherapy-circuit-45m' AND s.slug = 'treadmill';
INSERT IGNORE INTO `package_items` (`package_id`, `service_id`, `quantity`, `duration_minutes`)
SELECT p.id, s.id, 1, 15 FROM `packages` p, `services` s WHERE p.slug = 'hydrotherapy-circuit-45m' AND s.slug = 'walker';

-- Default practitioner working hours (only when a service has no schedule yet; edit in Admin > Schedules)
INSERT INTO `availability_rules` (`service_id`, `rule_type`, `weekday`, `start_time`, `end_time`, `is_available`, `status`)
SELECT s.id, 'recurring_weekday', t.weekday, t.st, t.et, 1, 'active'
FROM `services` s JOIN (
    SELECT 1 AS weekday, '09:00:00' AS st, '18:00:00' AS et
    UNION ALL SELECT 2, '09:00:00', '18:00:00'
    UNION ALL SELECT 3, '09:00:00', '18:00:00'
    UNION ALL SELECT 4, '09:00:00', '18:00:00'
    UNION ALL SELECT 5, '09:00:00', '18:00:00'
    UNION ALL SELECT 6, '09:00:00', '14:00:00'
) t
WHERE s.slug = 'physiotherapy' AND NOT EXISTS (SELECT 1 FROM `availability_rules` x WHERE x.service_id = s.id);
INSERT INTO `availability_rules` (`service_id`, `rule_type`, `weekday`, `start_time`, `end_time`, `is_available`, `status`)
SELECT s.id, 'recurring_weekday', t.weekday, t.st, t.et, 1, 'active'
FROM `services` s JOIN (
    SELECT 2 AS weekday, '10:00:00' AS st, '17:00:00' AS et
    UNION ALL SELECT 4, '10:00:00', '17:00:00'
    UNION ALL SELECT 6, '10:00:00', '17:00:00'
) t
WHERE s.slug = 'sports-psychology' AND NOT EXISTS (SELECT 1 FROM `availability_rules` x WHERE x.service_id = s.id);
