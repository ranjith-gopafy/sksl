-- ============================================================
-- SKSL — Database Export (GENERATED — do not edit by hand)
-- Sara Kinetic Sports Lab — Online Booking Platform
-- Generated: 2026-10-16 10:01 by database/build-export.php
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

-- ── 20261008_016_phase2_core_schema.sql ───────────────────────────────────
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

-- ── 20261008_017_hold_duration_option.sql ─────────────────────────────────
-- Migration: 20261008_017_hold_duration_option
-- Purpose: Add optional duration_option_id to booking_holds to propagate selected duration option through hold-to-payment lifecycle

ALTER TABLE `booking_holds`
    ADD COLUMN `duration_option_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `service_id`,
    ADD CONSTRAINT `fk_holds_duration_option`
        FOREIGN KEY (`duration_option_id`) REFERENCES `service_duration_options` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 20261008_018_business_updates_and_combos.sql ──────────────────────────
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

-- ── 20261009_019_phase2_audit_fixes.sql ───────────────────────────────────
-- Migration: 20261009_019_phase2_audit_fixes
-- Purpose: Phase 2 audit fixes.
--   1. bookings.visit_status: the application used 'scheduled', which is not in the enum.
--      Non-strict servers stored '' for such rows; normalise them to 'pending'.
--   2. service_duration_options.price is the ex-GST base, like services.price.
--      Migration 018 first stored the GST-inclusive amounts, so checkout added GST twice.
--      Absolute values keep this migration safe to re-run.

UPDATE `bookings` SET `visit_status` = 'pending' WHERE `visit_status` = '';

UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 846.61  WHERE s.slug = 'massages'     AND o.duration_minutes = 30;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 1270.34 WHERE s.slug = 'massages'     AND o.duration_minutes = 60;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 1524.58 WHERE s.slug = 'massages'     AND o.duration_minutes = 90;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 507.63  WHERE s.slug IN ('sauna', 'endless-pool');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 381.36  WHERE s.slug = 'steam';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 338.14  WHERE s.slug = 'ice-bath';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 148.31  WHERE s.slug IN ('cycle', 'walker', 'treadmill');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 422.88  WHERE s.slug = 'lap-pool';

-- 3. Package purchases are taxable sales and need their own sequential tax invoice number
--    (numbered from the same series as booking invoices).
ALTER TABLE `customer_packages`
    ADD COLUMN `invoice_number` VARCHAR(40) NULL DEFAULT NULL AFTER `purchase_reference`,
    ADD UNIQUE KEY `uq_cust_pkg_invoice` (`invoice_number`);

-- 4. payments.amount holds rupees for bookings; package payments were written in paise.
--    Normalise any existing package rows so webhook amount checks and reports agree.
UPDATE `payments` p JOIN `customer_packages` cp ON cp.id = p.customer_package_id
SET p.amount = cp.total_amount
WHERE p.payment_source = 'package' AND p.amount <> cp.total_amount;

-- 5. Package copy must match what a package actually grants: whole sessions of each modality
--    at that modality's normal length (no per-item minute splits, no uncredited extras).
UPDATE `packages` SET `description` = 'One Infrared Sauna session followed by one Ice Bath session: thermal immersion then cryo contrast.' WHERE `slug` = 'sauna-ice-bath-contrast';
UPDATE `packages` SET `description` = 'One Eucalyptus Steam session followed by one Ice Bath session for a rapid contrast flush.' WHERE `slug` = 'steam-ice-bath-flush';
UPDATE `packages` SET `description` = 'One Massage session, one Steam session and one Ice Bath session for a complete recovery reset.' WHERE `slug` = 'massage-steam-ice-reset';
UPDATE `packages` SET `description` = 'One therapeutic Massage session and one Ice Bath session for muscle recovery and contrast.' WHERE `slug` = 'massage-shower-ice-protocol';
UPDATE `packages` SET `name` = 'Hydrotherapy Circuit', `description` = 'A full aquatic conditioning circuit: one Aqua Cycle, one Aqua Walker and one Aqua Treadmill session.' WHERE `slug` = 'hydrotherapy-circuit-45m';
UPDATE `packages` SET `name` = 'Massage & Ice Bath Recovery' WHERE `slug` = 'massage-shower-ice-protocol';
UPDATE `packages` SET `name` = 'Massage, Steam & Ice Bath Reset' WHERE `slug` = 'massage-steam-ice-reset';

-- ── 20261009_020_inclusive_prices_and_package_minutes.sql ─────────────────
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

-- ── 20261009_021_fix_duration_mode.sql ────────────────────────────────────
-- Migration: 20261009_021_fix_duration_mode
-- Purpose: migration 018 wrote duration_mode = 'variable', which is not a value of the
--   ENUM('single','multiple'). Lenient servers stored an empty string; strict servers
--   (most hosting) refused the statement and stopped the migration run.
--   Massages is the one service with several duration options.

UPDATE `services` SET `duration_mode` = 'multiple' WHERE `slug` = 'massages';
UPDATE `services` SET `duration_mode` = 'single'   WHERE `duration_mode` = '' AND `slug` <> 'massages';

-- ── 20261009_022_package_expiry_end_of_day.sql ────────────────────────────
-- Migration: 20261009_022_package_expiry_end_of_day
-- Purpose: a package is valid "until" a date. Earlier purchases were stored with the time of day of the
--   purchase (for example 13:32), so a customer was refused a session late on the last day even though the
--   screen said "valid until <that date>". Normalise paid packages to the end of their last day.

UPDATE `customer_packages`
SET `expires_at` = CONCAT(DATE(`expires_at`), ' 23:59:59')
WHERE `payment_status` = 'paid' AND TIME(`expires_at`) <> '23:59:59';

-- ── 20261009_023_integrity_constraints.sql ────────────────────────────────
-- Migration: 20261009_023_integrity_constraints
-- Purpose: data-integrity guards that Phase 2 left out.
--   1. Orphan package payments (a payment whose package purchase no longer exists) are removed first;
--      they carry no information and would block the new foreign key.
--   2. Foreign keys for the Phase 2 columns that pointed at other tables without any constraint.
--      Payments RESTRICT (a purchase with a payment can never be deleted away); history columns SET NULL.
--   3. A payment belongs to exactly one thing: a booking or a package purchase, matching its source.
--   4. A session cannot end before it starts.
--   5. Indexes for the availability and housekeeping queries.

-- 1. orphans
DELETE p FROM `payments` p
WHERE p.`customer_package_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `customer_packages` c WHERE c.`id` = p.`customer_package_id`);

