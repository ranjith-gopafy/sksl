-- Migration: 20261009_022_package_expiry_end_of_day
-- Purpose: a package is valid "until" a date. Earlier purchases were stored with the time of day of the
--   purchase (for example 13:32), so a customer was refused a session late on the last day even though the
--   screen said "valid until <that date>". Normalise paid packages to the end of their last day.

UPDATE `customer_packages`
SET `expires_at` = CONCAT(DATE(`expires_at`), ' 23:59:59')
WHERE `payment_status` = 'paid' AND TIME(`expires_at`) <> '23:59:59';