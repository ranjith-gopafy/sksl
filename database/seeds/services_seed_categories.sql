-- Seed: put the catalogue modalities into their categories
-- Purpose: on a fresh install the services are inserted by services_seed.sql AFTER the migrations ran,
--   so migration 026 found no modalities to group yet. This file does the same grouping afterwards.
-- Only modalities that have no category yet are touched, so re-running the seeds never moves a
--   modality an administrator has regrouped.

UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'cold-cryo-immersion'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('ice-bath');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'thermal-sauna'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('sauna', 'steam', 'hot-bath');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'hydrotherapy'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('endless-pool', 'cycle', 'treadmill', 'walker', 'lap-pool');
UPDATE `services` s JOIN `service_categories` c ON c.`slug` = 'myotherapy'
    SET s.`category_id` = c.`id` WHERE s.`category` = 'modality' AND s.`category_id` IS NULL AND s.`slug` IN ('massages');
