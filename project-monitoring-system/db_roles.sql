-- User Roles System Database Schema
-- Run this script after db.sql and db_update.sql to add roles functionality

-- Table for storing user roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add role_id to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `role_id` INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `url_limit` INT(11) DEFAULT 10,
ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

-- Table for role permissions (for future extensibility)
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_permission_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for role-permission relationships
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT(11) NOT NULL,
    `permission_id` INT(11) NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for URL limits per role
CREATE TABLE IF NOT EXISTS `role_url_limits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `role_id` INT(11) NOT NULL,
    `url_limit` INT(11) NOT NULL DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_limit` (`role_id`),
    CONSTRAINT `fk_url_limits_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`name`, `description`) VALUES 
('Admin', 'Administrator with full system access'),
('Customer', 'Regular customer with limited access')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Insert default permissions
INSERT INTO `permissions` (`name`, `description`) VALUES 
('manage_users', 'Can create, update, and delete users'),
('manage_roles', 'Can create, update, and delete roles'),
('manage_projects', 'Can manage all projects'),
('view_all_projects', 'Can view all projects in the system'),
('manage_own_projects', 'Can manage only own projects'),
('manage_settings', 'Can manage system settings'),
('view_reports', 'Can view system reports')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Assign permissions to Admin role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id 
FROM `roles` r, `permissions` p 
WHERE r.name = 'Admin'
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

-- Assign basic permissions to Customer role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id 
FROM `roles` r, `permissions` p 
WHERE r.name = 'Customer' AND p.name IN ('manage_own_projects')
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

-- Set default URL limits
INSERT INTO `role_url_limits` (`role_id`, `url_limit`)
SELECT id, CASE 
    WHEN name = 'Admin' THEN 999999
    WHEN name = 'Customer' THEN 10
    ELSE 10
END
FROM `roles`
ON DUPLICATE KEY UPDATE `url_limit` = VALUES(`url_limit`);

-- Update existing users to Customer role if no role assigned
UPDATE `users` u
SET u.role_id = (SELECT id FROM `roles` WHERE name = 'Customer')
WHERE u.role_id IS NULL;

-- Add status column to projects table for better URL tracking
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
ADD INDEX `idx_project_status` (`status`);