<?php
/**
 * Application initialization file
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start output buffering
ob_start();

// Include required files
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Monitor.php';
require_once __DIR__ . '/Mailer.php';

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
    file_put_contents($logsDir . '/.htaccess', 'Deny from all');
}

// Initialize database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize authentication
$auth = Auth::getInstance();