-- ============================================================================
-- 06_profile_features.sql
-- Adds richer profile fields and identity (KYC) verification support.
--
-- New Users columns:
--   * profile_photo      path to an uploaded avatar image (optional)
--   * bio                short "about me" text (optional)
--   * id_document_type   'nid' or 'passport' (required for verification)
--   * id_document_path   path to the uploaded ID image/PDF (required)
--
-- Identity flow: a user uploads a photo of their NID or passport. That marks
-- the account as "pending review" (id_document_path set, is_verified = 0). An
-- admin reviews the document and flips is_verified to 1. ID documents are NOT
-- publicly listable — they are served only to the owner or an admin through a
-- gated script (view_id.php).
--
-- Run AFTER the earlier migrations (01..05).
-- ============================================================================

USE auren_db;

ALTER TABLE Users
    ADD COLUMN profile_photo    VARCHAR(255) NULL DEFAULT NULL AFTER phone,
    ADD COLUMN bio              TEXT NULL DEFAULT NULL AFTER profile_photo,
    ADD COLUMN id_document_type VARCHAR(20) NULL DEFAULT NULL AFTER bio,
    ADD COLUMN id_document_path VARCHAR(255) NULL DEFAULT NULL AFTER id_document_type;
