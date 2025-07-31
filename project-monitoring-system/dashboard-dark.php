<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
requireLogin();

// Get all projects for the user
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT is_up FROM uptime_logs WHERE project_id = p.id ORDER BY checked_at DESC LIMIT 1) as current_status,
               (SELECT last_checked FROM projects WHERE id = p.id) as last_checked,
               s.expiry_date as ssl_expiry
        FROM projects p
        LEFT JOIN ssl_certificates s ON p.id = s.project_id
        WHERE p.user_id = ?
        ORDER BY p.date_created DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
    
    // Calculate uptime for each project
    foreach ($projects as &$project) {
        $uptime = calculateUptime($pdo, $project['id'], 30);
        $project['uptime_percentage'] = $uptime ? $uptime['percentage'] : 100;
        
        // Check SSL expiry
        if ($project['ssl_expiry']) {
            $sslExpiry = new DateTime($project['ssl_expiry']);
            $now = new DateTime();
            $project['ssl_days_left'] = $now->diff($sslExpiry)->days;
        } else {
            $project['ssl_days_left'] = null;
        }
    }
    
    $unreadNotifications = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
    
} catch (PDOException $e) {
    $projects = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Dashboard - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/dark-theme.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="dark-nav">
        <div class="dark-container">
            <div class="dark-nav-content">
                <a href="dashboard-dark.php" class="dark-nav-brand">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Project Monitor
                </a>
                <div class="dark-nav-menu">
                    <a href="dashboard-dark.php" class="dark-nav-link">Dashboard</a>
                    <a href="add_project.php" class="dark-nav-link">Add Project</a>
                    <?php if ($unreadNotifications > 0): ?>
                        <a href="notifications.php" class="dark-nav-link" style="position: relative;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span style="position: absolute; top: -4px; right: -4px; background: var(--status-down); color: white; font-size: 0.75rem; padding: 0.125rem 0.375rem; border-radius: 9999px;"><?php echo $unreadNotifications; ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="dark-nav-link">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="dark-container" style="padding-top: 2rem; padding-bottom: 2rem;">
        <!-- Page Header -->
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Monitor Dashboard</h1>
            <p style="color: var(--text-secondary);">Track and manage all your monitored projects</p>
        </div>

        <!-- Quick Stats -->
        <div class="uptime-cards-grid" style="margin-bottom: 3rem;">
            <?php
            $totalProjects = count($projects);
            $upProjects = count(array_filter($projects, function($p) { return $p['current_status'] == 1; }));
            $downProjects = $totalProjects - $upProjects;
            $avgUptime = $totalProjects > 0 ? array_sum(array_column($projects, 'uptime_percentage')) / $totalProjects : 100;
            ?>
            <div class="uptime-card">
                <h3 class="uptime-period">Total Monitors</h3>
                <div class="uptime-percentage perfect"><?php echo $totalProjects; ?></div>
                <div class="uptime-stats">
                    <span class="uptime-stat">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <?php echo $upProjects; ?> up
                    </span>
                    <span class="uptime-stat">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 14l-2-2m0 0l-2-2m2 2l-2 2m2-2h14a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v3a2 2 0 002 2z" />
                        </svg>
                        <?php echo $downProjects; ?> down
                    </span>
                </div>
            </div>
            
            <div class="uptime-card">
                <h3 class="uptime-period">Average Uptime</h3>
                <div class="uptime-percentage <?php echo $avgUptime >= 99 ? 'perfect' : ($avgUptime >= 95 ? 'warning' : 'critical'); ?>">
                    <?php echo number_format($avgUptime, 2); ?>%
                </div>
                <div class="uptime-stats">
                    <span class="uptime-stat">Last 30 days</span>
                </div>
            </div>
            
            <div class="uptime-card">
                <h3 class="uptime-period">Active Incidents</h3>
                <div class="uptime-percentage <?php echo $downProjects > 0 ? 'critical' : 'perfect'; ?>">
                    <?php echo $downProjects; ?>
                </div>
                <div class="uptime-stats">
                    <span class="uptime-stat">Requires attention</span>
                </div>
            </div>
        </div>

        <!-- Monitors List -->
        <div class="monitors-list">
            <?php if (empty($projects)): ?>
                <div class="empty-state" style="padding: 4rem 2rem;">
                    <div class="empty-state-icon">📊</div>
                    <div class="empty-state-text">No monitors configured yet</div>
                    <div class="empty-state-subtext">Add your first project to start monitoring</div>
                    <a href="add_project.php" class="btn-export" style="margin-top: 1.5rem; display: inline-flex;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 4v16m8-8H4" />
                        </svg>
                        Add Project
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): 
                    $isUp = $project['current_status'] == 1;
                ?>
                <div class="monitor-row">
                    <div class="monitor-status-indicator <?php echo $isUp ? 'up' : 'down'; ?>"></div>
                    
                    <div class="monitor-list-info">
                        <a href="project-dark.php?id=<?php echo $project['id']; ?>" class="monitor-list-name">
                            <?php echo htmlspecialchars($project['project_name']); ?>
                        </a>
                        <span class="monitor-list-url"><?php echo htmlspecialchars($project['project_url']); ?></span>
                    </div>
                    
                    <div style="display: flex; align-items: center;">
                        <div class="monitor-uptime-bar">
                            <div class="monitor-uptime-fill" style="width: <?php echo $project['uptime_percentage']; ?>%"></div>
                        </div>
                        <span style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?php echo number_format($project['uptime_percentage'], 1); ?>%
                        </span>
                    </div>
                    
                    <?php if ($project['ssl_days_left'] !== null && $project['ssl_days_left'] < 30): ?>
                        <div class="monitor-ssl-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            SSL: <?php echo $project['ssl_days_left']; ?>d
                        </div>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    
                    <div class="monitor-interval">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 4px;">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Every 1 min
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Add Project Button -->
        <?php if (!empty($projects)): ?>
        <div style="margin-top: 2rem; text-align: center;">
            <a href="add_project.php" class="btn-export">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 4v16m8-8H4" />
                </svg>
                Add New Monitor
            </a>
        </div>
        <?php endif; ?>
    </main>

    <style>
        /* Quick fix for mobile responsiveness */
        @media (max-width: 768px) {
            .monitor-row {
                padding: var(--spacing-md);
                grid-template-columns: auto 1fr;
                gap: var(--spacing-sm);
            }
            
            .monitor-row > div:nth-child(3),
            .monitor-row > div:nth-child(4),
            .monitor-row > div:nth-child(5) {
                display: none;
            }
            
            .monitor-list-info {
                grid-column: 2;
            }
        }
    </style>
</body>
</html>