-- Migration: 20261008_016_phase2_core_schema
-- Purpose: SKSL Phase 2 Foundation Schema Extension
--   1. Variable duration & pricing models (service_duration_options)
--   2. Professional & recurring availability schedules (availability_rules)
--   3. Administrative manual time blocking (manual_blocks)
--   4. Combo packages, items, customer purchases & credit ledger (packages, package_items, customer_packages, customer_package_credits)
--   5. Non-breaking extension of bookings (booking_type, payment_mode, duration_option_id, package_credit_id, visit_status)
--   6. Multi-source payment support (payment_source, customer_package_id)
--   7. Backfill duration options and professional services (Physiotherapy & Sports Psychology)

-- 1. Extend services table with Category, Pricing Type & Duration Mode
ALTER TABLE `services`
    ADD COLUMN `category` ENUM('modality','professional') NOT NULL DEFAULT 'modality' AFTER `slug`,
    ADD COLUMN `pricing_type` ENUM('fixed','variable','pay_at_centre') NOT NULL DEFAULT 'fixed' AFTER `price`,
    ADD COLUMN `duration_mode` ENUM('single','multiple') NOT NULL DEFAULT 'single' AFTER `duration_minutes`;

-- 2. Create service_duration_options table
CREATE TABLE IF NOT EXISTS `service_duration_options` (
    `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `service_id`       BIGINT UNSIGNED   NOT NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `price`            DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `label`            VARCHAR(100)      NULL DEFAULT NULL,
    `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_service_duration` (`service_id`, `duration_minutes`),
    KEY `idx_duration_status` (`status`),
    CONSTRAINT `fk_duration_options_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create availability_rules table (Professional schedules & Specific Date exceptions)
CREATE TABLE IF NOT EXISTS `availability_rules` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `service_id`    BIGINT UNSIGNED  NOT NULL,
    `rule_type`     ENUM('recurring_weekday','specific_date') NOT NULL DEFAULT 'recurring_weekday',
    `weekday`       TINYINT UNSIGNED NULL DEFAULT NULL,
    `specific_date` DATE             NULL DEFAULT NULL,
    `start_time`    TIME             NOT NULL,
    `end_time`      TIME             NOT NULL,
    `is_available`  TINYINT(1)       NOT NULL DEFAULT 1,
    `notes`         VARCHAR(255)     NULL DEFAULT NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_avail_rules_service_type` (`service_id`, `rule_type`, `status`),
    KEY `idx_avail_rules_date` (`specific_date`),
    KEY `idx_avail_rules_weekday` (`weekday`),
    CONSTRAINT `fk_avail_rules_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create manual_blocks table (Maintenance and administrative blocking)
CREATE TABLE IF NOT EXISTS `manual_blocks` (
    `id`                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `service_id`          BIGINT UNSIGNED  NULL DEFAULT NULL,
    `block_type`          ENUM('specific_date','recurring_weekday') NOT NULL DEFAULT 'specific_date',
    `specific_date`       DATE             NULL DEFAULT NULL,
    `weekday`             TINYINT UNSIGNED NULL DEFAULT NULL,
    `start_time`          TIME             NOT NULL,
    `end_time`            TIME             NOT NULL,
    `reason`              VARCHAR(255)     NOT NULL DEFAULT 'Maintenance / Administrative Block',
    `status`              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by_admin_id` BIGINT UNSIGNED  NULL DEFAULT NULL,
    `created_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_manual_blocks_service` (`service_id`),
    KEY `idx_manual_blocks_date` (`specific_date`),
    KEY `idx_manual_blocks_status` (`status`),
    CONSTRAINT `fk_manual_blocks_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create packages table
CREATE TABLE IF NOT EXISTS `packages` (
    `id`             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150)      NOT NULL,
    `slug`           VARCHAR(150)      NOT NULL,
    `description`    TEXT              NULL DEFAULT NULL,
    `price`          DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `gst_percent`    DECIMAL(5,2)      NOT NULL DEFAULT 18.00,
    `total_price`    DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `validity_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `badge_text`     VARCHAR(50)       NULL DEFAULT NULL,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_packages_slug` (`slug`),
    KEY `idx_packages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create package_items table
CREATE TABLE IF NOT EXISTS `package_items` (
    `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `package_id` BIGINT UNSIGNED   NOT NULL,
    `service_id` BIGINT UNSIGNED   NOT NULL,
    `quantity`   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_package_item` (`package_id`, `service_id`),
    CONSTRAINT `fk_package_items_package`
        FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_package_items_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create customer_packages table
CREATE TABLE IF NOT EXISTS `customer_packages` (
    `id`                     BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`                BIGINT UNSIGNED   NOT NULL,
    `package_id`             BIGINT UNSIGNED   NOT NULL,
    `purchase_reference`     VARCHAR(30)       NOT NULL,
    `package_name_snapshot`  VARCHAR(150)      NOT NULL,
    `validity_days_snapshot` SMALLINT UNSIGNED NOT NULL,
    `base_amount`            DECIMAL(10,2)     NOT NULL,
    `gst_percent`            DECIMAL(5,2)      NOT NULL DEFAULT 18.00,
    `gst_amount`             DECIMAL(10,2)     NOT NULL,
    `total_amount`           DECIMAL(10,2)     NOT NULL,
    `payment_status`         ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    `purchased_at`           DATETIME          NULL DEFAULT NULL,
    `expires_at`             DATETIME          NOT NULL,
    `status`                 ENUM('active','expired','depleted','cancelled') NOT NULL DEFAULT 'active',
    `created_at`             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cust_pkg_reference` (`purchase_reference`),
    KEY `idx_cust_pkg_user` (`user_id`, `status`),
    KEY `idx_cust_pkg_expires` (`expires_at`),
    CONSTRAINT `fk_cust_pkg_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cust_pkg_package`
        FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create customer_package_credits table
CREATE TABLE IF NOT EXISTS `customer_package_credits` (
    `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `customer_package_id` BIGINT UNSIGNED   NOT NULL,
    `service_id`          BIGINT UNSIGNED   NOT NULL,
    `allocated_quantity`  SMALLINT UNSIGNED NOT NULL,
    `used_quantity`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `remaining_quantity`  SMALLINT UNSIGNED NOT NULL,
    `created_at`          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pkg_credit_service` (`customer_package_id`, `service_id`),
    KEY `idx_pkg_credit_avail` (`service_id`, `remaining_quantity`),
    CONSTRAINT `fk_pkg_credits_cust_pkg`
        FOREIGN KEY (`customer_package_id`) REFERENCES `customer_packages` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pkg_credits_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Extend bookings table
ALTER TABLE `bookings`
    ADD COLUMN `booking_type` ENUM('standard','professional','package') NOT NULL DEFAULT 'standard' AFTER `service_id`,
    ADD COLUMN `payment_mode` ENUM('payment_required','payment_not_required','package_credit') NOT NULL DEFAULT 'payment_required' AFTER `booking_type`,
    ADD COLUMN `duration_option_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `payment_mode`,
    ADD COLUMN `package_credit_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `duration_option_id`,
    ADD COLUMN `visit_status` ENUM('pending','visited','no_show') NOT NULL DEFAULT 'pending' AFTER `payment_status`,
    ADD COLUMN `visit_marked_at` DATETIME NULL DEFAULT NULL AFTER `visit_status`,
    ADD COLUMN `visit_marked_by_admin_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `visit_marked_at`,
    ADD KEY `idx_bookings_type` (`booking_type`),
    ADD KEY `idx_bookings_visit_status` (`visit_status`);

-- 10. Extend payments table for multi-source purchases
ALTER TABLE `payments`
    MODIFY COLUMN `booking_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `payment_source` ENUM('booking','package') NOT NULL DEFAULT 'booking' AFTER `id`,
    ADD COLUMN `customer_package_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `booking_id`,
    ADD KEY `idx_payments_source` (`payment_source`),
    ADD KEY `idx_payments_cust_pkg` (`customer_package_id`);

-- 11. Backfill existing modalities with 1 default duration option
INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT `id`, `duration_minutes`, `price`, CONCAT(`duration_minutes`, ' Minutes'), 1, 'active'
FROM `services`
WHERE NOT EXISTS (
    SELECT 1 FROM `service_duration_options` sdo WHERE sdo.service_id = services.id
);

-- 12. Seed Professional Services: Physiotherapy & Sports Psychology
INSERT INTO `services` (`name`, `slug`, `category`, `description`, `price`, `pricing_type`, `gst_percent`, `duration_minutes`, `duration_mode`, `capacity`, `image`, `status`)
VALUES 
('Physiotherapy & Sports Rehab', 'physiotherapy', 'professional', 'Clinical injury evaluation, musculoskeletal assessment, active rehabilitation and mobility restoration tailored to athlete recovery.', 0.00, 'pay_at_centre', 18.00, 45, 'single', 1, 'images/services/physiotherapy.jpg', 'active'),
('Sports Psychology & Mental Conditioning', 'sports-psychology', 'professional', 'Cognitive performance conditioning, athletic stress management, focus optimization and clinical psychological support.', 0.00, 'pay_at_centre', 18.00, 60, 'single', 1, 'images/services/sports-psychology.jpg', 'active')
ON DUPLICATE KEY UPDATE `category` = 'professional', `pricing_type` = 'pay_at_centre';

INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 45, 0.00, '45 Minutes Session', 1, 'active'
FROM `services` s WHERE s.slug = 'physiotherapy'
ON DUPLICATE KEY UPDATE `price` = 0.00;

INSERT INTO `service_duration_options` (`service_id`, `duration_minutes`, `price`, `label`, `sort_order`, `status`)
SELECT s.id, 60, 0.00, '60 Minutes (1 Hour)', 1, 'active'
FROM `services` s WHERE s.slug = 'sports-psychology'
ON DUPLICATE KEY UPDATE `price` = 0.00;
