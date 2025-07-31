<?php
require_once 'db.php';
requireLogin();

// Get project ID
$projectId = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$projectId) {
    header("Location: dashboard.php");
    exit();
}

// Verify project belongs to user
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $status = sanitizeInput($_POST['status'] ?? '');
    $rootCause = sanitizeInput($_POST['root_cause'] ?? '');
    $startedAt = sanitizeInput($_POST['started_at'] ?? '');
    $duration = sanitizeInput($_POST['duration'] ?? '');
    
    // Validation
    if (!in_array($status, ['Open', 'Resolved'])) {
        $errors[] = "Invalid status selected.";
    }
    
    if (empty($rootCause)) {
        $errors[] = "Root cause is required.";
    }
    
    if (empty($startedAt)) {
        $errors[] = "Start time is required.";
    } else {
        // Validate datetime format
        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $startedAt);
        if (!$dateTime) {
            $errors[] = "Invalid date/time format.";
        }
    }
    
    // Create incident if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO incidents (project_id, status, root_cause, started_at, duration) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $projectId,
                $status,
                $rootCause,
                $startedAt,
                $duration ?: null
            ]);
            
            header("Location: project.php?id=" . $projectId);
            exit();
        } catch (PDOException $e) {
            $errors[] = "Failed to create incident. Please try again.";
        }
    }
}

// Set default datetime to current time
$defaultDateTime = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Incident - Project Monitoring System</title>
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
                <li><a href="add_project.php">Add Project</a></li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container main-content">
        <div class="breadcrumb">
            <a href="project.php?id=<?php echo $projectId; ?>">← Back to <?php echo htmlspecialchars($project['project_name']); ?></a>
        </div>
        
        <div class="form-container">
            <h1>Add Incident</h1>
            <p class="form-subtitle">Report an incident for: <strong><?php echo htmlspecialchars($project['project_name']); ?></strong></p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="add_incident.php?project_id=<?php echo $projectId; ?>" class="incident-form">
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="">Select Status</option>
                        <option value="Open" <?php echo ($_POST['status'] ?? '') === 'Open' ? 'selected' : ''; ?>>Open</option>
                        <option value="Resolved" <?php echo ($_POST['status'] ?? '') === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="started_at">Started At *</label>
                    <input type="datetime-local" id="started_at" name="started_at" 
                           value="<?php echo htmlspecialchars($_POST['started_at'] ?? $defaultDateTime); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" 
                           value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>"
                           placeholder="e.g., 2 hours, 30 minutes">
                    <small>Leave empty for ongoing incidents</small>
                </div>
                
                <div class="form-group">
                    <label for="root_cause">Root Cause *</label>
                    <textarea id="root_cause" name="root_cause" rows="5" required
                              placeholder="Describe the root cause of the incident..."><?php echo htmlspecialchars($_POST['root_cause'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Incident</button>
                    <a href="project.php?id=<?php echo $projectId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>