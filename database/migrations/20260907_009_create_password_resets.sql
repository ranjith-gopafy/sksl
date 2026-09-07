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
