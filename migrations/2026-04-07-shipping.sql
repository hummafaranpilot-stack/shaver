-- ================================================================
-- MIGRATION: Per-domain shipping configuration
-- Date: 2026-04-07
-- Purpose: Powers the country selector + dynamic shipping override on
--          opted-in pages via the t.js tracker. Only domains with
--          shipping_enabled=1 receive the shipping payload.
-- ================================================================

ALTER TABLE `domains`
  ADD COLUMN `shipping_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

CREATE TABLE IF NOT EXISTS `domain_shipping_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_id` INT NOT NULL,
  `country_code` CHAR(2) NOT NULL COMMENT 'ISO-3166 alpha-2, e.g. US, GB',
  `country_name` VARCHAR(80) NOT NULL COMMENT 'Display label, e.g. United States',
  `ship_1_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0 COMMENT '0 = FREE Shipping',
  `ship_2_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
  `ship_3_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
  `ship_6_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0 COMMENT '6+1 bottle card',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_domain_country` (`domain_id`, `country_code`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
