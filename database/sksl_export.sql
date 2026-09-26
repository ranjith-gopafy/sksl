-- ============================================================
-- SKSL — Database Export (GENERATED — do not edit by hand)
-- Sara Kinetic Sports Lab — Online Booking Platform
-- Generated: 2026-09-26 15:15 by database/build-export.php
--
-- Contains every file in database/migrations/ and database/seeds/, in order,
-- plus the `migrations` ledger so `php database/migrate.php` knows they ran.
--
-- HOW TO IMPORT:
--   phpMyAdmin: Import → Select this file → Go
--   CLI: mysql -u<user> -p <database> < sksl_export.sql
--
-- AFTER IMPORT:
--   1. UPDATE admins SET email='your-admin@yourdomain.com' WHERE id=1;
--   2. Fill BUSINESS_* and INVOICE_* values in .env (legal identity for invoices)
--   3. Update hero banner slides via /admin/banner
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS `migrations` (
    `filename`   VARCHAR(191) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_001_create_users.sql ─────────────────────────────────────────
-- Migration: 20260907_001_create_users
-- Purpose: Customer accounts table
-- Tables: users
-- Data impact: None (new table)
-- Rollback: DROP TABLE users;

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

-- ── 20260907_002_create_admins.sql ────────────────────────────────────────
-- Migration: 20260907_002_create_admins
-- Purpose: Admin accounts table (no password — authentication via OTP)
-- Tables: admins
-- Data impact: None (new table)
-- Rollback: DROP TABLE admins;

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

-- ── 20260907_003_create_admin_otps.sql ────────────────────────────────────
-- Migration: 20260907_003_create_admin_otps
-- Purpose: Secure 6-digit OTP records for admin authentication
-- Tables: admin_otps
-- Data impact: None (new table)
-- Rollback: DROP TABLE admin_otps;
-- Notes:
--   otp_hash stores hash of OTP — never the plain OTP value.
--   attempts tracks incorrect entries; rate-limiting enforced at app level.
--   used_at is NULL until the OTP is consumed; once set, OTP is invalid.

CREATE TABLE IF NOT EXISTS `admin_otps` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   BIGINT UNSIGNED NOT NULL,
    `otp_hash`   VARCHAR(255)    NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    `used_at`    DATETIME        NULL DEFAULT NULL,
    `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_admin_otps_admin_id` (`admin_id`),
    KEY `idx_admin_otps_expires_at` (`expires_at`),

    CONSTRAINT `fk_admin_otps_admin_id`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_004_create_services.sql ──────────────────────────────────────
-- Migration: 20260907_004_create_services
-- Purpose: Facility services / offerings
-- Tables: services
-- Data impact: None (new table)
-- Rollback: DROP TABLE services;
-- Notes:
--   gst_percent stored per-service for historical snapshot accuracy.
--   V1 GST is fixed at 18% — admin UI does NOT expose a GST edit field.
--   image stores relative path to uploaded file (or NULL if no image).
--   Deactivating a service must NOT delete historical bookings.

CREATE TABLE IF NOT EXISTS `services` (
    `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150)     NOT NULL,
    `slug`             VARCHAR(150)     NOT NULL,
    `description`      TEXT             NULL DEFAULT NULL,
    `price`            DECIMAL(10,2)    NOT NULL,
    `gst_percent`      DECIMAL(5,2)     NOT NULL DEFAULT 18.00,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `capacity`         SMALLINT UNSIGNED NOT NULL,
    `image`            VARCHAR(500)     NULL DEFAULT NULL,
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_services_slug` (`slug`),
    KEY `idx_services_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_005_create_closed_dates.sql ──────────────────────────────────
-- Migration: 20260907_005_create_closed_dates
-- Purpose: Full-facility date closures (applies to ALL services)
-- Tables: closed_dates
-- Data impact: None (new table)
-- Rollback: DROP TABLE closed_dates;

CREATE TABLE IF NOT EXISTS `closed_dates` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `closed_date` DATE            NOT NULL,
    `reason`      VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_closed_dates_date` (`closed_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_006_create_bookings.sql ──────────────────────────────────────
-- Migration: 20260907_006_create_bookings
-- Purpose: Authoritative booking records with price/service snapshot
-- Tables: bookings
-- Data impact: None (new table)
-- Rollback: DROP TABLE bookings;
-- Notes:
--   All service/price fields are SNAPSHOTS taken at booking time.
--   Historical records must NOT change when services are later edited.
--   ON DELETE RESTRICT on user_id and service_id prevents accidental
--   deletion of users/services that have booking history.
--   convenience_fee defaults 0.00 — pending client confirmation.
--   booking_reference format: SKSL-XXXXXXXX (generated server-side).

CREATE TABLE IF NOT EXISTS `bookings` (
    `id`                       BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `booking_reference`        VARCHAR(20)      NOT NULL,
    `user_id`                  BIGINT UNSIGNED  NOT NULL,
    `service_id`               BIGINT UNSIGNED  NOT NULL,
    `booking_date`             DATE             NOT NULL,
    `start_time`               TIME             NOT NULL,
    `end_time`                 TIME             NOT NULL,

    -- Snapshot fields — frozen at booking time
    `service_name_snapshot`    VARCHAR(150)     NOT NULL,
    `service_duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `checkin_buffer_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `checkout_buffer_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `base_amount`              DECIMAL(10,2)    NOT NULL,
    `gst_percent`              DECIMAL(5,2)     NOT NULL,
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

-- ── 20260907_007_create_booking_holds.sql ─────────────────────────────────
-- Migration: 20260907_007_create_booking_holds
-- Purpose: Temporary 10-minute reservation holds during payment
-- Tables: booking_holds
-- Data impact: None (new table)
-- Rollback: DROP TABLE booking_holds;
-- Notes:
--   expires_at is the AUTHORITATIVE expiry — availability checks use this
--   directly. Cron job cleanup is maintenance only, not the source of truth.
--   A hold is "active" only if status = 'active' AND expires_at > NOW().
--   Overlap check must include: confirmed bookings + active non-expired holds.

CREATE TABLE IF NOT EXISTS `booking_holds` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(20)     NOT NULL,
    `user_id`           BIGINT UNSIGNED NOT NULL,
    `service_id`        BIGINT UNSIGNED NOT NULL,
    `booking_date`      DATE            NOT NULL,
    `start_time`        TIME            NOT NULL,
    `end_time`          TIME            NOT NULL,
    `expires_at`        DATETIME        NOT NULL,
    `status`            ENUM('active','released','expired') NOT NULL DEFAULT 'active',
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_holds_service_date` (`service_id`, `booking_date`),
    KEY `idx_holds_expires_at` (`expires_at`),
    KEY `idx_holds_status` (`status`),
    KEY `idx_holds_booking_ref` (`booking_reference`),
    KEY `idx_holds_user_id` (`user_id`),

    CONSTRAINT `fk_holds_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_holds_service_id`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_008_create_payments.sql ──────────────────────────────────────
-- Migration: 20260907_008_create_payments
-- Purpose: Razorpay payment records linked to bookings
-- Tables: payments
-- Data impact: None (new table)
-- Rollback: DROP TABLE payments;
-- Notes:
--   razorpay_order_id is UNIQUE — prevents duplicate payment records.
--   razorpay_payment_id is UNIQUE NULL — set only after payment attempt.
--   gateway_response stores limited non-sensitive gateway data for debugging.
--   Do NOT store raw webhook payloads that may contain sensitive card data.
--   Idempotency: before any state update, check current status first.

CREATE TABLE IF NOT EXISTS `payments` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`           BIGINT UNSIGNED NOT NULL,
    `razorpay_order_id`    VARCHAR(100)    NOT NULL,
    `razorpay_payment_id`  VARCHAR(100)    NULL DEFAULT NULL,
    `razorpay_signature`   VARCHAR(255)    NULL DEFAULT NULL,
    `amount`               DECIMAL(10,2)   NOT NULL,
    `currency`             VARCHAR(10)     NOT NULL DEFAULT 'INR',
    `status`               ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `gateway_response`     TEXT            NULL DEFAULT NULL,
    `paid_at`              DATETIME        NULL DEFAULT NULL,
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_order_id` (`razorpay_order_id`),
    UNIQUE KEY `uq_payments_payment_id` (`razorpay_payment_id`),
    KEY `idx_payments_booking_id` (`booking_id`),
    KEY `idx_payments_status` (`status`),

    CONSTRAINT `fk_payments_booking_id`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_009_create_password_resets.sql ───────────────────────────────
-- Migration: 20260907_009_create_password_resets
-- Purpose: Secure single-use password reset tokens for customers
-- Tables: password_resets
-- Data impact: None (new table)
-- Rollback: DROP TABLE password_resets;
-- Notes:
--   token_hash stores hash of token — never the plain token.
--   used_at is NULL until token is consumed; once set, token is invalid.
--   Application must also check expires_at before allowing reset.

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255)    NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    `used_at`    DATETIME        NULL DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_password_resets_user_id` (`user_id`),
    KEY `idx_password_resets_expires_at` (`expires_at`),

    CONSTRAINT `fk_password_resets_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_010_create_email_logs.sql ────────────────────────────────────
-- Migration: 20260907_010_create_email_logs
-- Purpose: Track email delivery attempts for operational visibility
-- Tables: email_logs
-- Data impact: None (new table)
-- Rollback: DROP TABLE email_logs;
-- Notes:
--   booking_id is nullable — some emails (e.g. admin alerts) may not
--   be tied to a specific booking.
--   Do NOT store SMTP credentials or email body content here.
--   Email failure must NOT prevent a successful payment/booking from completing.

CREATE TABLE IF NOT EXISTS `email_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `recipient`     VARCHAR(255)    NOT NULL,
    `email_type`    VARCHAR(50)     NOT NULL,
    `status`        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    `error_message` TEXT            NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_email_logs_booking_id` (`booking_id`),
    KEY `idx_email_logs_status` (`status`),
    KEY `idx_email_logs_email_type` (`email_type`),

    CONSTRAINT `fk_email_logs_booking_id`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260907_011_create_hero_banners_and_update_service_images.sql ────────
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

-- ── 20260926_012_audit_fixes.sql ──────────────────────────────────────────
-- Migration: 20260926_012_audit_fixes
-- Purpose: Schema changes from the September 2026 source audit
-- Tables: bookings, users, rate_limits
-- Data impact: Backfills empty service_name_snapshot from the live services row (one-time).
-- Rollback:
--   ALTER TABLE bookings DROP COLUMN invoice_number, DROP COLUMN health_declared_at;
--   ALTER TABLE bookings MODIFY booking_reference VARCHAR(20) NOT NULL;
--   ALTER TABLE users DROP COLUMN password_changed_at;
--   DROP TABLE rate_limits;
-- Notes:
--   Runs once; the migration runner records applied files in the `migrations` table.

-- C2: historical bookings that were inserted without a snapshot
UPDATE bookings b
JOIN services s ON s.id = b.service_id
SET b.service_name_snapshot = s.name
WHERE b.service_name_snapshot = '' OR b.service_name_snapshot IS NULL;

-- Give references headroom (SKSL-YYYYMMDD-XXXXXX is exactly 20 chars today)
ALTER TABLE bookings      MODIFY booking_reference VARCHAR(32) NOT NULL;
ALTER TABLE booking_holds MODIFY booking_reference VARCHAR(32) NOT NULL;

-- Issued invoice number is stored once, never recomputed
ALTER TABLE bookings ADD COLUMN invoice_number VARCHAR(40) NULL DEFAULT NULL AFTER payment_status;
ALTER TABLE bookings ADD UNIQUE KEY uq_bookings_invoice_number (invoice_number);

-- Customer's health/terms declaration at time of booking
ALTER TABLE bookings ADD COLUMN health_declared_at DATETIME NULL DEFAULT NULL AFTER invoice_number;

-- Sessions started before this timestamp are invalid (password change / reset)
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL AFTER password_hash;

-- Server-side rate limiting (replaces per-session counters that a new cookie reset)
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `rl_key`       VARCHAR(191)    NOT NULL,
    `attempts`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `window_start` DATETIME        NOT NULL,
    `locked_until` DATETIME        NULL DEFAULT NULL,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rl_key`),
    KEY `idx_rate_limits_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 20260926_013_auth_hardening.sql ───────────────────────────────────────
-- Migration: 20260926_013_auth_hardening
-- Purpose: Record explicit acceptance of the Terms of Service / Privacy Policy at registration
-- Tables: users
-- Data impact: none (existing rows keep NULL = accepted before this field existed)
-- Rollback:
--   ALTER TABLE users DROP COLUMN terms_accepted_at;

ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL AFTER password_changed_at;

-- ── 20260926_014_email_logs_subject_status.sql ────────────────────────────
-- Migration: 20260926_014_email_logs_subject_status
-- Purpose: email_logs becomes the delivery record for every outbound email
--          (audit: "The email_logs table exists and is never written").
-- Tables: email_logs
-- Data impact: None (adds a nullable column, widens an ENUM)
-- Rollback:
--   ALTER TABLE email_logs DROP COLUMN subject;
--   ALTER TABLE email_logs MODIFY status ENUM('sent','failed') NOT NULL DEFAULT 'sent';
-- Notes:
--   'logged' = written to storage/logs/mail.log by the development mail driver
--   (MAIL_DRIVER=log or no SMTP host outside production). Bodies are never stored here.
--   Subjects never contain secrets (the admin OTP was removed from the subject line).

ALTER TABLE `email_logs`
    MODIFY `status` ENUM('sent','failed','logged') NOT NULL DEFAULT 'sent';

ALTER TABLE `email_logs`
    ADD COLUMN `subject` VARCHAR(255) NULL DEFAULT NULL AFTER `email_type`;

-- ── 20260926_015_hold_health_declaration.sql ──────────────────────────────
-- Migration: 20260926_015_hold_health_declaration
-- Purpose: Persist the customer's "Terms & Health Declaration" acceptance.
--          The checkbox on the booking page was previously UI-only (audit, Low:
--          "health declaration is never stored"). The hold endpoint now requires
--          health_declared=1 and records the time on the hold; PaymentService
--          copies it to bookings.health_declared_at (column added in 012) when
--          the booking row is created.
-- Tables: booking_holds
-- Data impact: None (nullable column; existing holds stay NULL)
-- Rollback:
--   ALTER TABLE booking_holds DROP COLUMN health_declared_at;

ALTER TABLE `booking_holds`
    ADD COLUMN `health_declared_at` DATETIME NULL DEFAULT NULL AFTER `expires_at`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed: admin_seed.sql ──────────────────────────────────────────────────
-- Seed: admin
-- Purpose: Insert initial admin account
-- IMPORTANT: Update the email address before going live.
-- The admin authenticates via OTP only — no password stored.
-- Admin email is also used for notifications (set ADMIN_EMAIL in .env).

INSERT INTO `admins` (`name`, `email`, `status`)
VALUES ('SKSL Admin', 'admin@sksl.in', 'active');

-- NOTE: After seeding, update the email to the actual admin email address.
-- UPDATE admins SET email = 'actual-admin@example.com' WHERE id = 1;

-- ── Seed: services_seed.sql ───────────────────────────────────────────────
-- Seed: services
-- Purpose: Insert the 10 approved initial SKSL services
-- Per approved quotation and docs/00_PROJECT_OVERVIEW.md
-- GST fixed at 18% for all V1 services
-- Run AFTER migrations (services table must exist)

INSERT INTO `services`
    (`name`, `slug`, `description`, `price`, `gst_percent`, `duration_minutes`, `capacity`, `image`, `status`)
VALUES
    ('Spa',          'spa',          NULL, 999.00, 18.00, 30, 4, NULL, 'active'),
    ('Sauna',        'sauna',        NULL, 499.00, 18.00, 10, 4, NULL, 'active'),
    ('Steam',        'steam',        NULL, 299.00, 18.00, 10, 8, NULL, 'active'),
    ('Ice Bath',     'ice-bath',     NULL, 449.00, 18.00, 10, 8, NULL, 'active'),
    ('Hot Bath',     'hot-bath',     NULL, 199.00, 18.00, 10, 4, NULL, 'active'),
    ('Endless Pool', 'endless-pool', NULL, 499.00, 18.00, 30, 2, NULL, 'active'),
    ('Cycle',        'cycle',        NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Treadmill',    'treadmill',    NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Walker',       'walker',       NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Lap Pool',     'lap-pool',     NULL, 499.00, 18.00, 45, 1, NULL, 'active');

-- ── Migration ledger ────────────────────────────────────────────────────────
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_001_create_users.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_002_create_admins.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_003_create_admin_otps.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_004_create_services.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_005_create_closed_dates.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_006_create_bookings.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_007_create_booking_holds.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_008_create_payments.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_009_create_password_resets.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_010_create_email_logs.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260907_011_create_hero_banners_and_update_service_images.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260926_012_audit_fixes.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260926_013_auth_hardening.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260926_014_email_logs_subject_status.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20260926_015_hold_health_declaration.sql');
