-- Migration: 20260926_012_audit_fixes
-- Purpose: Schema changes from the September 2026 source audit
-- Tables: bookings, users, rate_limits
-- Data impact: Backfills empty service_name_snapshot from the live services row (one-time).
-- Rollback:
--   ALTER TABLE bookings DROP COLUMN invoice_number, DROP COLUMN health_declared_at;
--   ALTER TABLE bookings MODIFY booking_reference VARCHAR(20) NOT NULL;
--   ALTER TABLE users DROP COLUMN password_changed_at;
--   DROP TABLE rate_limits;
-- Notes:
--   Runs once; the migration runner records applied files in the `migrations` table.

-- C2: historical bookings that were inserted without a snapshot
UPDATE bookings b
JOIN services s ON s.id = b.service_id
SET b.service_name_snapshot = s.name
WHERE b.service_name_snapshot = '' OR b.service_name_snapshot IS NULL;

-- Give references headroom (SKSL-YYYYMMDD-XXXXXX is exactly 20 chars today)
ALTER TABLE bookings      MODIFY booking_reference VARCHAR(32) NOT NULL;
ALTER TABLE booking_holds MODIFY booking_reference VARCHAR(32) NOT NULL;

-- Issued invoice number is stored once, never recomputed
ALTER TABLE bookings ADD COLUMN invoice_number VARCHAR(40) NULL DEFAULT NULL AFTER payment_status;
ALTER TABLE bookings ADD UNIQUE KEY uq_bookings_invoice_number (invoice_number);

-- Customer's health/terms declaration at time of booking
ALTER TABLE bookings ADD COLUMN health_declared_at DATETIME NULL DEFAULT NULL AFTER invoice_number;

-- Sessions started before this timestamp are invalid (password change / reset)
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL AFTER password_hash;

-- Server-side rate limiting (replaces per-session counters that a new cookie reset)
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `rl_key`       VARCHAR(191)    NOT NULL,
    `attempts`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `window_start` DATETIME        NOT NULL,
    `locked_until` DATETIME        NULL DEFAULT NULL,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rl_key`),
    KEY `idx_rate_limits_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
