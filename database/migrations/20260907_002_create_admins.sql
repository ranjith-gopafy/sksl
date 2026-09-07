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
