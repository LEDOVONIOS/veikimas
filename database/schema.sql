-- PHP Website Monitoring Dashboard
-- Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS `{PREFIX}users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `project_limit` int(11) NOT NULL DEFAULT 5,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects table
CREATE TABLE IF NOT EXISTS `{PREFIX}projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `check_interval` int(11) NOT NULL DEFAULT 300,
  `timeout` int(11) NOT NULL DEFAULT 30,
  `method` enum('GET','POST','HEAD') NOT NULL DEFAULT 'GET',
  `expected_status` int(11) NOT NULL DEFAULT 200,
  `search_string` varchar(255) DEFAULT NULL,
  `notify_email` varchar(255) DEFAULT NULL,
  `notify_down` tinyint(1) NOT NULL DEFAULT 1,
  `notify_up` tinyint(1) NOT NULL DEFAULT 1,
  `notify_ssl` tinyint(1) NOT NULL DEFAULT 1,
  `notify_domain` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','paused') NOT NULL DEFAULT 'active',
  `current_status` enum('up','down','unknown') NOT NULL DEFAULT 'unknown',
  `last_check` timestamp NULL DEFAULT NULL,
  `last_status_change` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `current_status` (`current_status`),
  CONSTRAINT `projects_user_fk` FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Monitor logs table (stores check results)
CREATE TABLE IF NOT EXISTS `{PREFIX}monitor_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response_time` decimal(10,3) DEFAULT NULL,
  `is_up` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `checked_at` (`checked_at`),
  KEY `is_up` (`is_up`),
  CONSTRAINT `monitor_logs_project_fk` FOREIGN KEY (`project_id`) REFERENCES `{PREFIX}projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Incident logs table (tracks downtime periods)
CREATE TABLE IF NOT EXISTS `{PREFIX}incident_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` timestamp NULL DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `checks_failed` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `started_at` (`started_at`),
  KEY `ended_at` (`ended_at`),
  CONSTRAINT `incident_logs_project_fk` FOREIGN KEY (`project_id`) REFERENCES `{PREFIX}projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SSL data table
CREATE TABLE IF NOT EXISTS `{PREFIX}ssl_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `issuer` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_to` timestamp NULL DEFAULT NULL,
  `days_remaining` int(11) DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 0,
  `last_check` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_id` (`project_id`),
  KEY `valid_to` (`valid_to`),
  KEY `days_remaining` (`days_remaining`),
  CONSTRAINT `ssl_data_project_fk` FOREIGN KEY (`project_id`) REFERENCES `{PREFIX}projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domain data table
CREATE TABLE IF NOT EXISTS `{PREFIX}domain_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `registrar` varchar(255) DEFAULT NULL,
  `created_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `days_remaining` int(11) DEFAULT NULL,
  `last_check` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_id` (`project_id`),
  KEY `expiry_date` (`expiry_date`),
  KEY `days_remaining` (`days_remaining`),
  CONSTRAINT `domain_data_project_fk` FOREIGN KEY (`project_id`) REFERENCES `{PREFIX}projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification logs table
CREATE TABLE IF NOT EXISTS `{PREFIX}notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `type` enum('down','up','ssl_expiry','domain_expiry') NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `sent_at` (`sent_at`),
  KEY `type` (`type`),
  CONSTRAINT `notification_logs_project_fk` FOREIGN KEY (`project_id`) REFERENCES `{PREFIX}projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS `{PREFIX}settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO `{PREFIX}users` (`username`, `email`, `password`, `role`, `project_limit`) 
VALUES ('admin', 'admin@localhost', '$2y$10$YourHashedPasswordHere', 'admin', 999);

-- Insert default settings
INSERT INTO `{PREFIX}settings` (`setting_key`, `setting_value`) VALUES
('cron_last_run', NULL),
('installation_date', NOW()),
('version', '1.0.0');