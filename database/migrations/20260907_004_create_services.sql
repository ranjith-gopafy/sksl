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
