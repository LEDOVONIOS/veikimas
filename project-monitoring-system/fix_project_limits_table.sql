-- Fix for missing project_limits table
-- This script creates the project_limits table that is required by the roles.php functions

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

-- Optional: Add default project limits for existing users
-- This ensures all existing users get the default limit of 10 projects
INSERT IGNORE INTO `project_limits` (`user_id`, `max_projects`)
SELECT `id`, 10 FROM `users`
WHERE `id` NOT IN (SELECT `user_id` FROM `project_limits`);