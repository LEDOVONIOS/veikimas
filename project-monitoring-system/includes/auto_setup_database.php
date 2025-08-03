<?php
/**
 * Automatic Database Setup Script
 * This script automatically creates all necessary tables if they don't exist
 * Include this file in db.php to ensure tables are created on first run
 */

function setupDatabase($pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Create users table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `full_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `role_id` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_email` (`email`),
                KEY `fk_user_role` (`role_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create roles table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `roles` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_role_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default roles if they don't exist
        $pdo->exec("
            INSERT IGNORE INTO `roles` (`name`, `description`) VALUES
            ('Admin', 'Administrator with full system access and user management capabilities'),
            ('User', 'Regular user with project contribution limits')
        ");
        
        // Create projects table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `projects` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `project_name` VARCHAR(200) NOT NULL,
                `project_url` VARCHAR(255) DEFAULT NULL,
                `description` TEXT,
                `server_location` VARCHAR(100) DEFAULT NULL,
                `last_checked` TIMESTAMP NULL DEFAULT NULL,
                `monitoring_region` VARCHAR(100) DEFAULT 'North America',
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_user_id` (`user_id`),
                KEY `idx_projects_url` (`project_url`),
                CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create project_limits table (formerly url_limits)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `project_limits` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `max_projects` INT(11) NOT NULL DEFAULT 10,
                `set_by_admin_id` INT(11) DEFAULT NULL,
                `limit_message` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_limit` (`user_id`),
                KEY `fk_set_by_admin` (`set_by_admin_id`),
                CONSTRAINT `fk_project_limits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_project_limits_admin` FOREIGN KEY (`set_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create incidents table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `incidents` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `status` ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open',
                `root_cause` TEXT NOT NULL,
                `started_at` DATETIME NOT NULL,
                `duration` VARCHAR(50) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_project_id` (`project_id`),
                KEY `idx_status` (`status`),
                CONSTRAINT `fk_incidents_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create admin_project_access_log table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin_project_access_log` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `admin_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_admin_access_admin` (`admin_id`),
                KEY `fk_admin_access_project` (`project_id`),
                KEY `idx_admin_access_admin` (`admin_id`),
                KEY `idx_admin_access_project` (`project_id`),
                KEY `idx_admin_access_time` (`accessed_at`),
                CONSTRAINT `fk_admin_access_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_admin_access_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create http_status_logs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `http_status_logs` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `status_code` INT(3) NOT NULL,
                `count` INT(11) DEFAULT 1,
                `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_project_status` (`project_id`, `status_code`),
                KEY `idx_checked_at` (`checked_at`),
                CONSTRAINT `fk_status_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create uptime_logs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `uptime_logs` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `is_up` BOOLEAN DEFAULT TRUE,
                `response_time` INT(11) DEFAULT NULL COMMENT 'Response time in milliseconds',
                `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_project_uptime` (`project_id`, `checked_at`),
                CONSTRAINT `fk_uptime_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create ssl_certificates table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `ssl_certificates` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `issuer` VARCHAR(255) DEFAULT NULL,
                `expiry_date` DATE DEFAULT NULL,
                `domain_expiry_date` DATE DEFAULT NULL,
                `last_checked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_project_ssl` (`project_id`),
                CONSTRAINT `fk_ssl_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create response_times table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `response_times` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `response_time` INT(11) NOT NULL COMMENT 'Response time in milliseconds',
                `measured_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_project_response` (`project_id`, `measured_at`),
                CONSTRAINT `fk_response_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create cron_jobs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `cron_jobs` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `project_id` INT(11) NOT NULL,
                `job_name` VARCHAR(255) NOT NULL,
                `schedule` VARCHAR(100) NOT NULL COMMENT 'Cron expression',
                `last_run` TIMESTAMP NULL DEFAULT NULL,
                `next_run` TIMESTAMP NULL DEFAULT NULL,
                `status` ENUM('success', 'failed', 'running', 'pending') DEFAULT 'pending',
                `last_duration` INT(11) DEFAULT NULL COMMENT 'Duration in seconds',
                `error_message` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_project_cron` (`project_id`),
                CONSTRAINT `fk_cron_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create notifications table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                `type` ENUM('down', 'up', 'ssl_expiry', 'domain_expiry', 'cron_failed') NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `is_read` BOOLEAN DEFAULT FALSE,
                `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_notifications` (`user_id`, `is_read`),
                KEY `idx_project_notifications` (`project_id`),
                CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_notification_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create notification_settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notification_settings` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                `notify_on_down` BOOLEAN DEFAULT TRUE,
                `notify_on_ssl_expiry` BOOLEAN DEFAULT TRUE,
                `notify_on_domain_expiry` BOOLEAN DEFAULT TRUE,
                `notify_on_cron_failure` BOOLEAN DEFAULT TRUE,
                `email_notifications` BOOLEAN DEFAULT TRUE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_project_settings` (`user_id`, `project_id`),
                CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_settings_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create password_resets table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(100) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`),
                KEY `idx_token` (`token`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create the user_project_count view
        $pdo->exec("
            CREATE OR REPLACE VIEW `user_project_count` AS
            SELECT 
                u.id AS user_id,
                u.full_name,
                u.email,
                r.name AS role_name,
                COUNT(DISTINCT p.id) AS project_count,
                COALESCE(pl.max_projects, 10) AS project_limit,
                pl.limit_message,
                pl.set_by_admin_id,
                admin.full_name AS limit_set_by
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN projects p ON u.id = p.user_id
            LEFT JOIN project_limits pl ON u.id = pl.user_id
            LEFT JOIN users admin ON pl.set_by_admin_id = admin.id
            GROUP BY u.id, u.full_name, u.email, r.name, pl.max_projects, pl.limit_message, pl.set_by_admin_id, admin.full_name
        ");
        
        // Add foreign key constraint for users.role_id if not exists
        $constraintExists = $pdo->query("
            SELECT COUNT(*) as count 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'users' 
            AND CONSTRAINT_NAME = 'fk_users_role'
        ")->fetch()['count'];
        
        if ($constraintExists == 0) {
            $pdo->exec("
                ALTER TABLE `users` 
                ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
            ");
        }
        
        // Set default role for existing users without a role
        $pdo->exec("
            UPDATE `users` 
            SET `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'User')
            WHERE `role_id` IS NULL
        ");
        
        // Make the first user (lowest ID) an admin if exists
        $pdo->exec("
            UPDATE users 
            SET role_id = (SELECT id FROM roles WHERE name = 'Admin')
            WHERE id = (SELECT MIN(id) FROM (SELECT id FROM users) AS u)
        ");
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollback();
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// Function to check if database is properly set up
function isDatabaseSetup($pdo) {
    try {
        // Check if all critical tables exist
        $requiredTables = [
            'users', 'roles', 'projects', 'project_limits', 'incidents',
            'http_status_logs', 'uptime_logs', 'ssl_certificates',
            'response_times', 'cron_jobs', 'notifications',
            'notification_settings', 'password_resets', 'admin_project_access_log'
        ];
        
        foreach ($requiredTables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if (!$result) {
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>