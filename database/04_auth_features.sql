-- ============================================================================
-- 04_auth_features.sql
-- Adds password-reset and two-step-verification (2FA) support.
--
-- Run AFTER 01_schema.sql (and optionally 02/03). This is kept as a separate
-- migration so the original frozen schema file stays untouched.
--
-- Adds:
--   * Users.two_factor_enabled  (2FA flag — defaults to ON for every account)
--   * Password_Resets           (one-time, expiring reset tokens)
--   * Two_Factor_Codes          (one-time, expiring login OTP codes)
--
-- Both token tables store only a SHA-256 HASH of the secret, never the raw
-- value — so even someone reading the database cannot use a pending token.
-- Each row carries an expiry and a "used_at" so a token works exactly once.
-- ============================================================================

USE auren_db;

-- --- Per-user 2FA opt-in flag -------------------------------------------------
-- 2FA is ON by default for every account (new sign-ups inherit this).
ALTER TABLE Users
    ADD COLUMN two_factor_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER is_verified;

-- --- Password reset tokens -----------------------------------------------------
CREATE TABLE IF NOT EXISTS Password_Resets (
    reset_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  CHAR(64) NOT NULL,          -- sha256(raw token), hex
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES Users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_reset_token UNIQUE (token_hash)
);
CREATE INDEX idx_reset_user ON Password_Resets(user_id);

-- --- Two-factor login codes ---------------------------------------------------
CREATE TABLE IF NOT EXISTS Two_Factor_Codes (
    code_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    code_hash   CHAR(64) NOT NULL,          -- sha256(6-digit code), hex
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id)
        REFERENCES Users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX idx_2fa_user ON Two_Factor_Codes(user_id);
