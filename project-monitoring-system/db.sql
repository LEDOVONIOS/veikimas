-- Project Monitoring System Database Schema
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
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create projects table
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `project_name` VARCHAR(200) NOT NULL,
    `project_url` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
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

-- Create indexes for better performance
CREATE INDEX `idx_user_email` ON `users` (`email`);
CREATE INDEX `idx_project_user` ON `projects` (`user_id`);
CREATE INDEX `idx_incident_project` ON `incidents` (`project_id`);