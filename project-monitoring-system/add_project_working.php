<?php
require_once 'db.php';
require_once 'includes/roles.php';

// Double-check session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = false;
$urlLimitMessage = null;
$debug = []; // Debug information

// Collect debug info
$debug['session_id'] = session_id();
$debug['user_id'] = $_SESSION['user_id'] ?? 'not set';
$debug['user_name'] = $_SESSION['user_name'] ?? 'not set';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data (without sanitizeInput which might cause issues)
    $projectName = trim($_POST['project_name'] ?? '');
    $projectUrl = trim($_POST['project_url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($projectName)) {
        $errors[] = "Project name is required.";
    }
    
    if (!empty($projectUrl)) {
        // Clean up URL
        if (!preg_match('/^https?:\/\//', $projectUrl)) {
            $projectUrl = 'https://' . $projectUrl;
        }
        if (!filter_var($projectUrl, FILTER_VALIDATE_URL)) {
            $errors[] = "Please enter a valid URL.";
        }
    }
    
    // Check URL limit for non-empty URLs
    if (!empty($projectUrl) && !canAddMoreUrls($_SESSION['user_id'])) {
        $errors[] = getUrlLimitExceededMessage();
        $urlLimitMessage = true;
    }
    
    // Create project if no errors
    if (empty($errors)) {
        try {
            // Verify user exists in database
            $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User account not found in database. User ID: " . $_SESSION['user_id']);
            }
            
            $debug['user_found'] = 'Yes';
            $debug['user_db_id'] = $user['id'];
            
            // Insert project
            $stmt = $pdo->prepare("
                INSERT INTO projects (user_id, project_name, project_url, description, date_created) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $user['id'], // Use the verified user ID from database
                $projectName,
                empty($projectUrl) ? null : $projectUrl,
                empty($description) ? null : $description
            ]);
            
            if ($result) {
                $projectId = $pdo->lastInsertId();
                
                // Redirect to appropriate project view
                if (file_exists('project-dark.php')) {
                    header("Location: project-dark.php?id=" . $projectId);
                } else {
                    header("Location: project.php?id=" . $projectId);
                }
                exit();
            } else {
                $errors[] = "Failed to insert project into database.";
            }
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            $debug['exception'] = $e->getMessage();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            $debug['pdo_error'] = $e->getMessage();
            $debug['pdo_code'] = $e->getCode();
            $debug['sqlstate'] = $e->errorInfo[0] ?? 'unknown';
        }
    }
}

// Check if projects table exists and has correct structure
try {
    $stmt = $pdo->query("DESCRIBE projects");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $debug['projects_table'] = 'exists';
    $debug['columns'] = implode(', ', $columns);
} catch (PDOException $e) {
    $debug['projects_table'] = 'not found';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .debug-info {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 10px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .debug-info h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Project Monitor</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Home</a></li>
                <li><a href="add_project.php" class="active">Add Project</a></li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container main-content">
        <div class="form-container">
            <h1>Add New Project</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug Information (remove in production) -->
            <div class="debug-info">
                <h3>Debug Information:</h3>
                <?php foreach ($debug as $key => $value): ?>
                    <p><strong><?php echo $key; ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                <?php endforeach; ?>
            </div>
            
            <form method="POST" action="add_project_working.php" class="project-form">
                <div class="form-group">
                    <label for="project_name">Project Name *</label>
                    <input type="text" id="project_name" name="project_name" 
                           value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="project_url">Project URL</label>
                    <input type="url" id="project_url" name="project_url" 
                           value="<?php echo htmlspecialchars($_POST['project_url'] ?? ''); ?>"
                           placeholder="https://example.com">
                    <small>Optional: The URL of your project website</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"
                              placeholder="Describe your project..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Project</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                <h3>Troubleshooting Tips:</h3>
                <ol>
                    <li>Make sure you're logged in (check User ID in debug info)</li>
                    <li>Ensure your user account exists in the database</li>
                    <li>Verify the projects table has been created</li>
                    <li>Check that foreign key constraints are properly set up</li>
                </ol>
                <p><strong>Run the diagnostic tool:</strong> <a href="test_db_connection.php">test_db_connection.php</a></p>
            </div>
        </div>
    </main>
</body>
</html>