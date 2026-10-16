-- Migration: 20260926_013_auth_hardening
-- Purpose: Record explicit acceptance of the Terms of Service / Privacy Policy at registration
-- Tables: users
-- Data impact: none (existing rows keep NULL = accepted before this field existed)
-- Rollback:
--   ALTER TABLE users DROP COLUMN terms_accepted_at;

ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL AFTER password_changed_at;
