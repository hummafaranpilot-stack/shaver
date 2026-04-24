-- ================================================================
-- MULTI-TENANT SHAVING SYSTEM - DATABASE SETUP
-- Deploy to: bg.climaofficial.com MySQL database
-- Version: 1.0
-- ================================================================

-- ----------------------------------------------------------------
-- Table: domains
-- Stores registered domains with their BuyGoods / Digistore24 configuration
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_key` VARCHAR(64) NOT NULL UNIQUE COMMENT 'URL-safe identifier used in check.php?d=KEY',
  `domain_url` VARCHAR(500) NOT NULL COMMENT 'Full URL pattern e.g. bg.climaofficial.com/heater/',
  `label` VARCHAR(255) NOT NULL COMMENT 'Display name e.g. ClimaHeater EN',
  `bg_account_id` VARCHAR(20) DEFAULT NULL COMMENT 'Extracted: ?a=12105',
  `bg_product_codes` VARCHAR(500) DEFAULT NULL COMMENT 'Extracted: &product=hea2,hea3,hea6',
  `bg_conversion_token` VARCHAR(100) DEFAULT NULL COMMENT 'Extracted: &t=d357a9...',
  `bg_tracking_script` TEXT NOT NULL COMMENT 'Full BG tracking script block as pasted by user',
  `bg_iframe_script` TEXT DEFAULT NULL COMMENT 'Full BG conversion iframe script block',
  `platform` ENUM('buygoods','digistore24') NOT NULL DEFAULT 'buygoods' COMMENT 'Affiliate network platform',
  `ds24_product_id` VARCHAR(20) DEFAULT NULL COMMENT 'Digistore24 product ID from digistorePromocode()',
  `status` ENUM('active', 'paused', 'deleted') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_domain_key` (`domain_key`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: shaving_sessions
-- Active and stopped shaving sessions, linked to domain_id
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shaving_sessions` (
  `id` VARCHAR(64) PRIMARY KEY COMMENT 'Unique session ID',
  `domain_id` INT NOT NULL COMMENT 'FK to domains.id',
  `aff_id` VARCHAR(100) NOT NULL COMMENT 'Affiliate ID to shave',
  `sub_id` VARCHAR(100) DEFAULT NULL COMMENT 'Optional sub-affiliate filter',
  `mode` ENUM('remove', 'replace') DEFAULT 'remove',
  `replace_aff_id` VARCHAR(100) DEFAULT NULL,
  `replace_sub_id` VARCHAR(100) DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stop_time` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_domain_active` (`domain_id`, `active`),
  KEY `idx_aff_id` (`aff_id`),
  KEY `idx_active` (`active`),
  KEY `idx_domain_aff_active` (`domain_id`, `aff_id`, `active`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: shaving_history
-- Archived sessions after stopping
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shaving_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `domain_id` INT NOT NULL,
  `aff_id` VARCHAR(100) NOT NULL,
  `sub_id` VARCHAR(100) DEFAULT NULL,
  `mode` ENUM('remove', 'replace') DEFAULT 'remove',
  `replace_aff_id` VARCHAR(100) DEFAULT NULL,
  `replace_sub_id` VARCHAR(100) DEFAULT NULL,
  `start_time` DATETIME NOT NULL,
  `stop_time` DATETIME NOT NULL,
  `total_visits` INT DEFAULT 0,
  `total_clicks` INT DEFAULT 0,
  `duration` INT DEFAULT 0 COMMENT 'Duration in seconds',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_domain_id` (`domain_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_stop_time` (`stop_time`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: shaving_tracking
-- Per-event counters (visit/click) for active sessions
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shaving_tracking` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `domain_id` INT NOT NULL,
  `aff_id` VARCHAR(100) DEFAULT NULL,
  `sub_id` VARCHAR(100) DEFAULT NULL,
  `event_type` ENUM('visit', 'click') NOT NULL,
  `page` TEXT DEFAULT NULL,
  `referrer` TEXT DEFAULT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_session_event` (`session_id`, `event_type`),
  KEY `idx_domain_id` (`domain_id`),
  KEY `idx_timestamp` (`timestamp`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: affiliate_traffic
-- ALL visitor traffic (shaved and not shaved), per-domain
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `affiliate_traffic` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_id` INT NOT NULL,
  `aff_id` VARCHAR(100) DEFAULT NULL,
  `sub_id` VARCHAR(100) DEFAULT NULL,
  `page_url` TEXT DEFAULT NULL,
  `page_type` VARCHAR(20) NOT NULL DEFAULT 'landing' COMMENT 'landing, upsell, or thankyou',
  `sessid2` VARCHAR(100) DEFAULT NULL COMMENT 'BuyGoods session cookie for funnel linking',
  `matched_order_id` VARCHAR(20) DEFAULT NULL COMMENT 'Matched order ID from orders table',
  `referrer` TEXT DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `browser` VARCHAR(100) DEFAULT NULL,
  `device` VARCHAR(50) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `country_code` VARCHAR(10) DEFAULT NULL,
  `was_shaved` TINYINT(1) DEFAULT 0,
  `shaving_session_id` VARCHAR(64) DEFAULT NULL,
  `session_uuid` VARCHAR(100) DEFAULT NULL,
  `screen_width` INT DEFAULT NULL,
  `screen_height` INT DEFAULT NULL,
  `viewport_width` INT DEFAULT NULL,
  `viewport_height` INT DEFAULT NULL,
  `session_duration` INT DEFAULT NULL,
  `max_scroll_depth` INT DEFAULT NULL,
  `total_clicks` INT DEFAULT 0,
  `reached_checkout` TINYINT(1) DEFAULT 0,
  `checkout_url` TEXT DEFAULT NULL,
  `time_to_first_click` INT DEFAULT NULL,
  `time_to_checkout` INT DEFAULT NULL,
  `page_load_time` INT DEFAULT NULL,
  `bounce` TINYINT(1) DEFAULT 0,
  `is_bot` TINYINT(1) DEFAULT 0 COMMENT 'Client-side bot detection flag',
  `bot_flags` VARCHAR(255) DEFAULT NULL COMMENT 'CSV: webdriver,no_plugins,no_languages,missing_chrome,headless_chrome,phantomjs',
  `is_iframe` TINYINT(1) DEFAULT 0 COMMENT 'Page loaded inside an iframe',
  `has_adblock` TINYINT(1) DEFAULT NULL COMMENT 'NULL=not checked, 0=no adblock, 1=has adblock',
  `js_error_count` INT DEFAULT 0 COMMENT 'Number of JS errors during session',
  `js_errors` TEXT DEFAULT NULL COMMENT 'JSON array of first 5 error messages',
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_domain_timestamp` (`domain_id`, `timestamp`),
  KEY `idx_aff_id` (`aff_id`),
  KEY `idx_was_shaved` (`was_shaved`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_domain_shaved_time` (`domain_id`, `was_shaved`, `timestamp`),
  KEY `idx_page_type` (`page_type`),
  KEY `idx_sessid2` (`sessid2`),
  KEY `idx_matched_order` (`matched_order_id`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: user_behavior_events
-- Detailed granular behavior events
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_behavior_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `traffic_id` INT NOT NULL COMMENT 'FK to affiliate_traffic.id',
  `domain_id` INT NOT NULL,
  `session_uuid` VARCHAR(100) DEFAULT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `event_data` JSON DEFAULT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_traffic_id` (`traffic_id`),
  KEY `idx_domain_event` (`domain_id`, `event_type`),
  KEY `idx_session_uuid` (`session_uuid`),
  KEY `idx_timestamp` (`timestamp`),
  FOREIGN KEY (`traffic_id`) REFERENCES `affiliate_traffic`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- MIGRATION: Add Digistore24 support to existing databases
-- Uncomment and run these if tables already exist
-- ================================================================
-- ALTER TABLE `domains`
--   ADD COLUMN `platform` ENUM('buygoods','digistore24') NOT NULL DEFAULT 'buygoods'
--     COMMENT 'Affiliate network platform' AFTER `bg_iframe_script`,
--   ADD COLUMN `ds24_product_id` VARCHAR(20) DEFAULT NULL
--     COMMENT 'Digistore24 product ID from digistorePromocode()' AFTER `platform`,
--   ADD KEY `idx_platform` (`platform`);

-- ================================================================
-- MIGRATION: Add IPQS fraud detection columns
-- Run these if tables already exist
-- ================================================================
-- ALTER TABLE `affiliate_traffic`
--   ADD COLUMN `fraud_score` SMALLINT DEFAULT NULL
--     COMMENT 'IPQS fraud score 0-100, NULL = not checked' AFTER `time_to_checkout`,
--   ADD COLUMN `fraud_risk_level` VARCHAR(20) DEFAULT NULL
--     COMMENT 'low/suspicious/risky/high' AFTER `fraud_score`,
--   ADD COLUMN `fraud_flags` VARCHAR(200) DEFAULT NULL
--     COMMENT 'CSV flags: vpn,proxy,tor,bot,recent_abuse' AFTER `fraud_risk_level`;
--
-- CREATE TABLE IF NOT EXISTS `ipqs_usage` (
--   `date` DATE PRIMARY KEY,
--   `calls_made` INT DEFAULT 0
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- MIGRATION: Per-domain affiliate names
-- ================================================================
-- CREATE TABLE IF NOT EXISTS `affiliate_names` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `domain_id` INT NOT NULL,
--   `aff_id` VARCHAR(50) NOT NULL,
--   `name` VARCHAR(255) NOT NULL,
--   UNIQUE KEY `idx_domain_aff` (`domain_id`, `aff_id`),
--   FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- Table: orders
-- Uploaded BuyGoods order data, keyed by domain_id + order_id
-- ----------------------------------------------------------------
-- CREATE TABLE IF NOT EXISTS `orders` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `domain_id` INT NOT NULL,
--   `order_id` VARCHAR(20) NOT NULL,
--   `date_created` DATETIME DEFAULT NULL,
--   `status` VARCHAR(30) DEFAULT NULL,
--   `customer_name` VARCHAR(255) DEFAULT NULL,
--   `customer_email` VARCHAR(255) DEFAULT NULL,
--   `customer_phone` VARCHAR(50) DEFAULT NULL,
--   `address` TEXT DEFAULT NULL,
--   `city` VARCHAR(100) DEFAULT NULL,
--   `state` VARCHAR(100) DEFAULT NULL,
--   `country` VARCHAR(100) DEFAULT NULL,
--   `zip` VARCHAR(20) DEFAULT NULL,
--   `affiliate_id` VARCHAR(50) DEFAULT NULL,
--   `affiliate_name` VARCHAR(255) DEFAULT NULL,
--   `affiliate_commission` DECIMAL(10,2) DEFAULT 0,
--   `sub_id` VARCHAR(255) DEFAULT NULL,
--   `sub_id2` VARCHAR(255) DEFAULT NULL,
--   `sub_id3` VARCHAR(255) DEFAULT NULL,
--   `sub_id4` VARCHAR(255) DEFAULT NULL,
--   `sub_id5` VARCHAR(255) DEFAULT NULL,
--   `product_names` TEXT DEFAULT NULL,
--   `product_codenames` VARCHAR(255) DEFAULT NULL,
--   `sku` VARCHAR(100) DEFAULT NULL,
--   `payment_method` VARCHAR(50) DEFAULT NULL,
--   `total_amount` DECIMAL(10,2) DEFAULT 0,
--   `vendor_net` DECIMAL(10,2) DEFAULT 0,
--   `taxes` DECIMAL(10,2) DEFAULT 0,
--   `shipping_cost` DECIMAL(10,2) DEFAULT 0,
--   `processing_fees` DECIMAL(10,2) DEFAULT 0,
--   `production_cost` DECIMAL(10,2) DEFAULT 0,
--   `handling_cost` DECIMAL(10,2) DEFAULT 0,
--   `ip_address` VARCHAR(50) DEFAULT NULL,
--   `referrer_url` TEXT DEFAULT NULL,
--   `funnel_codename` VARCHAR(255) DEFAULT NULL,
--   `flag_upsell` TINYINT(1) DEFAULT 0,
--   `is_free` TINYINT(1) DEFAULT 0,
--   `is_test` TINYINT(1) DEFAULT 0,
--   `was_canceled` TINYINT(1) DEFAULT 0,
--   `date_canceled` DATETIME DEFAULT NULL,
--   `cancel_reason` TEXT DEFAULT NULL,
--   `user_comments` TEXT DEFAULT NULL,
--   `user_id` VARCHAR(50) DEFAULT NULL,
--   `store_id` VARCHAR(20) DEFAULT NULL,
--   `order_details` TEXT DEFAULT NULL,
--   `external_order_id` VARCHAR(100) DEFAULT NULL,
--   `external_order_id2` VARCHAR(100) DEFAULT NULL,
--   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--   UNIQUE KEY `uq_domain_order` (`domain_id`, `order_id`),
--   INDEX `idx_domain_date` (`domain_id`, `date_created`),
--   INDEX `idx_customer_email` (`customer_email`),
--   INDEX `idx_affiliate` (`domain_id`, `affiliate_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- MIGRATION: Add visitor signal detection columns
-- ================================================================
-- ALTER TABLE `affiliate_traffic`
--   ADD COLUMN `is_bot` TINYINT(1) DEFAULT 0 AFTER `bounce`,
--   ADD COLUMN `bot_flags` VARCHAR(255) DEFAULT NULL AFTER `is_bot`,
--   ADD COLUMN `is_iframe` TINYINT(1) DEFAULT 0 AFTER `bot_flags`,
--   ADD COLUMN `has_adblock` TINYINT(1) DEFAULT NULL AFTER `is_iframe`,
--   ADD COLUMN `js_error_count` INT DEFAULT 0 AFTER `has_adblock`,
--   ADD COLUMN `js_errors` TEXT DEFAULT NULL AFTER `js_error_count`;

-- ================================================================
-- MIGRATION: Add fulfillment tracking columns to orders
-- ================================================================
-- ALTER TABLE `orders`
--   ADD COLUMN `fulfillment_status` ENUM('pending','shipped','delayed','delivered') DEFAULT 'pending' AFTER `created_at`,
--   ADD COLUMN `delay_reason` TEXT DEFAULT NULL AFTER `fulfillment_status`,
--   ADD COLUMN `expected_delivery` DATE DEFAULT NULL AFTER `delay_reason`,
--   ADD COLUMN `delivered_date` DATE DEFAULT NULL AFTER `expected_delivery`,
--   ADD COLUMN `compensation_offered` TINYINT(1) DEFAULT 0 AFTER `delivered_date`,
--   ADD COLUMN `compensation_amount` DECIMAL(10,2) DEFAULT 0 AFTER `compensation_offered`,
--   ADD COLUMN `compensation_notes` TEXT DEFAULT NULL AFTER `compensation_amount`,
--   ADD COLUMN `internal_notes` TEXT DEFAULT NULL AFTER `compensation_notes`;

-- ================================================================
-- TABLE: domain_shipping_config (Per-domain country shipping rules)
-- Powers the country selector + dynamic shipping override on opted-in
-- pages via the t.js tracker. Only domains with shipping_enabled=1
-- receive the shipping payload. See shipping.html admin page.
-- ================================================================
-- ALTER TABLE `domains`
--   ADD COLUMN `shipping_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;
--
-- CREATE TABLE IF NOT EXISTS `domain_shipping_config` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `domain_id` INT NOT NULL,
--   `country_code` CHAR(2) NOT NULL COMMENT 'ISO-3166 alpha-2',
--   `country_name` VARCHAR(80) NOT NULL,
--   `ship_1_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
--   `ship_2_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
--   `ship_3_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
--   `ship_6_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
--   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--   `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   UNIQUE KEY `uq_domain_country` (`domain_id`, `country_code`),
--   FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- TABLE: checkout_leads (Isolated Feature — Checkout Tracking Pixel)
-- Captures customer details from BuyGoods checkout pages
-- ================================================================
-- CREATE TABLE IF NOT EXISTS `checkout_leads` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `checkout_uuid` VARCHAR(100) NOT NULL COMMENT 'Client-generated dedup key',
--   `sessid2` VARCHAR(100) DEFAULT NULL COMMENT 'BuyGoods session ID from URL',
--   `aff_id` VARCHAR(100) DEFAULT NULL,
--   `sub_id` VARCHAR(100) DEFAULT NULL,
--   `account_id` VARCHAR(20) DEFAULT NULL COMMENT 'BuyGoods account ID from URL',
--   `product_codename` VARCHAR(100) DEFAULT NULL COMMENT 'Product code from URL',
--   `email` VARCHAR(255) DEFAULT NULL,
--   `phone` VARCHAR(50) DEFAULT NULL,
--   `full_name` VARCHAR(255) DEFAULT NULL,
--   `address` TEXT DEFAULT NULL,
--   `country` VARCHAR(100) DEFAULT NULL,
--   `state` VARCHAR(100) DEFAULT NULL,
--   `city` VARCHAR(100) DEFAULT NULL,
--   `zip` VARCHAR(20) DEFAULT NULL,
--   `payment_method` VARCHAR(30) DEFAULT NULL COMMENT 'credit_card, paypal, apple_pay',
--   `status` ENUM('started','form_filled','purchase_attempted','completed') DEFAULT 'started',
--   `ip_address` VARCHAR(45) DEFAULT NULL,
--   `user_agent` TEXT DEFAULT NULL,
--   `checkout_url` TEXT DEFAULT NULL,
--   `referrer` TEXT DEFAULT NULL,
--   `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
--   `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   `completed_at` DATETIME DEFAULT NULL,
--   UNIQUE KEY `uq_checkout_uuid` (`checkout_uuid`),
--   KEY `idx_status` (`status`),
--   KEY `idx_email` (`email`),
--   KEY `idx_sessid2` (`sessid2`),
--   KEY `idx_account_product` (`account_id`, `product_codename`),
--   KEY `idx_aff_id` (`aff_id`),
--   KEY `idx_started_at` (`started_at`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
