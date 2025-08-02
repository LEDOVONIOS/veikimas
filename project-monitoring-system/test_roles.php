<?php
/**
 * Test script for roles system
 * This file helps verify that the roles system is properly set up
 */

require_once 'db.php';
require_once 'includes/roles.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Roles System Test</h1>";

try {
    // Test 1: Check if roles table exists
    echo "<h2>Test 1: Checking roles table</h2>";
    $stmt = $pdo->query("SELECT * FROM roles");
    $roles = $stmt->fetchAll();
    
    if (count($roles) > 0) {
        echo "✅ Roles table exists with " . count($roles) . " roles:<br>";
        foreach ($roles as $role) {
            echo "- " . $role['name'] . ": " . $role['description'] . "<br>";
        }
    } else {
        echo "❌ No roles found. Please run db_roles_update.sql<br>";
    }
    
    // Test 2: Check if url_limits table exists
    echo "<h2>Test 2: Checking url_limits table</h2>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM url_limits");
        echo "✅ url_limits table exists<br>";
    } catch (PDOException $e) {
        echo "❌ url_limits table not found. Please run db_roles_update.sql<br>";
    }
    
    // Test 3: Check users with roles
    echo "<h2>Test 3: Sample users with roles</h2>";
    $stmt = $pdo->query("
        SELECT u.id, u.email, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        LIMIT 5
    ");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . ($user['role_name'] ?: 'No role assigned') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "No users found<br>";
    }
    
    // Test 4: Test role functions
    echo "<h2>Test 4: Testing role functions</h2>";
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        echo "Current user ID: " . $userId . "<br>";
        echo "Is Admin: " . (isAdmin($userId) ? 'Yes' : 'No') . "<br>";
        echo "Is Customer: " . (isCustomer($userId) ? 'Yes' : 'No') . "<br>";
        echo "URL Count: " . getUserUrlCount($userId) . "<br>";
        echo "URL Limit: " . getUserUrlLimit($userId) . "<br>";
        echo "Can add more URLs: " . (canAddMoreUrls($userId) ? 'Yes' : 'No') . "<br>";
    } else {
        echo "No user logged in. <a href='login.php'>Login</a> to test user-specific functions.<br>";
    }
    
    // Test 5: Check user_url_count view
    echo "<h2>Test 5: Checking user_url_count view</h2>";
    try {
        $stmt = $pdo->query("SELECT * FROM user_url_count LIMIT 3");
        $counts = $stmt->fetchAll();
        
        if (count($counts) > 0) {
            echo "✅ user_url_count view exists. Sample data:<br>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>User ID</th><th>Email</th><th>Role</th><th>URL Count</th><th>URL Limit</th></tr>";
            foreach ($counts as $count) {
                echo "<tr>";
                echo "<td>" . $count['user_id'] . "</td>";
                echo "<td>" . $count['email'] . "</td>";
                echo "<td>" . $count['role_name'] . "</td>";
                echo "<td>" . $count['url_count'] . "</td>";
                echo "<td>" . $count['url_limit'] . "</td>";
                echo "</tr>";
            }
            echo "</table><br>";
        }
    } catch (PDOException $e) {
        echo "❌ user_url_count view not found. Please run db_roles_update.sql<br>";
    }
    
    echo "<h2>Summary</h2>";
    echo "<p>If all tests pass (✅), your roles system is properly set up!</p>";
    echo "<p>If any tests fail (❌), please run the SQL migration script: db_roles_update.sql</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<a href='dashboard.php'>Back to Dashboard</a> | ";
echo "<a href='admin/manage-users.php'>Admin Panel</a> | ";
echo "<a href='scripts/make_admin.php?key=your-secret-key-here'>Make Admin Tool</a>";
?>