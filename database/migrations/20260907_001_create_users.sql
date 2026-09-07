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
