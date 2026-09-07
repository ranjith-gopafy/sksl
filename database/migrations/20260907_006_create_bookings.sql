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
