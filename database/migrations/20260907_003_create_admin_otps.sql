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
