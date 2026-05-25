-- À exécuter sur db_marketplace si la colonne n'existe pas encore
ALTER TABLE `services`
  ADD COLUMN `photo_path` varchar(255) DEFAULT NULL AFTER `delai_execution`;
