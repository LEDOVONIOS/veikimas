<?php
/**
 * Fix Database Error Script
 * This script creates the missing project_limits table to resolve the error:
 * "SQLSTATE[42S02]: Base table or view not found: 1146 project_limits' doesn't exist"
 */

require_once 'db.php';

try {
    echo "Starting database fix...\n\n";
    
    // Check if project_limits table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'project_limits'");
    if ($checkTable->rowCount() > 0) {
        echo "✓ Table 'project_limits' already exists.\n";
    } else {
        echo "✗ Table 'project_limits' is missing. Creating it now...\n";
        
        // Create the project_limits table
        $createTableSQL = "
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($createTableSQL);
        echo "✓ Table 'project_limits' created successfully.\n";
        
        // Add default project limits for existing users
        $insertDefaultLimits = "
            INSERT IGNORE INTO `project_limits` (`user_id`, `max_projects`)
            SELECT `id`, 10 FROM `users`
            WHERE `id` NOT IN (SELECT `user_id` FROM `project_limits`)
        ";
        
        $affectedRows = $pdo->exec($insertDefaultLimits);
        echo "✓ Added default project limits for $affectedRows existing users.\n";
    }
    
    // Test the getUserProjectLimit function
    echo "\nTesting the getUserProjectLimit function...\n";
    require_once 'includes/roles.php';
    
    // Get all users to test
    $users = $pdo->query("SELECT id, username FROM users LIMIT 5")->fetchAll();
    
    if (count($users) > 0) {
        foreach ($users as $user) {
            $limit = getUserProjectLimit($user['id']);
            echo "- User '{$user['username']}' (ID: {$user['id']}): Project limit = $limit\n";
        }
        echo "\n✓ Function is working correctly!\n";
    } else {
        echo "No users found in the database to test with.\n";
    }
    
    echo "\n✅ Database fix completed successfully!\n";
    echo "\nYou can now use the application without the database error.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\nPlease make sure:\n";
    echo "1. The database connection details in db.php are correct\n";
    echo "2. The 'users' table exists in your database\n";
    echo "3. You have the necessary database permissions\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}