-- Applies the program-enhancements schema additions that are missing
-- from the existing securebounty_db. Safe to run on a database that already
-- has the core tables (programs, users, etc.). Idempotent.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Table: program_assets
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_assets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('Domain', 'Wildcard', 'iOS App Store', 'Android Play Store', 'Windows App', 'Other') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_asset_name_program` (`program_id`, `name`),
    INDEX `idx_assets_program_id` (`program_id`),
    INDEX `idx_assets_type` (`type`),
    CONSTRAINT `fk_assets_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: technology_tags
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `normalized_name` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tag_normalized` (`normalized_name`),
    INDEX `idx_tags_normalized` (`normalized_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: program_tags (junction)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_program_tag` (`program_id`, `tag_id`),
    INDEX `idx_program_tags_tag_id` (`tag_id`),
    CONSTRAINT `fk_program_tags_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_program_tags_tag` FOREIGN KEY (`tag_id`)
        REFERENCES `technology_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------
-- programs.logo_path
-- -----------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'logo_path');
SET @stmt := IF(@col_exists = 0,
    'ALTER TABLE `programs` ADD COLUMN `logo_path` VARCHAR(512) DEFAULT NULL AFTER `scope`',
    'SELECT "programs.logo_path already exists" AS info');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------
-- users profile columns + reputation
-- -----------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'display_name');
SET @stmt := IF(@col_exists = 0,
    'ALTER TABLE `users`
        ADD COLUMN `display_name` VARCHAR(50) DEFAULT NULL AFTER `last_name`,
        ADD COLUMN `bio` VARCHAR(500) DEFAULT NULL AFTER `display_name`,
        ADD COLUMN `avatar_path` VARCHAR(512) DEFAULT NULL AFTER `bio`,
        ADD COLUMN `website_url` VARCHAR(255) DEFAULT NULL AFTER `avatar_path`,
        ADD COLUMN `github_url` VARCHAR(255) DEFAULT NULL AFTER `website_url`,
        ADD COLUMN `linkedin_url` VARCHAR(255) DEFAULT NULL AFTER `github_url`,
        ADD COLUMN `facebook_url` VARCHAR(255) DEFAULT NULL AFTER `linkedin_url`,
        ADD COLUMN `youtube_url` VARCHAR(255) DEFAULT NULL AFTER `facebook_url`,
        ADD COLUMN `instagram_url` VARCHAR(255) DEFAULT NULL AFTER `youtube_url`,
        ADD COLUMN `reputation_score` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `instagram_url`,
        ADD COLUMN `earliest_accepted_at` TIMESTAMP NULL DEFAULT NULL AFTER `reputation_score`,
        ADD INDEX `idx_users_reputation` (`reputation_score` DESC, `earliest_accepted_at` ASC)',
    'SELECT "users profile columns already exist" AS info');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
