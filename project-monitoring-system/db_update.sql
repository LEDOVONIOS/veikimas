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

-- Insert sample data for testing (optional - remove in production)
-- This will be added via a separate script