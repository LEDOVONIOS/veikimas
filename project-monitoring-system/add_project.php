<?php
require_once 'db.php';
require_once 'includes/roles.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$errors = [];
$success = false;
$urlLimitMessage = null;

// Debug: Log session info
error_log("Add Project - Session ID: " . session_id());
error_log("Add Project - User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Add Project - User Name: " . ($_SESSION['user_name'] ?? 'not set'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $projectName = sanitizeInput($_POST['project_name'] ?? '');
    $projectUrl = sanitizeInput($_POST['project_url'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    // Validation
    if (empty($projectName)) {
        $errors[] = "Project name is required.";
    }
    
    if (!empty($projectUrl) && !filter_var($projectUrl, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid URL.";
    }
    
    // Check URL limit for non-empty URLs
    if (!empty($projectUrl) && !canAddMoreUrls($_SESSION['user_id'])) {
        $errors[] = getUrlLimitExceededMessage();
        $urlLimitMessage = true;
    }
    
    // Create project if no errors
    if (empty($errors)) {
        try {
            // Debug: Check if user_id exists
            if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                throw new Exception("User ID not found in session. Please log in again.");
            }
            
            $userId = intval($_SESSION['user_id']); // Ensure it's an integer
            
            $stmt = $pdo->prepare("
                INSERT INTO projects (user_id, project_name, project_url, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $projectName,
                $projectUrl ?: null,
                $description ?: null
            ]);
            
            $projectId = $pdo->lastInsertId();
            header("Location: project.php?id=" . $projectId);
            exit();
        } catch (Exception $e) {
            // Handle general exceptions
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            // Log the actual error for debugging
            error_log("Project creation error: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("User ID from session: " . ($_SESSION['user_id'] ?? 'not set'));
            
            // Provide user-friendly error message based on the actual error
            if ($e->getCode() == '23000') {
                // This is typically a foreign key constraint violation
                $errors[] = "Database constraint error. The user account may not exist in the database.";
                $errors[] = "Current user ID: " . ($_SESSION['user_id'] ?? 'not set');
            } else if ($e->getCode() == '42S02') {
                $errors[] = "Database table not found. Please ensure the database is properly set up.";
            } else if ($e->getCode() == 'HY000') {
                $errors[] = "Database error. Please check your database connection settings.";
            } else {
                $errors[] = "Failed to create project. Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
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
            
            <form method="POST" action="add_project.php" class="project-form">
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
        </div>
    </main>
</body>
</html>