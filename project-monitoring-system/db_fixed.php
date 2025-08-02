<?php
/**
 * Database Configuration File - Fixed Version
 * 
 * IMPORTANT: Update these values with your Hostinger database credentials
 * For security, consider moving this file outside the public_html directory
 * and update the require_once paths accordingly
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost'); // Usually 'localhost' on Hostinger
define('DB_NAME', 'your_database_name'); // Replace with your database name
define('DB_USER', 'your_database_user'); // Replace with your database username
define('DB_PASS', 'your_database_password'); // Replace with your database password

// Create database connection with error handling
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Log the actual error (in production, log to file instead)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error message
    if (ini_get('display_errors')) {
        die("Database connection failed: " . $e->getMessage() . 
            "<br><br>Please check:<br>" .
            "1. Database credentials in db.php<br>" .
            "2. Database server is running<br>" .
            "3. Database '" . DB_NAME . "' exists<br>" .
            "4. User '" . DB_USER . "' has access to the database");
    } else {
        die("Database connection failed. Please check the error logs.");
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to handle database errors gracefully
function handleDatabaseError($e, $userMessage = "An error occurred. Please try again.") {
    error_log("Database error: " . $e->getMessage());
    if (ini_get('display_errors')) {
        return "Database error: " . $e->getMessage();
    }
    return $userMessage;
}
?>