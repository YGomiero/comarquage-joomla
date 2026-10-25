CREATE TABLE IF NOT EXISTS `#__comarquage_feeds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `universe` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `source_url` text NULL,
  `source_type` varchar(16) NOT NULL DEFAULT 'remote',
  `target_version` varchar(24) NOT NULL DEFAULT '3.5',
  `enabled` tinyint NOT NULL DEFAULT 1,
  `last_downloaded` datetime NULL,
  `last_imported` datetime NULL,
  `last_checked` datetime NULL,
  `source_etag` varchar(255) NOT NULL DEFAULT '',
  `source_last_modified` varchar(255) NOT NULL DEFAULT '',
  `source_hash` char(40) NOT NULL DEFAULT '',
  `last_status` varchar(32) NOT NULL DEFAULT '',
  `last_error` text NULL,
  `params` mediumtext NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comarquage_feeds_universe` (`universe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `universe` varchar(32) NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `parent_external_id` varchar(128) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `path` varchar(1024) NOT NULL DEFAULT '',
  `level` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `payload` mediumtext NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comarquage_cat_external` (`universe`,`external_id`),
  KEY `idx_comarquage_cat_alias` (`alias`),
  KEY `idx_comarquage_cat_parent` (`parent_external_id`),
  KEY `idx_comarquage_cat_universe_state_title` (`universe`,`state`,`title`),
  KEY `idx_comarquage_cat_universe_alias_state` (`universe`,`alias`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `universe` varchar(32) NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(500) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `introtext` mediumtext NULL,
  `body` longtext NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `language` char(7) NOT NULL DEFAULT '*',
  `metakey` text NULL,
  `metadesc` text NULL,
  `metadata` text NULL,
  `canonical_url` text NULL,
  `source_version` varchar(24) NOT NULL DEFAULT '',
  `source_hash` char(40) NOT NULL DEFAULT '',
  `payload` longtext NULL,
  `created` datetime NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comarquage_item_external` (`universe`,`external_id`),
  KEY `idx_comarquage_item_alias` (`alias`),
  KEY `idx_comarquage_item_state` (`state`),
  KEY `idx_comarquage_item_universe_state_title` (`universe`,`state`,`title`),
  KEY `idx_comarquage_item_universe_alias_state` (`universe`,`alias`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_item_category_map` (
  `item_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`category_id`),
  KEY `idx_comarquage_map_category` (`category_id`),
  KEY `idx_comarquage_map_category_item` (`category_id`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_metadata` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `value` longtext NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comarquage_meta_scope` (`scope`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_search_index` (
  `item_id` int unsigned NOT NULL,
  `universe` varchar(32) NOT NULL,
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`item_id`),
  FULLTEXT KEY `idx_comarquage_search_fulltext` (`title`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__comarquage_local_organisations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code_insee` varchar(5) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(64) NOT NULL DEFAULT '',
  `url` text NULL,
  `payload` mediumtext NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_comarquage_org_insee` (`code_insee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__comarquage_feeds` (`universe`, `title`, `target_version`, `enabled`) VALUES
('particuliers', 'Particuliers', '3.5', 1),
('associations', 'Associations', '3.5', 1),
('entreprises', 'Entreprises / Professionnels', '3.5', 1);
