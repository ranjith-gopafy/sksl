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
