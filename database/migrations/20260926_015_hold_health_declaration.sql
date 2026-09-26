-- Migration: 20260926_015_hold_health_declaration
-- Purpose: Persist the customer's "Terms & Health Declaration" acceptance.
--          The checkbox on the booking page was previously UI-only (audit, Low:
--          "health declaration is never stored"). The hold endpoint now requires
--          health_declared=1 and records the time on the hold; PaymentService
--          copies it to bookings.health_declared_at (column added in 012) when
--          the booking row is created.
-- Tables: booking_holds
-- Data impact: None (nullable column; existing holds stay NULL)
-- Rollback:
--   ALTER TABLE booking_holds DROP COLUMN health_declared_at;

ALTER TABLE `booking_holds`
    ADD COLUMN `health_declared_at` DATETIME NULL DEFAULT NULL AFTER `expires_at`;
