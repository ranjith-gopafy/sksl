-- Seed: admin
-- Purpose: Insert initial admin account
-- The admin authenticates via OTP only — no password stored.
-- Booking alerts use ADMIN_EMAIL in .env, which can be a different address.

INSERT INTO `admins` (`name`, `email`, `status`)
VALUES ('SKSL Admin', 'info@sarakineticsportslab.com', 'active');
