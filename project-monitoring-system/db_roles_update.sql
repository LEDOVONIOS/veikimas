-- User Roles System Database Update
-- This script adds user roles functionality to the Project Monitoring System

-- Create roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add role_id column to users table
ALTER TABLE `users` 
ADD COLUMN `role_id` INT(11) DEFAULT NULL AFTER `password_hash`,
ADD KEY `fk_user_role` (`role_id`),
ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

-- Create url_limits table for customer URL restrictions
CREATE TABLE IF NOT EXISTS `url_limits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `max_urls` INT(11) NOT NULL DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_limit` (`user_id`),
    CONSTRAINT `fk_url_limits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`name`, `description`) VALUES
('Admin', 'Administrator with full system access'),
('Customer', 'Regular customer with limited access');

-- Update existing users to have Customer role by default
UPDATE `users` 
SET `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'Customer')
WHERE `role_id` IS NULL;

-- Create a view to easily count URLs per user
CREATE OR REPLACE VIEW `user_url_count` AS
SELECT 
    u.id AS user_id,
    u.email,
    r.name AS role_name,
    COUNT(DISTINCT p.project_url) AS url_count,
    COALESCE(ul.max_urls, 10) AS url_limit
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
LEFT JOIN projects p ON u.id = p.user_id AND p.project_url IS NOT NULL AND p.project_url != ''
LEFT JOIN url_limits ul ON u.id = ul.user_id
GROUP BY u.id, u.email, r.name, ul.max_urls;

-- Add index for better performance
CREATE INDEX `idx_projects_url` ON `projects` (`project_url`);