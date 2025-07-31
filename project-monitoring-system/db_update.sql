-- Project Monitoring System Database Update
-- Run this script after the initial db.sql to add monitoring features

-- Table for storing HTTP status code data
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing uptime data
CREATE TABLE IF NOT EXISTS `uptime_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `project_id` INT(11) NOT NULL,
    `is_up` BOOLEAN DEFAULT TRUE,
    `response_time` INT(11) DEFAULT NULL COMMENT 'Response time in milliseconds',
    `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_uptime` (`project_id`, `checked_at`),
    CONSTRAINT `fk_uptime_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for SSL certificate information
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for response time metrics
CREATE TABLE IF NOT EXISTS `response_times` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `project_id` INT(11) NOT NULL,
    `response_time` INT(11) NOT NULL COMMENT 'Response time in milliseconds',
    `measured_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_response` (`project_id`, `measured_at`),
    CONSTRAINT `fk_response_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add location field to projects table
ALTER TABLE `projects` ADD COLUMN `server_location` VARCHAR(100) DEFAULT NULL AFTER `description`;

-- Add last_checked and monitoring_region fields to projects table
ALTER TABLE `projects` ADD COLUMN `last_checked` TIMESTAMP NULL DEFAULT NULL AFTER `server_location`;
ALTER TABLE `projects` ADD COLUMN `monitoring_region` VARCHAR(100) DEFAULT 'North America' AFTER `last_checked`;

-- Table for cron job monitoring
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for notifications
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for notification settings
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for testing (optional - remove in production)
-- This will be added via a separate script