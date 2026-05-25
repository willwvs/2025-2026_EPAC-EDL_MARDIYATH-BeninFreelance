-- Messagerie bidirectionnelle : qui a envoyé le message
ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `expediteur` varchar(20) NOT NULL DEFAULT 'client' AFTER `service_id`;

UPDATE `messages` SET `expediteur` = 'client' WHERE `expediteur` IS NULL OR `expediteur` = '';
