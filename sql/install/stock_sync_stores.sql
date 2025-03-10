CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_stores` (
  `id_store` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_name` varchar(128) NOT NULL,
  `store_url` varchar(255) NOT NULL,
  `api_key` varchar(128) NOT NULL,
  `sync_type` varchar(32) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `priority` int(10) unsigned NOT NULL DEFAULT 0,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id_store`),
  UNIQUE KEY `store_url` (`store_url`),
  KEY `sync_type` (`sync_type`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
