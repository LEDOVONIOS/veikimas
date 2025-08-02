-- Enhanced Role Management System
-- This script updates the role system to use User/Admin terminology and adds project limits

-- First, update role names to match requirements
UPDATE `roles` SET `name` = 'User', `description` = 'Regular user with project contribution limits' WHERE `name` = 'Customer';
UPDATE `roles` SET `name` = 'Admin', `description` = 'Administrator with full system access and user management capabilities' WHERE `name` = 'Admin';

-- Rename url_limits table to project_limits to better reflect its purpose
RENAME TABLE `url_limits` TO `project_limits`;

-- Update the column name from max_urls to max_projects
ALTER TABLE `project_limits` 
CHANGE COLUMN `max_urls` `max_projects` INT(11) NOT NULL DEFAULT 10;

-- Add a column to track who set the limit (for audit purposes)
ALTER TABLE `project_limits`
ADD COLUMN `set_by_admin_id` INT(11) DEFAULT NULL AFTER `max_projects`,
ADD COLUMN `limit_message` TEXT DEFAULT NULL AFTER `set_by_admin_id`,
ADD KEY `fk_set_by_admin` (`set_by_admin_id`),
ADD CONSTRAINT `fk_project_limits_admin` FOREIGN KEY (`set_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create a table to track admin access to user projects (for audit trail)
CREATE TABLE IF NOT EXISTS `admin_project_access_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` INT(11) NOT NULL,
    `project_id` INT(11) NOT NULL,
    `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_admin_access_admin` (`admin_id`),
    KEY `fk_admin_access_project` (`project_id`),
    CONSTRAINT `fk_admin_access_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_admin_access_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update the view to use new table and column names
DROP VIEW IF EXISTS `user_url_count`;
CREATE VIEW `user_project_count` AS
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
GROUP BY u.id, u.full_name, u.email, r.name, pl.max_projects, pl.limit_message, pl.set_by_admin_id, admin.full_name;

-- Ensure the first user (lowest ID) is an admin
UPDATE users 
SET role_id = (SELECT id FROM roles WHERE name = 'Admin')
WHERE id = (SELECT MIN(id) FROM (SELECT id FROM users) AS u);

-- Create indexes for better performance
CREATE INDEX `idx_admin_access_admin` ON `admin_project_access_log` (`admin_id`);
CREATE INDEX `idx_admin_access_project` ON `admin_project_access_log` (`project_id`);
CREATE INDEX `idx_admin_access_time` ON `admin_project_access_log` (`accessed_at`);