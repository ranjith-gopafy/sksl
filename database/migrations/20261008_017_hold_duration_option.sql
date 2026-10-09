-- Migration: 20261008_017_hold_duration_option
-- Purpose: Add optional duration_option_id to booking_holds to propagate selected duration option through hold-to-payment lifecycle

ALTER TABLE `booking_holds`
    ADD COLUMN `duration_option_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `service_id`,
    ADD CONSTRAINT `fk_holds_duration_option`
        FOREIGN KEY (`duration_option_id`) REFERENCES `service_duration_options` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
