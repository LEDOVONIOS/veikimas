-- Project Monitoring System Complete Database Schema
-- This file includes ALL required tables for the system to function properly
-- 
-- Instructions for Hostinger:
-- 1. Create a new MySQL database in your Hostinger control panel
-- 2. Open phpMyAdmin from your Hostinger control panel
-- 3. Select your database
-- 4. Click on "SQL" tab
-- 5. Copy and paste this entire script
-- 6. Click "Go" to execute

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`email`),
    UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(50) NOT NULL,
    `can_create_projects` BOOLEAN DEFAULT TRUE,
    `can_edit_own_projects` BOOLEAN DEFAULT TRUE,
    `can_delete_own_projects` BOOLEAN DEFAULT TRUE,
    `can_view_all_projects` BOOLEAN DEFAULT FALSE,
    `can_edit_all_projects` BOOLEAN DEFAULT FALSE,
    `can_delete_all_projects` BOOLEAN DEFAULT FALSE,
    `can_manage_users` BOOLEAN DEFAULT FALSE,
    `can_manage_roles` BOOLEAN DEFAULT FALSE,
    `is_system_role` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_limits table (IMPORTANT: This was missing in original schema)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create projects table
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
    CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create incidents table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT IGNORE INTO `roles` (`role_name`, `can_create_projects`, `can_edit_own_projects`, `can_delete_own_projects`, `can_view_all_projects`, `can_edit_all_projects`, `can_delete_all_projects`, `can_manage_users`, `can_manage_roles`, `is_system_role`) VALUES
('admin', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE),
('user', TRUE, TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, FALSE, TRUE);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_user_email` ON `users` (`email`);
CREATE INDEX IF NOT EXISTS `idx_project_user` ON `projects` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_incident_project` ON `incidents` (`project_id`);