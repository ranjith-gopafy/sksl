-- ============================================================
-- SKSL — Production Database Export
-- Sara Kinetic Sports Lab — Online Booking Platform
-- Version: 1.0 (MVP)
-- Generated: 2026-09-23
--
-- Contains:
--   - Schema (all 11 migrations in order)
--   - Seed data (10 services + initial admin)
--
-- HOW TO IMPORT:
--   phpMyAdmin: Import → Select this file → Go
--   CLI: mysql -u<user> -p <database> < sksl_export.sql
--
-- AFTER IMPORT:
--   1. Update admin email:
--      UPDATE admins SET email='your-admin@yourdomain.com' WHERE id=1;
--   2. Update invoice GSTIN and address in app/services/InvoiceService.php
--   3. Update hero banner slides via /admin/banner
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+05:30';

-- ── Migration 001: users ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(150)        NOT NULL,
    `email`         VARCHAR(255)        NOT NULL,
    `mobile`        VARCHAR(30)         NOT NULL,
    `password_hash` VARCHAR(255)        NOT NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 002: admins ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150)        NOT NULL,
    `email`      VARCHAR(255)        NOT NULL,
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`),
    KEY `idx_admins_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 003: admin_otps ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_otps` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   BIGINT UNSIGNED NOT NULL,
    `otp_hash`   VARCHAR(255)    NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    `used`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_admin_otps_admin_id_expires` (`admin_id`, `expires_at`),

    CONSTRAINT `fk_admin_otps_admin_id`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 004: services ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `services` (
    `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150)     NOT NULL,
    `slug`             VARCHAR(150)     NOT NULL,
    `description`      TEXT             NULL DEFAULT NULL,
    `price`            DECIMAL(10,2)    NOT NULL,
    `gst_percent`      DECIMAL(5,2)     NOT NULL DEFAULT 18.00,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `capacity`         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `image`            VARCHAR(500)     NULL DEFAULT NULL,
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_services_slug` (`slug`),
    KEY `idx_services_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 005: closed_dates ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `closed_dates` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `closed_date` DATE            NOT NULL,
    `reason`      VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_closed_dates_date` (`closed_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 006: bookings ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
    `id`                       BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `booking_reference`        VARCHAR(20)      NOT NULL,
    `user_id`                  BIGINT UNSIGNED  NOT NULL,
    `service_id`               BIGINT UNSIGNED  NOT NULL,
    `booking_date`             DATE             NOT NULL,
    `start_time`               TIME             NOT NULL,
    `end_time`                 TIME             NOT NULL,

    -- Snapshot fields — frozen at booking time
    `service_name_snapshot`    VARCHAR(150)     NOT NULL DEFAULT '',
    `service_duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `checkin_buffer_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `checkout_buffer_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `base_amount`              DECIMAL(10,2)    NOT NULL,
    `gst_percent`              DECIMAL(5,2)     NOT NULL DEFAULT 18.00,
    `gst_amount`               DECIMAL(10,2)    NOT NULL,
    `convenience_fee`          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `total_amount`             DECIMAL(10,2)    NOT NULL,

    `booking_status`  ENUM('pending','confirmed','cancelled','completed','expired') NOT NULL DEFAULT 'pending',
    `payment_status`  ENUM('pending','paid','failed','refunded')                   NOT NULL DEFAULT 'pending',
    `notes`           TEXT             NULL DEFAULT NULL,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookings_reference` (`booking_reference`),
    KEY `idx_bookings_user_date` (`user_id`, `booking_date`),
    KEY `idx_bookings_service_date_time` (`service_id`, `booking_date`, `start_time`),
    KEY `idx_bookings_booking_status` (`booking_status`),
    KEY `idx_bookings_payment_status` (`payment_status`),
    KEY `idx_bookings_date` (`booking_date`),

    CONSTRAINT `fk_bookings_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_bookings_service_id`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 007: booking_holds ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `booking_holds` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(20)     NOT NULL,
    `user_id`           BIGINT UNSIGNED NOT NULL,
    `service_id`        BIGINT UNSIGNED NOT NULL,
    `booking_date`      DATE            NOT NULL,
    `start_time`        TIME            NOT NULL,
    `end_time`          TIME            NOT NULL,
    `base_amount`       DECIMAL(10,2)   NOT NULL,
    `gst_amount`        DECIMAL(10,2)   NOT NULL,
    `total_amount`      DECIMAL(10,2)   NOT NULL,
    `status`            ENUM('active','released','expired') NOT NULL DEFAULT 'active',
    `expires_at`        DATETIME        NOT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_holds_reference` (`booking_reference`),
    KEY `idx_holds_user_id` (`user_id`),
    KEY `idx_holds_service_date_time` (`service_id`, `booking_date`, `start_time`),
    KEY `idx_holds_status_expires` (`status`, `expires_at`),

    CONSTRAINT `fk_holds_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_holds_service_id`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 008: payments ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`          BIGINT UNSIGNED NOT NULL,
    `razorpay_order_id`   VARCHAR(100)    NOT NULL,
    `razorpay_payment_id` VARCHAR(100)    NULL DEFAULT NULL,
    `razorpay_signature`  VARCHAR(255)    NULL DEFAULT NULL,
    `amount`              DECIMAL(10,2)   NOT NULL,
    `currency`            VARCHAR(10)     NOT NULL DEFAULT 'INR',
    `status`              ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `paid_at`             DATETIME        NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_razorpay_order_id` (`razorpay_order_id`),
    KEY `idx_payments_booking_id` (`booking_id`),
    KEY `idx_payments_status` (`status`),

    CONSTRAINT `fk_payments_booking_id`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 009: password_resets ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255)    NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    `used`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_password_resets_user_id` (`user_id`),
    KEY `idx_password_resets_expires` (`expires_at`),

    CONSTRAINT `fk_password_resets_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 010: email_logs ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `to_email`     VARCHAR(255)    NOT NULL,
    `subject`      VARCHAR(500)    NOT NULL,
    `email_type`   VARCHAR(100)    NOT NULL,
    `related_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `status`       ENUM('sent','failed','logged') NOT NULL DEFAULT 'logged',
    `error_message` TEXT            NULL DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_email_logs_to_email` (`to_email`),
    KEY `idx_email_logs_type` (`email_type`),
    KEY `idx_email_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migration 011: hero_banners ───────────────────────────────────────────────
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

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed: 10 SKSL Recovery Modalities ────────────────────────────────────────
INSERT INTO `services`
    (`name`, `slug`, `description`, `price`, `gst_percent`, `duration_minutes`, `capacity`, `image`, `status`)
VALUES
    ('Spa',          'spa',          NULL, 999.00, 18.00, 30, 4, 'images/services/spa.jpg',          'active'),
    ('Sauna',        'sauna',        NULL, 499.00, 18.00, 10, 4, 'images/services/sauna.jpg',        'active'),
    ('Steam',        'steam',        NULL, 299.00, 18.00, 10, 8, 'images/services/steam.jpg',        'active'),
    ('Ice Bath',     'ice-bath',     NULL, 449.00, 18.00, 10, 8, 'images/services/ice-bath.jpg',     'active'),
    ('Hot Bath',     'hot-bath',     NULL, 199.00, 18.00, 10, 4, 'images/services/hot-bath.jpg',     'active'),
    ('Endless Pool', 'endless-pool', NULL, 499.00, 18.00, 30, 2, 'images/services/endless-pool.jpg', 'active'),
    ('Cycle',        'cycle',        NULL, 149.00, 18.00, 15, 1, 'images/services/cycle.jpg',        'active'),
    ('Treadmill',    'treadmill',    NULL, 149.00, 18.00, 15, 1, 'images/services/treadmill.jpg',    'active'),
    ('Walker',       'walker',       NULL, 149.00, 18.00, 15, 1, 'images/services/walker.jpg',       'active'),
    ('Lap Pool',     'lap-pool',     NULL, 499.00, 18.00, 45, 1, 'images/services/lap-pool.jpg',     'active');

-- ── Seed: Initial Admin Account ──────────────────────────────────────────────
-- IMPORTANT: Update email to the actual admin email before going live.
INSERT INTO `admins` (`name`, `email`, `status`)
VALUES ('SKSL Admin', 'admin@sksl.in', 'active')
ON DUPLICATE KEY UPDATE id=id;

-- ── Seed: Default Hero Banner ─────────────────────────────────────────────────
INSERT INTO `hero_banners` (`badge_text`, `headline`, `subheadline`, `image_url`, `cta_text`, `cta_link`, `is_active`)
VALUES (
    'Sports Science & High-Performance Lab',
    'Recover Faster. Recharge Fully. Perform at Your Peak.',
    'Chennai''s premier sports recovery center combining clinical contrast therapy, ice baths, infrared saunas, and aquatic conditioning for marathoners, triathletes, and competitive sports performers.',
    'images/hero-banner.jpg',
    'Reserve Recovery Session',
    '/booking',
    1
) ON DUPLICATE KEY UPDATE id=id;

-- ── Post-import instructions ──────────────────────────────────────────────────
-- Run this after import to update admin email:
-- UPDATE admins SET email = 'your-actual-admin@yourdomain.com' WHERE id = 1;
--
-- To verify:
-- SELECT COUNT(*) AS service_count FROM services;  -- Should be 10
-- SELECT COUNT(*) AS admin_count FROM admins;       -- Should be 1
-- SHOW TABLES;                                       -- Should show 11 tables
