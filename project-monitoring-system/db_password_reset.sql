-- Password Reset System Database Update
-- This script adds password reset functionality to the Project Monitoring System

-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`token`),
    KEY `idx_user_token` (`user_id`, `token`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up expired tokens periodically (optional - can be run as a cron job)
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = TRUE;