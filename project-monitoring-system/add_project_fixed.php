<?php
require_once 'db.php';
require_once 'includes/roles.php';
requireLogin();

$errors = [];
$success = false;
$urlLimitMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $projectName = sanitizeInput($_POST['project_name'] ?? '');
    $projectUrl = sanitizeInput($_POST['project_url'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $serverLocation = sanitizeInput($_POST['server_location'] ?? '');
    $monitoringRegion = sanitizeInput($_POST['monitoring_region'] ?? 'North America');
    
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
            // First, check which columns exist in the projects table
            $stmt = $pdo->prepare("SHOW COLUMNS FROM projects");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build dynamic INSERT query based on available columns
            $insertColumns = ['user_id', 'project_name', 'project_url', 'description'];
            $insertValues = [$_SESSION['user_id'], $projectName, $projectUrl ?: null, $description ?: null];
            
            // Add optional columns if they exist
            if (in_array('server_location', $columns)) {
                $insertColumns[] = 'server_location';
                $insertValues[] = $serverLocation ?: null;
            }
            
            if (in_array('monitoring_region', $columns)) {
                $insertColumns[] = 'monitoring_region';
                $insertValues[] = $monitoringRegion;
            }
            
            if (in_array('status', $columns)) {
                $insertColumns[] = 'status';
                $insertValues[] = 'active';
            }
            
            // Build the query
            $placeholders = array_fill(0, count($insertColumns), '?');
            $query = "INSERT INTO projects (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($insertValues);
            
            $projectId = $pdo->lastInsertId();
            
            // Determine which project view to redirect to
            $redirectUrl = file_exists('project-dark.php') ? "project-dark.php" : "project.php";
            header("Location: " . $redirectUrl . "?id=" . $projectId);
            exit();
        } catch (PDOException $e) {
            // Provide more detailed error message
            if ($e->getCode() == '42S22') {
                $errors[] = "Database schema issue detected. Please ensure db_update.sql has been run.";
                $errors[] = "Technical details: " . $e->getMessage();
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
    <?php if (file_exists('assets/css/dark-theme.css')): ?>
        <link rel="stylesheet" href="assets/css/dark-theme.css">
    <?php else: ?>
        <link rel="stylesheet" href="assets/css/style.css">
    <?php endif; ?>
</head>
<body>
    <nav class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav' : 'navbar'; ?>">
        <div class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-container' : 'container'; ?>">
            <div class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-content' : ''; ?>">
                <div class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-brand' : 'nav-brand'; ?>">
                    <h2>Project Monitor</h2>
                </div>
                <ul class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-menu' : 'nav-menu'; ?>">
                    <li><a href="dashboard.php" class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-link' : ''; ?>">Home</a></li>
                    <li><a href="add_project.php" class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-link' : 'active'; ?>">Add Project</a></li>
                    <li class="nav-user">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="logout.php" class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-nav-link' : 'btn-logout'; ?>">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="<?php echo file_exists('assets/css/dark-theme.css') ? 'dark-container' : 'container'; ?> main-content">
        <div class="form-container" <?php echo file_exists('assets/css/dark-theme.css') ? 'style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);"' : ''; ?>>
            <h1>Add New Project</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="add_project_fixed.php" class="project-form">
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
                    <label for="server_location">Server Location</label>
                    <input type="text" id="server_location" name="server_location" 
                           value="<?php echo htmlspecialchars($_POST['server_location'] ?? ''); ?>"
                           placeholder="e.g., US East, Europe, Singapore">
                    <small>Optional: Geographic location of your server</small>
                </div>
                
                <div class="form-group">
                    <label for="monitoring_region">Monitoring Region</label>
                    <select id="monitoring_region" name="monitoring_region">
                        <option value="North America" <?php echo ($_POST['monitoring_region'] ?? '') === 'North America' ? 'selected' : ''; ?>>North America</option>
                        <option value="Europe" <?php echo ($_POST['monitoring_region'] ?? '') === 'Europe' ? 'selected' : ''; ?>>Europe</option>
                        <option value="Asia Pacific" <?php echo ($_POST['monitoring_region'] ?? '') === 'Asia Pacific' ? 'selected' : ''; ?>>Asia Pacific</option>
                        <option value="South America" <?php echo ($_POST['monitoring_region'] ?? '') === 'South America' ? 'selected' : ''; ?>>South America</option>
                        <option value="Africa" <?php echo ($_POST['monitoring_region'] ?? '') === 'Africa' ? 'selected' : ''; ?>>Africa</option>
                    </select>
                    <small>Region from which uptime will be monitored</small>
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
            
            <div style="margin-top: 2rem; padding: 1rem; background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 0.5rem;">
                <h3 style="margin-bottom: 0.5rem; color: #3b82f6;">Database Setup Required?</h3>
                <p style="margin-bottom: 0.5rem;">If you're getting errors, make sure you've run the database update script:</p>
                <code style="display: block; padding: 0.5rem; background-color: rgba(0,0,0,0.2); border-radius: 0.25rem;">
                    mysql -u your_username -p your_database < db_update.sql
                </code>
            </div>
        </div>
    </main>
</body>
</html>