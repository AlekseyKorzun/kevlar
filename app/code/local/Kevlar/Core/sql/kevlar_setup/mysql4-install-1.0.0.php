<?php
$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS `{$installer->getTable('kevlar_queue')}` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created` timestamp NOT NULL,
  `url` TEXT NOT NULL,
  `type` enum('akamai','varnish','cloudflare') NOT NULL,
  `eta` timestamp NULL,
  `attempt` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `is_pending` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `type` (`type`),
  KEY `is_pending` (`is_pending`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
");

$installer->endSetup();
