-- Migration: 20261009_024_booking_cancellation
-- Purpose: record who cancelled a booking, when, and what the cancellation charge and the manual refund are.
--   cancelled_at      when the booking was cancelled
--   cancelled_by      'customer' (from My Sessions) or 'admin'
--   cancellation_fee  charge kept by SKSL (rupees, incl. GST), NULL when nothing was paid online
--   refund_amount     amount SKSL must refund manually (rupees), NULL when nothing was paid online

ALTER TABLE `bookings`
    ADD COLUMN `cancelled_at`     DATETIME NULL AFTER `visit_marked_by_admin_id`,
    ADD COLUMN `cancelled_by`     ENUM('customer','admin') NULL AFTER `cancelled_at`,
    ADD COLUMN `cancellation_fee` DECIMAL(10,2) NULL AFTER `cancelled_by`,
    ADD COLUMN `refund_amount`    DECIMAL(10,2) NULL AFTER `cancellation_fee`;
