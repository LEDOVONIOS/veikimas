<?php
require_once 'db.php';
require_once 'includes/roles.php';
requireLogin();

// Get user role and URL information
$userId = $_SESSION['user_id'];
$userRole = getUserRole($userId);
$urlCount = getUserUrlCount($userId);
$urlLimit = getUserUrlLimit($userId);
$remainingUrls = getRemainingUrlSlots($userId);

// Get user's projects
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(DISTINCT i.id) as incident_count,
               COUNT(DISTINCT CASE WHEN i.status = 'Open' THEN i.id END) as open_incidents
        FROM projects p
        LEFT JOIN incidents i ON p.id = i.project_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY p.date_created DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    $projects = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Project Monitor</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">Home</a></li>
                <li><a href="add_project.php">Add Project</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/manage-users.php">Manage Users</a></li>
                <?php endif; ?>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (<?php echo $userRole['name']; ?>)</span>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container main-content">
        <div class="page-header">
            <h1>My Projects</h1>
            <a href="add_project.php" class="btn btn-primary">
                <span class="btn-icon">+</span> Add New Project
            </a>
        </div>
        
        <?php if ($userRole['name'] === 'Customer'): ?>
            <div class="url-limit-info" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <p style="margin: 0;">
                    <strong>URL Limit:</strong> You have used <?php echo $urlCount; ?> out of <?php echo $urlLimit; ?> URLs. 
                    <?php if ($remainingUrls > 0): ?>
                        (<?php echo $remainingUrls; ?> remaining)
                    <?php else: ?>
                        <span style="color: #dc3545;">(Limit reached)</span>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <h3>No projects yet</h3>
                <p>Start monitoring your projects by creating your first one.</p>
                <a href="add_project.php" class="btn btn-primary">Create Project</a>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-header">
                            <h3>
                                <a href="project.php?id=<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                </a>
                            </h3>
                            <div class="project-status">
                                <?php if ($project['open_incidents'] > 0): ?>
                                    <span class="status-badge status-critical">
                                        <?php echo $project['open_incidents']; ?> Open
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-operational">
                                        Operational
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($project['project_url'])): ?>
                            <p class="project-url">
                                <a href="<?php echo htmlspecialchars($project['project_url']); ?>" 
                                   target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars($project['project_url']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($project['description'])): ?>
                            <p class="project-description">
                                <?php echo htmlspecialchars(substr($project['description'], 0, 150)); ?>
                                <?php if (strlen($project['description']) > 150): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="project-footer">
                            <span class="project-meta">
                                Total Incidents: <?php echo $project['incident_count']; ?>
                            </span>
                            <span class="project-date">
                                Created: <?php echo date('M d, Y', strtotime($project['date_created'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>