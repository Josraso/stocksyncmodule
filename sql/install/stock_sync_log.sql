CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_log` (
  `id_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_queue` int(10) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `level` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_queue` (`id_queue`),
  KEY `level` (`level`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
