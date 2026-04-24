-- Digistore24 Platform Support Migration
-- Run this on: u373133718_shaver database via phpMyAdmin

ALTER TABLE `domains`
  ADD COLUMN `platform` ENUM('buygoods','digistore24') NOT NULL DEFAULT 'buygoods' AFTER `bg_iframe_script`,
  ADD COLUMN `ds24_product_id` VARCHAR(20) DEFAULT NULL AFTER `platform`,
  ADD KEY `idx_platform` (`platform`);
