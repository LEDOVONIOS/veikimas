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
    
    // Check project limit
    if (!canAddMoreProjects($_SESSION['user_id'])) {
        $errors[] = getProjectLimitExceededMessage();
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

// Get user info for display
$userId = $_SESSION['user_id'];
$userRole = getUserRole($userId);
$urlCount = getUserUrlCount($userId);
$urlLimit = getUserUrlLimit($userId);
$remainingUrls = getRemainingUrlSlots($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Monitor - Uptime Monitoring System</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Dark Theme CSS -->
    <link rel="stylesheet" href="assets/css/dark-theme.css">
    
    <style>
        body {
            background-color: var(--bg-primary);
        }
        
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .form-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 32px;
            border: 1px solid var(--border-color);
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid var(--border-color);
        }
        
        .url-limit-info {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .url-limit-icon {
            width: 40px;
            height: 40px;
            background-color: var(--bg-secondary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .url-limit-text {
            flex: 1;
        }
        
        .url-limit-text strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 4px;
        }
        
        .url-limit-text span {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .monitoring-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .option-card {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-card:hover {
            border-color: var(--accent);
        }
        
        .option-card.selected {
            border-color: var(--accent);
            background-color: rgba(99, 102, 241, 0.1);
        }
        
        .option-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .option-description {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <nav class="nav-header" style="background-color: var(--bg-secondary); border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <div class="nav-container" style="max-width: 1400px; margin: 0 auto; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div class="nav-brand" style="display: flex; align-items: center; gap: 12px;">
                <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket" style="height: 40px;">
            </div>
            <nav class="nav-menu" style="display: flex; gap: 8px;">
                <a href="dashboard.php" class="nav-item">Monitors</a>
                <a href="add_project.php" class="nav-item active">Add Monitor</a>
                <a href="notifications.php" class="nav-item">Notifications</a>
                <?php if (isAdmin()): ?>
                <a href="admin/users.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </nav>

    <div class="page-container">
        <a href="dashboard.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 12L6 8l4-4"></path>
            </svg>
            Back to monitors
        </a>
        
        <div class="page-header">
            <h1 class="page-title">Add New Monitor</h1>
            <p class="page-subtitle">Monitor your website's uptime and performance</p>
        </div>
        
        <?php if ($remainingUrls > 0): ?>
        <div class="url-limit-info">
            <div class="url-limit-icon">📊</div>
            <div class="url-limit-text">
                <strong>Monitor Limit</strong>
                <span>You're using <?php echo $urlCount; ?> of <?php echo $urlLimit; ?> monitors (<?php echo $remainingUrls; ?> remaining)</span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="add_project.php" class="form-card">
            <div class="form-section">
                <h2 class="section-title">
                    <span>🌐</span>
                    Monitor Details
                </h2>
                
                <div class="form-group">
                    <label for="project_name">Monitor Name *</label>
                    <input type="text" id="project_name" name="project_name" 
                           value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" 
                           placeholder="e.g., My Website"
                           required>
                    <small>A friendly name to identify this monitor</small>
                </div>
                
                <div class="form-group">
                    <label for="project_url">Website URL *</label>
                    <input type="url" id="project_url" name="project_url" 
                           value="<?php echo htmlspecialchars($_POST['project_url'] ?? ''); ?>"
                           placeholder="https://example.com"
                           required>
                    <small>The URL to monitor (must include http:// or https://)</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Add notes about this monitor..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <small>Optional notes or description for this monitor</small>
                </div>
            </div>
            
            <div class="form-section">
                <h2 class="section-title">
                    <span>⚙️</span>
                    Monitoring Settings
                </h2>
                
                <div class="monitoring-options">
                    <div class="option-card selected">
                        <div class="option-title">HTTP(S) Monitor</div>
                        <div class="option-description">Check website availability and response time</div>
                    </div>
                    <div class="option-card">
                        <div class="option-title">SSL Certificate</div>
                        <div class="option-description">Monitor SSL certificate expiry</div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="check_interval">Check Interval</label>
                    <select id="check_interval" name="check_interval">
                        <option value="1">Every 1 minute</option>
                        <option value="5" selected>Every 5 minutes</option>
                        <option value="10">Every 10 minutes</option>
                        <option value="30">Every 30 minutes</option>
                        <option value="60">Every 60 minutes</option>
                    </select>
                    <small>How often to check your website</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Create Monitor</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>