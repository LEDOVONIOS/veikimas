<?php
/**
 * Script to make a user an admin
 * Usage: php make_admin.php email@example.com
 * 
 * This script should be run from the command line or accessed with proper security
 */

require_once dirname(__DIR__) . '/db.php';

// Check if running from command line
$isCLI = php_sapi_name() === 'cli';

if ($isCLI) {
    // Command line usage
    if ($argc !== 2) {
        echo "Usage: php make_admin.php email@example.com\n";
        exit(1);
    }
    $email = $argv[1];
} else {
    // Web usage - add security check
    echo "<h1>Make User Admin</h1>";
    
    // Simple security check - in production, use better authentication
    $secretKey = $_GET['key'] ?? '';
    if ($secretKey !== 'your-secret-key-here') {
        die("Unauthorized access. Please provide the correct key.");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
    } else {
        ?>
        <form method="POST">
            <label>User Email: <input type="email" name="email" required></label>
            <button type="submit">Make Admin</button>
        </form>
        <?php
        exit;
    }
}

if (empty($email)) {
    echo "Error: Email address is required.\n";
    exit(1);
}

try {
    // Get the user
    $userStmt = $pdo->prepare("SELECT id, full_name, role_id FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        echo "Error: User with email '$email' not found.\n";
        exit(1);
    }
    
    // Get the Admin role
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Admin'");
    $roleStmt->execute();
    $adminRole = $roleStmt->fetch();
    
    if (!$adminRole) {
        echo "Error: Admin role not found in database. Please run the migration script first.\n";
        exit(1);
    }
    
    // Update the user's role
    $updateStmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    $updateStmt->execute([$adminRole['id'], $user['id']]);
    
    echo "Success: User '{$user['full_name']}' ({$email}) has been made an admin.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

if (!$isCLI) {
    echo "<br><a href='../dashboard.php'>Go to Dashboard</a>";
}
?>