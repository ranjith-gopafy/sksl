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
