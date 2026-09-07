-- Seed: admin
-- Purpose: Insert initial admin account
-- IMPORTANT: Update the email address before going live.
-- The admin authenticates via OTP only — no password stored.
-- Admin email is also used for notifications (set ADMIN_EMAIL in .env).

INSERT INTO `admins` (`name`, `email`, `status`)
VALUES ('SKSL Admin', 'admin@sksl.in', 'active');

-- NOTE: After seeding, update the email to the actual admin email address.
-- UPDATE admins SET email = 'actual-admin@example.com' WHERE id = 1;
