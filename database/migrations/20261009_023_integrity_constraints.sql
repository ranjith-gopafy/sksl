-- Migration: 20261009_023_integrity_constraints
-- Purpose: data-integrity guards that Phase 2 left out.
--   1. Orphan package payments (a payment whose package purchase no longer exists) are removed first;
--      they carry no information and would block the new foreign key.
--   2. Foreign keys for the Phase 2 columns that pointed at other tables without any constraint.
--      Payments RESTRICT (a purchase with a payment can never be deleted away); history columns SET NULL.
--   3. A payment belongs to exactly one thing: a booking or a package purchase, matching its source.
--   4. A session cannot end before it starts.
--   5. Indexes for the availability and housekeeping queries.

-- 1. orphans
DELETE p FROM `payments` p
WHERE p.`customer_package_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `customer_packages` c WHERE c.`id` = p.`customer_package_id`);

-- 2. foreign keys
ALTER TABLE `payments`
    ADD CONSTRAINT `fk_payments_customer_package`
        FOREIGN KEY (`customer_package_id`) REFERENCES `customer_packages` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `bookings`
    ADD CONSTRAINT `fk_bookings_package_credit`
        FOREIGN KEY (`package_credit_id`) REFERENCES `customer_package_credits` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bookings_duration_option`
        FOREIGN KEY (`duration_option_id`) REFERENCES `service_duration_options` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bookings_visit_admin`
        FOREIGN KEY (`visit_marked_by_admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `manual_blocks`
    ADD CONSTRAINT `fk_manual_blocks_admin`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- 3 + 4. row-level checks
ALTER TABLE `payments`
    ADD CONSTRAINT `chk_payments_one_parent` CHECK (
        (`payment_source` = 'booking' AND `booking_id` IS NOT NULL AND `customer_package_id` IS NULL)
        OR (`payment_source` = 'package' AND `customer_package_id` IS NOT NULL AND `booking_id` IS NULL)
    );

ALTER TABLE `bookings`
    ADD CONSTRAINT `chk_bookings_times` CHECK (`end_time` > `start_time`);

ALTER TABLE `booking_holds`
    ADD CONSTRAINT `chk_holds_times` CHECK (`end_time` > `start_time`);

-- 5. indexes
ALTER TABLE `booking_holds`
    ADD KEY `idx_holds_slot_lookup` (`service_id`, `booking_date`, `status`, `expires_at`);

ALTER TABLE `email_logs`
    ADD KEY `idx_email_logs_created_at` (`created_at`);
