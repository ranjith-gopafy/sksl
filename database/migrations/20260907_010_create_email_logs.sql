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
