<?php

/**
 * TestDatabaseHelper
 *
 * Provides methods to create, migrate, seed, and tear down the test database.
 * Used by PHPUnit tests that need a real MySQL connection.
 */

declare(strict_types=1);

namespace Tests;

use mysqli;
use RuntimeException;

class TestDatabaseHelper
{
    private static ?mysqli $connection = null;

    /**
     * Get the test database connection credentials from environment.
     *
     * @return array{host: string, port: int, name: string, user: string, pass: string}
     */
    public static function getConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
            'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'securebounty_test',
            'user' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
            'pass' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
        ];
    }

    /**
     * Get a MySQLi connection to the test database.
     * Creates the database if it does not exist.
     *
     * @return mysqli
     * @throws RuntimeException if connection fails.
     */
    public static function getConnection(): mysqli
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = self::getConfig();

        // Connect without selecting a database first
        $conn = new mysqli($config['host'], $config['user'], $config['pass'], '', $config['port']);

        if ($conn->connect_error) {
            throw new RuntimeException("Test DB connection failed: {$conn->connect_error}");
        }

        $conn->set_charset('utf8mb4');

        // Create the test database if it doesn't exist
        $dbName = $conn->real_escape_string($config['name']);
        $conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Select the test database
        if (!$conn->select_db($config['name'])) {
            throw new RuntimeException("Failed to select test database: {$config['name']}");
        }

        self::$connection = $conn;

        return self::$connection;
    }

    /**
     * Run the full database schema migration on the test database.
     * Drops all existing tables and recreates them.
     */
    public static function migrate(): void
    {
        $conn = self::getConnection();

        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Drop all existing tables
        $tables = [
            'program_comments',
            'csrf_tokens',
            'notifications',
            'saved_programs',
            'user_programs',
            'activity_logs',
            'comments',
            'attachments',
            'reports',
            'reward_policies',
            'programs',
            'users',
            'roles',
        ];

        foreach ($tables as $table) {
            $conn->query("DROP TABLE IF EXISTS `{$table}`");
        }

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        // Run schema DDL
        $schemaFile = dirname(__DIR__) . '/database/schema.sql';

        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $conn->multi_query($sql);

            // Consume all results from multi_query
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());

            if ($conn->error) {
                throw new RuntimeException("Migration error: {$conn->error}");
            }
        } else {
            // Inline minimal schema for tests if schema file doesn't exist yet
            self::createMinimalSchema($conn);
        }
    }

    /**
     * Seed the test database with baseline data (roles).
     */
    public static function seed(): void
    {
        $conn = self::getConnection();

        // Seed roles if empty
        $result = $conn->query("SELECT COUNT(*) as cnt FROM `roles`");
        $row = $result->fetch_assoc();

        if ((int) $row['cnt'] === 0) {
            $conn->query("INSERT INTO `roles` (`name`, `description`) VALUES
                ('Admin', 'Platform administrator with full access'),
                ('Program_Owner', 'Organization representative managing bounty programs'),
                ('Researcher', 'Security researcher who finds and reports vulnerabilities')
            ");
        }
    }

    /**
     * Truncate all data tables (preserving schema and roles).
     * Useful for cleaning up between tests.
     */
    public static function cleanUp(): void
    {
        $conn = self::getConnection();

        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        $dataTables = [
            'program_comments',
            'csrf_tokens',
            'notifications',
            'saved_programs',
            'user_programs',
            'activity_logs',
            'comments',
            'attachments',
            'reports',
            'reward_policies',
            'programs',
            'users',
        ];

        foreach ($dataTables as $table) {
            $conn->query("TRUNCATE TABLE `{$table}`");
        }

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    /**
     * Drop the entire test database.
     */
    public static function dropDatabase(): void
    {
        $config = self::getConfig();
        $conn = self::getConnection();

        $dbName = $conn->real_escape_string($config['name']);
        $conn->query("DROP DATABASE IF EXISTS `{$dbName}`");

        self::$connection = null;
    }

    /**
     * Close the test database connection.
     */
    public static function closeConnection(): void
    {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }

    /**
     * Create a minimal schema inline for when the schema.sql file doesn't exist yet.
     */
    private static function createMinimalSchema(mysqli $conn): void
    {
        $statements = [
            "CREATE TABLE `roles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_roles_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `users` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `programs` (
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
                CONSTRAINT `fk_programs_owner` FOREIGN KEY (`owner_id`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `reward_policies` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `program_id` INT UNSIGNED NOT NULL,
                `severity` ENUM('critical', 'high', 'medium', 'low', 'informational') NOT NULL,
                `min_reward` DECIMAL(10,2) NOT NULL,
                `max_reward` DECIMAL(10,2) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_reward_program_severity` (`program_id`, `severity`),
                CONSTRAINT `fk_reward_policies_program` FOREIGN KEY (`program_id`)
                    REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `chk_min_reward_positive` CHECK (`min_reward` >= 0),
                CONSTRAINT `chk_max_gte_min` CHECK (`max_reward` >= `min_reward`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `reports` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `program_id` INT UNSIGNED NOT NULL,
                `researcher_id` INT UNSIGNED NOT NULL,
                `reward_policy_id` INT UNSIGNED DEFAULT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NOT NULL,
                `steps_to_reproduce` TEXT NOT NULL,
                `impact` TEXT NOT NULL,
                `cvss_vector` VARCHAR(255) DEFAULT NULL,
                `cvss_score` DECIMAL(3,1) DEFAULT NULL,
                `cvss_severity` ENUM('none', 'low', 'medium', 'high', 'critical') DEFAULT NULL,
                `cvss_submitted_by` ENUM('researcher', 'program_owner') DEFAULT NULL,
                `final_severity` ENUM('critical', 'high', 'medium', 'low', 'informational') DEFAULT NULL,
                `status` ENUM('pending', 'triaged', 'accepted', 'rejected', 'resolved') NOT NULL DEFAULT 'pending',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_reports_program_id` (`program_id`),
                INDEX `idx_reports_researcher_id` (`researcher_id`),
                INDEX `idx_reports_status` (`status`),
                CONSTRAINT `fk_reports_program` FOREIGN KEY (`program_id`)
                    REFERENCES `programs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_reports_researcher` FOREIGN KEY (`researcher_id`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_reports_reward_policy` FOREIGN KEY (`reward_policy_id`)
                    REFERENCES `reward_policies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `attachments` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `comments` (
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
                CONSTRAINT `fk_comments_report` FOREIGN KEY (`report_id`)
                    REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`)
                    REFERENCES `comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `activity_logs` (
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
                CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `user_programs` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `saved_programs` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `program_comments` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `notifications` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE `csrf_tokens` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `session_id` VARCHAR(128) NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_csrf_token` (`token`),
                INDEX `idx_csrf_session` (`session_id`),
                INDEX `idx_csrf_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            if (!$conn->query($sql)) {
                throw new RuntimeException("Schema creation failed: {$conn->error}");
            }
        }
    }
}
