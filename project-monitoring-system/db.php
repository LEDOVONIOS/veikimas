<?php
/**
 * Database Configuration File
 * 
 * IMPORTANT: Update these values with your Hostinger database credentials
 * For security, consider moving this file outside the public_html directory
 * and update the require_once paths accordingly
 */

// Include the automatic database setup script
require_once __DIR__ . '/includes/auto_setup_database.php';

// Database configuration
define('DB_HOST', 'localhost'); // Usually 'localhost' on Hostinger
define('DB_NAME', 'your_database_name'); // Replace with your database name
define('DB_USER', 'your_database_user'); // Replace with your database username
define('DB_PASS', 'your_database_password'); // Replace with your database password

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
    
    // Automatically set up database tables if they don't exist
    if (!isDatabaseSetup($pdo)) {
        setupDatabase($pdo);
    }
} catch (PDOException $e) {
    // In production, log this error instead of displaying it
    die("Database connection failed: " . $e->getMessage());
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>