-- Migration: 20260926_014_email_logs_subject_status
-- Purpose: email_logs becomes the delivery record for every outbound email
--          (audit: "The email_logs table exists and is never written").
-- Tables: email_logs
-- Data impact: None (adds a nullable column, widens an ENUM)
-- Rollback:
--   ALTER TABLE email_logs DROP COLUMN subject;
--   ALTER TABLE email_logs MODIFY status ENUM('sent','failed') NOT NULL DEFAULT 'sent';
-- Notes:
--   'logged' = written to storage/logs/mail.log by the development mail driver
--   (MAIL_DRIVER=log or no SMTP host outside production). Bodies are never stored here.
--   Subjects never contain secrets (the admin OTP was removed from the subject line).

ALTER TABLE `email_logs`
    MODIFY `status` ENUM('sent','failed','logged') NOT NULL DEFAULT 'sent';

ALTER TABLE `email_logs`
    ADD COLUMN `subject` VARCHAR(255) NULL DEFAULT NULL AFTER `email_type`;
