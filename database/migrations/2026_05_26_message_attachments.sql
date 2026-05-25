ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `attachment_path` varchar(255) DEFAULT NULL AFTER `contenu`,
  ADD COLUMN IF NOT EXISTS `attachment_name` varchar(255) DEFAULT NULL AFTER `attachment_path`,
  ADD COLUMN IF NOT EXISTS `attachment_mime` varchar(120) DEFAULT NULL AFTER `attachment_name`,
  ADD COLUMN IF NOT EXISTS `attachment_size` int unsigned DEFAULT NULL AFTER `attachment_mime`;
