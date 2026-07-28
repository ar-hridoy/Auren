-- ============================================================================
-- 05_enable_2fa_for_all.sql
-- Makes two-step verification mandatory for EVERY account.
--
-- Run this once if you already created auren_db with the earlier migration
-- (where two_factor_enabled defaulted to FALSE / opt-in).
--
--   1) turns 2FA ON for all existing users
--   2) changes the column default to TRUE, so every new sign-up gets it too
-- ============================================================================

USE auren_db;

-- 1) Every existing account now requires a code at sign-in.
UPDATE Users SET two_factor_enabled = TRUE;

-- 2) New accounts default to 2FA on.
ALTER TABLE Users
    MODIFY COLUMN two_factor_enabled BOOLEAN NOT NULL DEFAULT TRUE;
