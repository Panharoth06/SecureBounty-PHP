-- ============================================================
-- SecureBounty Database Schema
-- MySQL 8.0+ | InnoDB | utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Table: roles
-- -----------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed roles
INSERT INTO `roles` (`name`, `description`) VALUES
    ('Admin', 'Platform administrator with full access'),
    ('Program_Owner', 'Organization representative managing bounty programs'),
    ('Researcher', 'Security researcher who finds and reports vulnerabilities');

-- -----------------------------------------------------------
-- Table: users
-- -----------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role_id` (`role_id`),
    INDEX `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: programs
-- -----------------------------------------------------------
CREATE TABLE `programs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `scope` TEXT NOT NULL,
    `status` ENUM('draft', 'active', 'closed', 'suspended') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_programs_owner_id` (`owner_id`),
    INDEX `idx_programs_status` (`status`),
    INDEX `idx_programs_status_created` (`status`, `created_at` DESC),
    CONSTRAINT `fk_programs_owner` FOREIGN KEY (`owner_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: reward_policies
-- -----------------------------------------------------------
CREATE TABLE `reward_policies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `severity` ENUM('critical', 'high', 'medium', 'low', 'informational') NOT NULL,
    `min_reward` DECIMAL(10,2) NOT NULL,
    `max_reward` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reward_program_severity` (`program_id`, `severity`),
    INDEX `idx_reward_policies_program_id` (`program_id`),
    CONSTRAINT `fk_reward_policies_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_min_reward_positive` CHECK (`min_reward` >= 0),
    CONSTRAINT `chk_max_gte_min` CHECK (`max_reward` >= `min_reward`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: reports
-- -----------------------------------------------------------
CREATE TABLE `reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `researcher_id` INT UNSIGNED NOT NULL,
    `reward_policy_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `steps_to_reproduce` TEXT NOT NULL,
    `impact` TEXT NOT NULL,
    `cvss_vector` VARCHAR(255) DEFAULT NULL COMMENT 'CVSS 3.1 vector string',
    `cvss_score` DECIMAL(3,1) DEFAULT NULL COMMENT 'Computed CVSS base score 0.0-10.0',
    `cvss_severity` ENUM('none', 'low', 'medium', 'high', 'critical') DEFAULT NULL COMMENT 'CVSS-derived severity',
    `cvss_submitted_by` ENUM('researcher', 'program_owner') DEFAULT NULL COMMENT 'Who last set CVSS values',
    `final_severity` ENUM('critical', 'high', 'medium', 'low', 'informational') DEFAULT NULL COMMENT 'Program owner final severity (used for reward matching)',
    `status` ENUM('pending', 'triaged', 'accepted', 'rejected', 'resolved') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reports_program_id` (`program_id`),
    INDEX `idx_reports_researcher_id` (`researcher_id`),
    INDEX `idx_reports_status` (`status`),
    INDEX `idx_reports_program_status` (`program_id`, `status`),
    INDEX `idx_reports_reward_policy_id` (`reward_policy_id`),
    INDEX `idx_reports_final_severity` (`final_severity`),
    CONSTRAINT `fk_reports_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reports_researcher` FOREIGN KEY (`researcher_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reports_reward_policy` FOREIGN KEY (`reward_policy_id`)
        REFERENCES `reward_policies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_cvss_score_range` CHECK (`cvss_score` IS NULL OR (`cvss_score` >= 0.0 AND `cvss_score` <= 10.0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: attachments
-- -----------------------------------------------------------
CREATE TABLE `attachments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(512) NOT NULL,
    `file_type` VARCHAR(10) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_attachments_report_id` (`report_id`),
    CONSTRAINT `fk_attachments_report` FOREIGN KEY (`report_id`)
        REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_file_size_max` CHECK (`file_size` <= 10485760),
    CONSTRAINT `chk_file_type_allowed` CHECK (`file_type` IN ('png', 'jpg', 'gif', 'pdf', 'txt', 'zip'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: comments
-- -----------------------------------------------------------
CREATE TABLE `comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_comments_report_id` (`report_id`),
    INDEX `idx_comments_user_id` (`user_id`),
    INDEX `idx_comments_parent_id` (`parent_id`),
    INDEX `idx_comments_report_created` (`report_id`, `created_at` ASC),
    CONSTRAINT `fk_comments_report` FOREIGN KEY (`report_id`)
        REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: activity_logs
-- -----------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_entity` VARCHAR(100) NOT NULL,
    `target_id` INT UNSIGNED DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_logs_user_id` (`user_id`),
    INDEX `idx_activity_logs_action` (`action`),
    INDEX `idx_activity_logs_created_at` (`created_at` DESC),
    INDEX `idx_activity_logs_target` (`target_entity`, `target_id`),
    INDEX `idx_activity_logs_user_created` (`user_id`, `created_at` DESC),
    CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: user_programs
-- -----------------------------------------------------------
CREATE TABLE `user_programs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `program_id` INT UNSIGNED NOT NULL,
    `enrolled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_program` (`user_id`, `program_id`),
    INDEX `idx_user_programs_program_id` (`program_id`),
    CONSTRAINT `fk_user_programs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_user_programs_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: saved_programs
-- -----------------------------------------------------------
CREATE TABLE `saved_programs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `program_id` INT UNSIGNED NOT NULL,
    `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_saved_program` (`user_id`, `program_id`),
    INDEX `idx_saved_programs_program_id` (`program_id`),
    CONSTRAINT `fk_saved_programs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_saved_programs_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: notifications
-- -----------------------------------------------------------
CREATE TABLE `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `reference_entity` VARCHAR(100) DEFAULT NULL,
    `reference_id` INT UNSIGNED DEFAULT NULL,
    `message` VARCHAR(500) NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`, `created_at` DESC),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: program_comments
-- -----------------------------------------------------------
CREATE TABLE `program_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_program_comments_program_id` (`program_id`),
    INDEX `idx_program_comments_user_id` (`user_id`),
    INDEX `idx_program_comments_parent_id` (`parent_id`),
    INDEX `idx_program_comments_program_created` (`program_id`, `created_at` ASC),
    CONSTRAINT `fk_program_comments_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_program_comments_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_program_comments_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `program_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: csrf_tokens
-- -----------------------------------------------------------
CREATE TABLE `csrf_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_csrf_token` (`token`),
    INDEX `idx_csrf_session` (`session_id`),
    INDEX `idx_csrf_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: program_assets
-- -----------------------------------------------------------
CREATE TABLE `program_assets` (
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
CREATE TABLE `technology_tags` (
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
CREATE TABLE `program_tags` (
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

-- -----------------------------------------------------------
-- Schema Modifications: programs table - add logo_path
-- -----------------------------------------------------------
ALTER TABLE `programs`
    ADD COLUMN `logo_path` VARCHAR(512) DEFAULT NULL AFTER `scope`;

-- -----------------------------------------------------------
-- Schema Modifications: users table - add profile columns
-- -----------------------------------------------------------
ALTER TABLE `users`
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
    ADD INDEX `idx_users_reputation` (`reputation_score` DESC, `earliest_accepted_at` ASC);

SET FOREIGN_KEY_CHECKS = 1;
