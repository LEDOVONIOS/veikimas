<?php
/**
 * Configuration file for PHP Website Monitoring Dashboard
 * Rename this file to config.php and update with your settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_PREFIX', 'monitor_'); // Table prefix

// Application Settings
define('SITE_URL', 'https://yourdomain.com'); // Without trailing slash
define('SITE_NAME', 'Website Monitor');
define('TIMEZONE', 'Etc/GMT-3'); // UTC+03:00

// Email Settings
define('MAIL_FROM', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'Website Monitor');
define('MAIL_METHOD', 'mail'); // Options: 'mail', 'smtp'

// SMTP Settings (if MAIL_METHOD = 'smtp')
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your@email.com');
define('SMTP_PASS', 'your_password');
define('SMTP_SECURE', 'tls'); // Options: 'tls', 'ssl'

// Monitoring Settings
define('CHECK_TIMEOUT', 30); // Seconds to wait for response
define('CHECK_INTERVAL', 300); // Default check interval in seconds (5 minutes)
define('SSL_WARNING_DAYS', 15); // Warn when SSL expires in X days
define('DOMAIN_WARNING_DAYS', 30); // Warn when domain expires in X days

// Security
define('CRON_KEY', 'generate_random_key_here'); // Change this!
define('SESSION_NAME', 'monitor_session');
define('SALT', 'generate_random_salt_here'); // Change this!

// Features
define('ENABLE_WHOIS', true); // Enable domain expiration checking
define('ENABLE_SSL_CHECK', true); // Enable SSL certificate checking
define('ENABLE_REGISTRATION', false); // Allow public registration

// Limits
define('DEFAULT_PROJECT_LIMIT', 5); // Default project limit for new users
define('MAX_INCIDENTS_DISPLAY', 50); // Maximum incidents to show in history

// Development
define('DEBUG_MODE', false); // Set to true for development
define('LOG_ERRORS', true); // Log errors to file

// Error logging
if (LOG_ERRORS) {
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}