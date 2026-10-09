-- Migration: 20261009_019_phase2_audit_fixes
-- Purpose: Phase 2 audit fixes.
--   1. bookings.visit_status: the application used 'scheduled', which is not in the enum.
--      Non-strict servers stored '' for such rows; normalise them to 'pending'.
--   2. service_duration_options.price is the ex-GST base, like services.price.
--      Migration 018 first stored the GST-inclusive amounts, so checkout added GST twice.
--      Absolute values keep this migration safe to re-run.

UPDATE `bookings` SET `visit_status` = 'pending' WHERE `visit_status` = '';

UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 846.61  WHERE s.slug = 'massages'     AND o.duration_minutes = 30;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 1270.34 WHERE s.slug = 'massages'     AND o.duration_minutes = 60;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 1524.58 WHERE s.slug = 'massages'     AND o.duration_minutes = 90;
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 507.63  WHERE s.slug IN ('sauna', 'endless-pool');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 381.36  WHERE s.slug = 'steam';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 338.14  WHERE s.slug = 'ice-bath';
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 148.31  WHERE s.slug IN ('cycle', 'walker', 'treadmill');
UPDATE `service_duration_options` o JOIN `services` s ON s.id = o.service_id
SET o.price = 422.88  WHERE s.slug = 'lap-pool';

-- 3. Package purchases are taxable sales and need their own sequential tax invoice number
--    (numbered from the same series as booking invoices).
ALTER TABLE `customer_packages`
    ADD COLUMN `invoice_number` VARCHAR(40) NULL DEFAULT NULL AFTER `purchase_reference`,
    ADD UNIQUE KEY `uq_cust_pkg_invoice` (`invoice_number`);

-- 4. payments.amount holds rupees for bookings; package payments were written in paise.
--    Normalise any existing package rows so webhook amount checks and reports agree.
UPDATE `payments` p JOIN `customer_packages` cp ON cp.id = p.customer_package_id
SET p.amount = cp.total_amount
WHERE p.payment_source = 'package' AND p.amount <> cp.total_amount;

-- 5. Package copy must match what a package actually grants: whole sessions of each modality
--    at that modality's normal length (no per-item minute splits, no uncredited extras).
UPDATE `packages` SET `description` = 'One Infrared Sauna session followed by one Ice Bath session: thermal immersion then cryo contrast.' WHERE `slug` = 'sauna-ice-bath-contrast';
UPDATE `packages` SET `description` = 'One Eucalyptus Steam session followed by one Ice Bath session for a rapid contrast flush.' WHERE `slug` = 'steam-ice-bath-flush';
UPDATE `packages` SET `description` = 'One Massage session, one Steam session and one Ice Bath session for a complete recovery reset.' WHERE `slug` = 'massage-steam-ice-reset';
UPDATE `packages` SET `description` = 'One therapeutic Massage session and one Ice Bath session for muscle recovery and contrast.' WHERE `slug` = 'massage-shower-ice-protocol';
UPDATE `packages` SET `name` = 'Hydrotherapy Circuit', `description` = 'A full aquatic conditioning circuit: one Aqua Cycle, one Aqua Walker and one Aqua Treadmill session.' WHERE `slug` = 'hydrotherapy-circuit-45m';
UPDATE `packages` SET `name` = 'Massage & Ice Bath Recovery' WHERE `slug` = 'massage-shower-ice-protocol';
UPDATE `packages` SET `name` = 'Massage, Steam & Ice Bath Reset' WHERE `slug` = 'massage-steam-ice-reset';
