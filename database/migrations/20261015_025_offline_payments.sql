-- Migration: 20261015_025_offline_payments
-- Purpose: pay-outside-the-website flow. While the online gateway is switched off, a booking or combo
--   package is requested without paying, waits for staff to receive the money (UPI / bank / cash) and
--   is then approved. These columns record the channel, the deadline, how the money arrived and who approved.
--   payment_channel   'online' (Razorpay, the default) or 'offline' (approved by staff)
--   payment_due_at    when an unpaid offline request lapses and releases its slot
--   payment_method    upi / bank_transfer / cash / card / other (set on approval)
--   payment_reference UPI transaction id, bank reference or receipt number (set on approval)
--   payment_received_at / approved_by_admin_id   when and by whom it was approved

ALTER TABLE `bookings`
    ADD COLUMN `payment_channel`      ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER `payment_mode`,
    ADD COLUMN `payment_due_at`       DATETIME NULL AFTER `payment_status`,
    ADD COLUMN `payment_method`       VARCHAR(30) NULL AFTER `payment_due_at`,
    ADD COLUMN `payment_reference`    VARCHAR(100) NULL AFTER `payment_method`,
    ADD COLUMN `payment_received_at`  DATETIME NULL AFTER `payment_reference`,
    ADD COLUMN `approved_by_admin_id` BIGINT(20) UNSIGNED NULL AFTER `payment_received_at`,
    ADD KEY `idx_bookings_offline_due` (`payment_channel`, `booking_status`, `payment_due_at`),
    ADD CONSTRAINT `fk_bookings_approved_by_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `customer_packages`
    ADD COLUMN `payment_channel`      ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER `payment_status`,
    ADD COLUMN `payment_due_at`       DATETIME NULL AFTER `payment_channel`,
    ADD COLUMN `payment_method`       VARCHAR(30) NULL AFTER `payment_due_at`,
    ADD COLUMN `payment_reference`    VARCHAR(100) NULL AFTER `payment_method`,
    ADD COLUMN `approved_by_admin_id` BIGINT(20) UNSIGNED NULL AFTER `payment_reference`,
    ADD CONSTRAINT `fk_customer_packages_approved_by_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
