<?php
require_once 'db.php';

// Start session to check login status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Database Diagnostic Tool</h1>
    
    <div class="section">
        <h2>1. Database Connection</h2>
        <?php
        try {
            $testConnection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            echo '<p class="success">✓ Database connection successful!</p>';
            echo '<p>Connected to database: <strong>' . DB_NAME . '</strong></p>';
        } catch (PDOException $e) {
            echo '<p class="error">✗ Database connection failed!</p>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            die();
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. Session Information</h2>
        <?php
        if (isset($_SESSION['user_id'])) {
            echo '<p class="success">✓ User is logged in</p>';
            echo '<p>User ID: <strong>' . $_SESSION['user_id'] . '</strong></p>';
            echo '<p>User Name: <strong>' . ($_SESSION['user_name'] ?? 'Not set') . '</strong></p>';
        } else {
            echo '<p class="warning">⚠ User is not logged in</p>';
            echo '<p>Session data: <pre>' . print_r($_SESSION, true) . '</pre></p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. Database Tables</h2>
        <?php
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<p>Found <strong>' . count($tables) . '</strong> tables:</p>';
            echo '<ul>';
            foreach ($tables as $table) {
                echo '<li>' . htmlspecialchars($table) . '</li>';
            }
            echo '</ul>';
        } catch (PDOException $e) {
            echo '<p class="error">Error listing tables: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. Projects Table Structure</h2>
        <?php
        try {
            $stmt = $pdo->query("DESCRIBE projects");
            $columns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            foreach ($columns as $column) {
                echo '<tr>';
                foreach ($column as $value) {
                    echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        } catch (PDOException $e) {
            echo '<p class="error">Projects table not found or error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Users in Database</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT id, full_name, email, created_at FROM users");
            $users = $stmt->fetchAll();
            
            if (count($users) > 0) {
                echo '<p>Found <strong>' . count($users) . '</strong> users:</p>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Created</th></tr>';
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td>' . $user['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($user['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                    echo '<td>' . $user['created_at'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="warning">No users found in database</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error querying users: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>6. Existing Projects</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT p.*, u.full_name as owner_name 
                                FROM projects p 
                                LEFT JOIN users u ON p.user_id = u.id");
            $projects = $stmt->fetchAll();
            
            if (count($projects) > 0) {
                echo '<p>Found <strong>' . count($projects) . '</strong> projects:</p>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>URL</th><th>Owner</th><th>Created</th></tr>';
                foreach ($projects as $project) {
                    echo '<tr>';
                    echo '<td>' . $project['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($project['project_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($project['project_url'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($project['owner_name'] ?? 'Unknown (ID: ' . $project['user_id'] . ')') . '</td>';
                    echo '<td>' . $project['date_created'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="success">No projects found in database (table is empty)</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error querying projects: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>7. Test Project Creation</h2>
        <?php
        if (isset($_SESSION['user_id'])) {
            echo '<p>Testing project creation with user ID: <strong>' . $_SESSION['user_id'] . '</strong></p>';
            
            // Test if we can insert a project
            try {
                $testProjectName = 'Test Project ' . date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO projects (user_id, project_name, project_url, description) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_SESSION['user_id'],
                    $testProjectName,
                    'https://test.example.com',
                    'This is a test project created by the diagnostic tool'
                ]);
                
                if ($result) {
                    $lastId = $pdo->lastInsertId();
                    echo '<p class="success">✓ Successfully created test project with ID: ' . $lastId . '</p>';
                    
                    // Now delete it
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$lastId]);
                    echo '<p>Test project deleted.</p>';
                } else {
                    echo '<p class="error">Failed to create test project</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="error">Error creating test project: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p>Error code: ' . $e->getCode() . '</p>';
                echo '<p>SQLSTATE: ' . $e->errorInfo[0] . '</p>';
            }
        } else {
            echo '<p class="warning">Cannot test project creation - no user logged in</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>8. Foreign Key Constraints</h2>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT 
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "'
                    AND TABLE_NAME = 'projects'
            ");
            $constraints = $stmt->fetchAll();
            
            if (count($constraints) > 0) {
                echo '<p>Foreign key constraints on projects table:</p>';
                echo '<table>';
                echo '<tr><th>Column</th><th>Constraint Name</th><th>References Table</th><th>References Column</th></tr>';
                foreach ($constraints as $constraint) {
                    echo '<tr>';
                    echo '<td>' . $constraint['COLUMN_NAME'] . '</td>';
                    echo '<td>' . $constraint['CONSTRAINT_NAME'] . '</td>';
                    echo '<td>' . $constraint['REFERENCED_TABLE_NAME'] . '</td>';
                    echo '<td>' . $constraint['REFERENCED_COLUMN_NAME'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No foreign key constraints found</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error checking constraints: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>Recommendations</h2>
        <ul>
            <li>Make sure you are logged in before trying to create a project</li>
            <li>Check that the database connection settings in db.php are correct</li>
            <li>Ensure all required tables have been created using db.sql</li>
            <li>If you've made changes to the database schema, run db_update.sql</li>
            <li>Check the web server error logs for more details</li>
        </ul>
    </div>
</body>
</html>