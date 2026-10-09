-- Migration: 20261009_021_fix_duration_mode
-- Purpose: migration 018 wrote duration_mode = 'variable', which is not a value of the
--   ENUM('single','multiple'). Lenient servers stored an empty string; strict servers
--   (most hosting) refused the statement and stopped the migration run.
--   Massages is the one service with several duration options.

UPDATE `services` SET `duration_mode` = 'multiple' WHERE `slug` = 'massages';
UPDATE `services` SET `duration_mode` = 'single'   WHERE `duration_mode` = '' AND `slug` <> 'massages';
