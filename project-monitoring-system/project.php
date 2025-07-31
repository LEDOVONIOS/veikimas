<?php
require_once 'db.php';
requireLogin();

// Get project ID
$projectId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$projectId) {
    header("Location: dashboard.php");
    exit();
}

// Get project details
try {
    $stmt = $pdo->prepare("
        SELECT * FROM projects 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get incidents for this project
    $stmt = $pdo->prepare("
        SELECT * FROM incidents 
        WHERE project_id = ?
        ORDER BY started_at DESC
    ");
    $stmt->execute([$projectId]);
    $incidents = $stmt->fetchAll();
    
} catch (PDOException $e) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['project_name']); ?> - Project Monitoring System</title>
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
            <a href="dashboard.php">← Back to Projects</a>
        </div>
        
        <div class="project-detail">
            <div class="project-detail-header">
                <h1><?php echo htmlspecialchars($project['project_name']); ?></h1>
                <?php
                $openIncidents = array_filter($incidents, function($inc) {
                    return $inc['status'] === 'Open';
                });
                ?>
                <?php if (count($openIncidents) > 0): ?>
                    <span class="status-badge status-critical large">
                        <?php echo count($openIncidents); ?> Open Incident<?php echo count($openIncidents) > 1 ? 's' : ''; ?>
                    </span>
                <?php else: ?>
                    <span class="status-badge status-operational large">
                        Operational
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="project-info">
                <?php if (!empty($project['project_url'])): ?>
                    <div class="info-item">
                        <strong>URL:</strong> 
                        <a href="<?php echo htmlspecialchars($project['project_url']); ?>" 
                           target="_blank" rel="noopener">
                            <?php echo htmlspecialchars($project['project_url']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <strong>Created:</strong> 
                    <?php echo date('F d, Y g:i A', strtotime($project['date_created'])); ?>
                </div>
                
                <?php if (!empty($project['description'])): ?>
                    <div class="info-item">
                        <strong>Description:</strong>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="incidents-section">
            <div class="section-header">
                <h2>Incidents</h2>
                <a href="add_incident.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                    <span class="btn-icon">+</span> Add Incident
                </a>
            </div>
            
            <?php if (empty($incidents)): ?>
                <div class="empty-state">
                    <p>No incidents recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="incidents-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Started At</th>
                                <th>Duration</th>
                                <th>Root Cause</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td>
                                        <?php if ($incident['status'] === 'Open'): ?>
                                            <span class="status-indicator status-open"></span>
                                            <span class="status-text">Open</span>
                                        <?php else: ?>
                                            <span class="status-indicator status-resolved"></span>
                                            <span class="status-text">Resolved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($incident['started_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($incident['duration'] ?: 'Ongoing'); ?></td>
                                    <td class="root-cause"><?php echo htmlspecialchars($incident['root_cause']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>