<?php
// Debug script to diagnose login issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Login Debug Information</h2>";

// Check PHP version
echo "<h3>PHP Version:</h3>";
echo phpversion() . "<br><br>";

// Check if session can be started
echo "<h3>Session Status:</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "Session started successfully<br>";
} else {
    echo "Session already active<br>";
}
echo "Session ID: " . session_id() . "<br><br>";

// Check database configuration
echo "<h3>Database Configuration:</h3>";
require_once 'db.php';

// Check if database constants are defined
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "<br>";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "<br>";
echo "DB_PASS: " . (defined('DB_PASS') ? (strlen(DB_PASS) > 0 ? 'SET' : 'EMPTY') : 'NOT DEFINED') . "<br><br>";

// Check PDO connection
echo "<h3>Database Connection:</h3>";
if (isset($pdo)) {
    echo "PDO connection object exists<br>";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "Database query successful<br>";
    } catch (PDOException $e) {
        echo "Database query failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "PDO connection object does not exist<br>";
}

// Check if required tables exist
echo "<h3>Database Tables:</h3>";
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "<br><br>";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error checking tables: " . $e->getMessage() . "<br>";
}

// Check file permissions
echo "<h3>File Permissions:</h3>";
$files = ['db.php', 'login.php', 'dashboard.php', 'includes/roles.php', 'includes/monitoring_functions.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "$file: " . (is_readable($file) ? 'Readable' : 'NOT Readable') . "<br>";
    } else {
        echo "$file: FILE NOT FOUND<br>";
    }
}

// Check if includes directory exists
echo "<br>includes/ directory: " . (is_dir('includes') ? 'EXISTS' : 'NOT FOUND') . "<br>";

// Check session save path
echo "<h3>Session Configuration:</h3>";
echo "Session save path: " . ini_get('session.save_path') . "<br>";
echo "Session save path writable: " . (is_writable(ini_get('session.save_path')) ? 'YES' : 'NO') . "<br>";

phpinfo();
?>