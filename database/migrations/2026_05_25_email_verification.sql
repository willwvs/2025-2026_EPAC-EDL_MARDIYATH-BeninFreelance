-- Colonnes e-mail et workflow validation prestataire (MySQL marketplace)
ALTER TABLE `clients`
  ADD COLUMN `email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`,
  ADD COLUMN `email_verify_token` varchar(64) DEFAULT NULL AFTER `email_verified_at`,
  ADD COLUMN `email_verify_expires_at` timestamp NULL DEFAULT NULL AFTER `email_verify_token`;

ALTER TABLE `prestataires`
  ADD COLUMN `email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`,
  ADD COLUMN `email_verify_token` varchar(64) DEFAULT NULL AFTER `email_verified_at`,
  ADD COLUMN `email_verify_expires_at` timestamp NULL DEFAULT NULL AFTER `email_verify_token`,
  ADD COLUMN `ia_decided_at` timestamp NULL DEFAULT NULL AFTER `motif_refus_ia`,
  ADD COLUMN `admin_decided_at` timestamp NULL DEFAULT NULL AFTER `ia_decided_at`,
  ADD COLUMN `verification_email_sent_at` timestamp NULL DEFAULT NULL AFTER `admin_decided_at`;
