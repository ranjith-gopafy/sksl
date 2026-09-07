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
