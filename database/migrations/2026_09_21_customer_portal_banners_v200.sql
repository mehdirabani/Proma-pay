CREATE TABLE IF NOT EXISTS `portal_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `placement` varchar(40) NOT NULL DEFAULT 'customer_dashboard',
  `audience_role` varchar(30) NOT NULL DEFAULT 'customer',
  `title` varchar(190) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `eyebrow` varchar(80) DEFAULT NULL,
  `desktop_image_path` varchar(255) DEFAULT NULL,
  `mobile_image_path` varchar(255) DEFAULT NULL,
  `image_alt` varchar(190) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `link_type` varchar(20) NOT NULL DEFAULT 'none',
  `link_target` varchar(500) DEFAULT NULL,
  `open_in_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `tone` varchar(20) NOT NULL DEFAULT 'primary',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_portal_banners_delivery` (`placement`,`audience_role`,`is_active`,`archived_at`,`sort_order`),
  KEY `idx_portal_banners_schedule` (`starts_at`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `portal_banners`
(`placement`, `audience_role`, `title`, `body`, `eyebrow`, `cta_label`, `link_type`, `link_target`, `tone`, `is_active`, `sort_order`)
SELECT 'customer_dashboard', 'customer', 'پرداخت اقساط، ساده و شفاف',
       'مانده، سررسید و مبلغ قابل پرداخت امروز را یکجا ببینید و با اطمینان پرداخت کنید.',
       'پروما پی همراه شما', 'مشاهده اقساط', 'internal', 'installments/panel', 'primary', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM `portal_banners` WHERE `placement` = 'customer_dashboard' AND `archived_at` IS NULL);