-- 2. foreign keys
ALTER TABLE `payments`
    ADD CONSTRAINT `fk_payments_customer_package`
        FOREIGN KEY (`customer_package_id`) REFERENCES `customer_packages` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `bookings`
    ADD CONSTRAINT `fk_bookings_package_credit`
        FOREIGN KEY (`package_credit_id`) REFERENCES `customer_package_credits` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bookings_duration_option`
        FOREIGN KEY (`duration_option_id`) REFERENCES `service_duration_options` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bookings_visit_admin`
        FOREIGN KEY (`visit_marked_by_admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `manual_blocks`
    ADD CONSTRAINT `fk_manual_blocks_admin`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- 3 + 4. row-level checks
-- (A "payment has exactly one parent" CHECK on payments is deliberately not added: MariaDB, which Hostinger
--  runs, refuses a CHECK on columns that carry an ON UPDATE CASCADE foreign key. PaymentService always
--  writes exactly one parent.)
ALTER TABLE `bookings`
    ADD CONSTRAINT `chk_bookings_times` CHECK (`end_time` > `start_time`);

ALTER TABLE `booking_holds`
    ADD CONSTRAINT `chk_holds_times` CHECK (`end_time` > `start_time`);

-- 5. indexes
ALTER TABLE `booking_holds`
    ADD KEY `idx_holds_slot_lookup` (`service_id`, `booking_date`, `status`, `expires_at`);

ALTER TABLE `email_logs`
    ADD KEY `idx_email_logs_created_at` (`created_at`);

-- ── 20261009_024_booking_cancellation.sql ─────────────────────────────────
-- Migration: 20261009_024_booking_cancellation
-- Purpose: record who cancelled a booking, when, and what the cancellation charge and the manual refund are.
--   cancelled_at      when the booking was cancelled
--   cancelled_by      'customer' (from My Sessions) or 'admin'
--   cancellation_fee  charge kept by SKSL (rupees, incl. GST), NULL when nothing was paid online
--   refund_amount     amount SKSL must refund manually (rupees), NULL when nothing was paid online

ALTER TABLE `bookings`
    ADD COLUMN `cancelled_at`     DATETIME NULL AFTER `visit_marked_by_admin_id`,
    ADD COLUMN `cancelled_by`     ENUM('customer','admin') NULL AFTER `cancelled_at`,
    ADD COLUMN `cancellation_fee` DECIMAL(10,2) NULL AFTER `cancelled_by`,
    ADD COLUMN `refund_amount`    DECIMAL(10,2) NULL AFTER `cancellation_fee`;

-- ── 20261015_025_offline_payments.sql ─────────────────────────────────────
-- Migration: 20261015_025_offline_payments
-- Purpose: pay-outside-the-website flow. While the online gateway is switched off, a booking or combo
--   package is requested without paying, waits for staff to receive the money (UPI / bank / cash) and
--   is then approved. These columns record the channel, the deadline, how the money arrived and who approved.
--   payment_channel   'online' (Razorpay, the default) or 'offline' (approved by staff)
--   payment_due_at    when an unpaid offline request lapses and releases its slot
--   payment_method    upi / bank_transfer / cash / card / other (set on approval)
--   payment_reference UPI transaction id, bank reference or receipt number (set on approval)
--   payment_received_at / approved_by_admin_id   when and by whom it was approved

ALTER TABLE `bookings`
    ADD COLUMN `payment_channel`      ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER `payment_mode`,
    ADD COLUMN `payment_due_at`       DATETIME NULL AFTER `payment_status`,
    ADD COLUMN `payment_method`       VARCHAR(30) NULL AFTER `payment_due_at`,
    ADD COLUMN `payment_reference`    VARCHAR(100) NULL AFTER `payment_method`,
    ADD COLUMN `payment_received_at`  DATETIME NULL AFTER `payment_reference`,
    ADD COLUMN `approved_by_admin_id` BIGINT(20) UNSIGNED NULL AFTER `payment_received_at`,
    ADD KEY `idx_bookings_offline_due` (`payment_channel`, `booking_status`, `payment_due_at`),
    ADD CONSTRAINT `fk_bookings_approved_by_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `customer_packages`
    ADD COLUMN `payment_channel`      ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER `payment_status`,
    ADD COLUMN `payment_due_at`       DATETIME NULL AFTER `payment_channel`,
    ADD COLUMN `payment_method`       VARCHAR(30) NULL AFTER `payment_due_at`,
    ADD COLUMN `payment_reference`    VARCHAR(100) NULL AFTER `payment_method`,
    ADD COLUMN `approved_by_admin_id` BIGINT(20) UNSIGNED NULL AFTER `payment_reference`,
    ADD CONSTRAINT `fk_customer_packages_approved_by_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed: admin_seed.sql ──────────────────────────────────────────────────
-- Seed: admin
-- Purpose: Insert initial admin account
-- The admin authenticates via OTP only — no password stored.
-- Booking alerts use ADMIN_EMAIL in .env, which can be a different address.

INSERT INTO `admins` (`name`, `email`, `status`)
VALUES ('SKSL Admin', 'info@sarakineticsportslab.com', 'active');

-- ── Seed: services_seed.sql ───────────────────────────────────────────────
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
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261008_016_phase2_core_schema.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261008_017_hold_duration_option.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261008_018_business_updates_and_combos.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_019_phase2_audit_fixes.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_020_inclusive_prices_and_package_minutes.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_021_fix_duration_mode.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_022_package_expiry_end_of_day.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_023_integrity_constraints.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261009_024_booking_cancellation.sql');
INSERT IGNORE INTO `migrations` (`filename`) VALUES ('20261015_025_offline_payments.sql');
