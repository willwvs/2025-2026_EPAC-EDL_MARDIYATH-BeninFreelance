-- Comptes existants : pas de confirmation e-mail requise
UPDATE `clients` SET `email_verified_at` = COALESCE(`email_verified_at`, `created_at`, NOW()) WHERE `email_verified_at` IS NULL;
UPDATE `prestataires` SET `email_verified_at` = COALESCE(`email_verified_at`, `created_at`, NOW()) WHERE `email_verified_at` IS NULL;
