<?php
/**
 * Standalone Database Setup Script
 * 
 * This script can be run to manually create all necessary database tables.
 * Access via browser: http://yourdomain.com/setup_database.php
 * Or run via command line: php setup_database.php
 * 
 * IMPORTANT: Delete this file after setup for security reasons!
 */

// Check if running from command line
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>Project Monitoring System - Database Setup</h1>";
}

// Include database connection
require_once __DIR__ . '/db.php';

// Include the auto setup functions
require_once __DIR__ . '/includes/auto_setup_database.php';

function output($message, $type = 'info') {
    global $isCLI;
    
    if ($isCLI) {
        $prefix = '';
        switch($type) {
            case 'success': $prefix = '✓ '; break;
            case 'error': $prefix = '✗ '; break;
            case 'warning': $prefix = '⚠ '; break;
        }
        echo $prefix . $message . PHP_EOL;
    } else {
        echo "<p class='$type'>$message</p>";
    }
}

// Start setup
output("Starting database setup...", "info");

try {
    // Check if database is already set up
    if (isDatabaseSetup($pdo)) {
        output("Database is already set up! All tables exist.", "success");
        
        // Check table counts
        output("\nChecking table status:", "info");
        
        $tables = [
            'users' => 'Users',
            'roles' => 'Roles',
            'projects' => 'Projects',
            'project_limits' => 'Project Limits',
            'incidents' => 'Incidents',
            'notifications' => 'Notifications'
        ];
        
        foreach ($tables as $table => $name) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                output("$name table: $count records", "info");
            } catch (Exception $e) {
                output("Error checking $name table: " . $e->getMessage(), "error");
            }
        }
    } else {
        output("Database tables not found. Creating...", "warning");
        
        if (setupDatabase($pdo)) {
            output("Database setup completed successfully!", "success");
            output("All tables have been created.", "success");
            
            // Verify roles were created
            $roleCount = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
            output("Created $roleCount default roles (Admin and User)", "info");
            
            // Check if any users exist
            $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount > 0) {
                output("Found $userCount existing users. First user has been made an Admin.", "info");
            } else {
                output("No users found. The first user to register will automatically become an Admin.", "info");
            }
        } else {
            output("Database setup failed! Check error logs for details.", "error");
        }
    }
    
    // Display connection info
    output("\nDatabase Connection Info:", "info");
    output("Host: " . DB_HOST, "info");
    output("Database: " . DB_NAME, "info");
    output("User: " . DB_USER, "info");
    
    // Security reminder
    output("\n⚠️ IMPORTANT: Delete this setup_database.php file after setup is complete!", "warning");
    
} catch (Exception $e) {
    output("Fatal error: " . $e->getMessage(), "error");
    output("Stack trace: " . $e->getTraceAsString(), "error");
}

if (!$isCLI) {
    echo "</body></html>";
}
?>