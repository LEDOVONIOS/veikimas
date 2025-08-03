<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

$pageTitle = 'Dashboard';
$user = $auth->getUser();
$monitor = new Monitor();

// Get user's projects
if ($auth->isAdmin()) {
    $projects = $db->fetchAllArray(
        "SELECT p.*, u.username, u.email as user_email 
         FROM " . DB_PREFIX . "projects p 
         LEFT JOIN " . DB_PREFIX . "users u ON p.user_id = u.id 
         ORDER BY p.created_at DESC"
    );
} else {
    $projects = $db->fetchAllArray(
        "SELECT * FROM " . DB_PREFIX . "projects 
         WHERE user_id = ? 
         ORDER BY created_at DESC",
        [$user['id']]
    );
}

// Calculate statistics
$totalProjects = count($projects);
$onlineProjects = 0;
$offlineProjects = 0;
$pausedProjects = 0;

foreach ($projects as $project) {
    if ($project['status'] === 'paused') {
        $pausedProjects++;
    } elseif ($project['current_status'] === 'up') {
        $onlineProjects++;
    } elseif ($project['current_status'] === 'down') {
        $offlineProjects++;
    }
}

// Get recent incidents
$recentIncidents = $db->fetchAllArray(
    "SELECT i.*, p.name as project_name, p.url 
     FROM " . DB_PREFIX . "incident_logs i 
     JOIN " . DB_PREFIX . "projects p ON i.project_id = p.id 
     " . ($auth->isAdmin() ? "" : "WHERE p.user_id = " . $user['id']) . "
     ORDER BY i.started_at DESC 
     LIMIT 5"
);

include 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0">
            <i class="fas fa-tachometer-alt"></i> Dashboard
            <?php if (!$auth->isAdmin()): ?>
            <small class="text-muted">(<?php echo $auth->getProjectCount(); ?>/<?php echo $auth->getProjectLimit(); ?> projects)</small>
            <?php endif; ?>
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card status-card up">
            <h5 class="mb-1">Online</h5>
            <h2 class="mb-0"><?php echo $onlineProjects; ?></h2>
            <i class="fas fa-check-circle icon"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card status-card down">
            <h5 class="mb-1">Offline</h5>
            <h2 class="mb-0"><?php echo $offlineProjects; ?></h2>
            <i class="fas fa-times-circle icon"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card status-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <h5 class="mb-1">Paused</h5>
            <h2 class="mb-0"><?php echo $pausedProjects; ?></h2>
            <i class="fas fa-pause-circle icon"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card status-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <h5 class="mb-1">Total</h5>
            <h2 class="mb-0"><?php echo $totalProjects; ?></h2>
            <i class="fas fa-globe icon"></i>
        </div>
    </div>
</div>

<!-- Add Project Button -->
<?php if ($auth->canCreateProject()): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <a href="project-add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Project
        </a>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> You've reached your project limit. 
    Contact <a href="mailto:info@seorocket.lt">info@seorocket.lt</a> to upgrade your plan.
</div>
<?php endif; ?>

<!-- Projects List -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Your Projects
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                    <p class="text-center text-muted my-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i><br>
                        No projects yet. Click "Add New Project" to get started.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Uptime (24h)</th>
                                    <th>Response</th>
                                    <th>Last Check</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                <?php
                                $uptime24h = $monitor->getUptimePercentage($project['id'], 24);
                                $responseStats = $monitor->getResponseTimeStats($project['id'], date('Y-m-d H:i:s', strtotime('-24 hours')));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($project['name']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-link"></i> 
                                            <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank" class="text-muted">
                                                <?php echo htmlspecialchars(substr($project['url'], 0, 40)) . (strlen($project['url']) > 40 ? '...' : ''); ?>
                                            </a>
                                        </small>
                                        <?php if ($auth->isAdmin() && isset($project['username'])): ?>
                                        <br><small class="text-info">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($project['username']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['status'] === 'paused'): ?>
                                            <?php echo getStatusBadge('paused'); ?>
                                        <?php else: ?>
                                            <?php echo getStatusBadge($project['current_status']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo getUptimeColorClass($uptime24h); ?> font-weight-bold">
                                            <?php echo number_format($uptime24h, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($responseStats && $responseStats['avg_time']): ?>
                                            <span class="<?php echo getResponseTimeColorClass($responseStats['avg_time']); ?>">
                                                <?php echo round($responseStats['avg_time']); ?>ms
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['last_check']): ?>
                                            <span class="time-ago" data-timestamp="<?php echo $project['last_check']; ?>">
                                                <?php echo timeAgo($project['last_check']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="project.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           data-toggle="tooltip" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="project-edit.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-warning" 
                                           data-toggle="tooltip" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Incidents -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i> Recent Incidents
            </div>
            <div class="card-body">
                <?php if (empty($recentIncidents)): ?>
                    <p class="text-center text-muted my-3">
                        <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                        No incidents recorded.
                    </p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentIncidents as $incident): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($incident['project_name']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo timeAgo($incident['started_at']); ?>
                                </small>
                            </div>
                            <p class="mb-1 small">
                                <?php if ($incident['ended_at']): ?>
                                    <span class="badge badge-success">Resolved</span>
                                    Duration: <?php echo formatDuration($incident['duration']); ?>
                                <?php else: ?>
                                    <span class="badge badge-danger">Ongoing</span>
                                    Started: <?php echo date('H:i', strtotime($incident['started_at'])); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($incident['reason']): ?>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($incident['reason']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Quick Info
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-user text-primary"></i> 
                        Logged in as: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-shield-alt text-success"></i> 
                        Role: <strong><?php echo ucfirst($user['role']); ?></strong>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-clock text-info"></i> 
                        Last login: <strong><?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Now'; ?></strong>
                    </li>
                    <?php if (!$auth->isAdmin()): ?>
                    <li>
                        <i class="fas fa-chart-pie text-warning"></i> 
                        Project usage: <strong><?php echo $auth->getProjectCount(); ?>/<?php echo $auth->getProjectLimit(); ?></strong>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>