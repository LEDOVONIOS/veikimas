<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
requireLogin();

// Set Lithuanian timezone
date_default_timezone_set('Europe/Vilnius');

// Get user's projects with monitoring data
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM incidents WHERE project_id = p.id AND status = 'Open') as open_incidents,
               (SELECT MAX(checked_at) FROM uptime_checks WHERE project_id = p.id) as last_checked
        FROM projects p 
        WHERE p.user_id = ?
        ORDER BY p.date_created DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
    
    // Calculate statistics for each project
    foreach ($projects as &$project) {
        $project['uptime_30d'] = calculateUptime($pdo, $project['id'], 30);
        $project['ssl_info'] = getSSLInfo($pdo, $project['id']);
        
        // Calculate current status
        $openIncidents = $project['open_incidents'];
        $project['is_up'] = $openIncidents == 0;
    }
    
    // Calculate overall statistics
    $totalProjects = count($projects);
    $projectsUp = count(array_filter($projects, fn($p) => $p['is_up']));
    $totalIncidents = array_sum(array_column($projects, 'open_incidents'));
    
    // Calculate average uptime
    $uptimeSum = 0;
    $uptimeCount = 0;
    foreach ($projects as $project) {
        if (isset($project['uptime_30d']['percentage'])) {
            $uptimeSum += $project['uptime_30d']['percentage'];
            $uptimeCount++;
        }
    }
    $averageUptime = $uptimeCount > 0 ? $uptimeSum / $uptimeCount : 100;
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Helper function to format time in Lithuanian timezone
function formatLithuanianTime($timestamp) {
    if (!$timestamp) return 'Never';
    $dt = new DateTime($timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Vilnius'));
    
    $now = new DateTime('now', new DateTimeZone('Europe/Vilnius'));
    $diff = $now->diff($dt);
    
    if ($diff->days > 0) {
        return $dt->format('Y-m-d H:i');
    } else if ($diff->h > 0) {
        return $diff->h . 'h ago';
    } else if ($diff->i > 0) {
        return $diff->i . 'm ago';
    } else {
        return 'Just now';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uptime Monitor Dashboard</title>
    <link rel="stylesheet" href="assets/css/modern-dark-theme.css">
    <style>
        /* Dashboard specific styles */
        .dashboard-header {
            margin: 2rem 0;
        }
        
        .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        /* Quick stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-value.success {
            color: var(--success);
        }
        
        .stat-value.warning {
            color: var(--warning);
        }
        
        .stat-value.danger {
            color: var(--danger);
        }
        
        /* Monitors list */
        .monitors-section {
            margin: 2rem 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .monitors-list {
            background: var(--bg-card);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        
        .monitor-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            cursor: pointer;
            gap: 1rem;
        }
        
        .monitor-row:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .monitor-row:last-child {
            border-bottom: none;
        }
        
        .monitor-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            position: relative;
        }
        
        .monitor-status-indicator.up {
            background: var(--success);
            box-shadow: 0 0 0 0 rgba(0, 255, 178, 0.4);
            animation: pulse-green 2s infinite;
        }
        
        .monitor-status-indicator.down {
            background: var(--danger);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            animation: pulse-red 2s infinite;
        }
        
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 255, 178, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(0, 255, 178, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 255, 178, 0);
            }
        }
        
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
        
        .monitor-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .monitor-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .monitor-url {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .monitor-uptime {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .uptime-bar {
            width: 100px;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .uptime-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }
        
        .uptime-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            min-width: 60px;
            text-align: right;
        }
        
        .monitor-ssl {
            font-size: 0.75rem;
        }
        
        .ssl-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .ssl-badge.valid {
            background: rgba(0, 255, 178, 0.1);
            color: var(--success);
        }
        
        .ssl-badge.expiring {
            background: rgba(250, 204, 21, 0.1);
            color: var(--warning);
        }
        
        .ssl-badge.expired {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .monitor-check-interval {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .add-monitor-btn {
            background: var(--info);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .add-monitor-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav-modern">
        <div class="container">
            <div class="nav-content">
                <div class="nav-brand">⚡ Uptime Monitor</div>
                <div class="nav-actions">
                    <span style="color: var(--text-secondary); margin-right: 1rem;">
                        Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                    <a href="logout.php" class="btn-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Your Monitors</h1>
            <p class="dashboard-subtitle">Real-time status of all your monitored websites</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-label">Total Monitors</div>
                <div class="stat-value"><?php echo $totalProjects; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Monitors Up</div>
                <div class="stat-value <?php echo $projectsUp == $totalProjects ? 'success' : 'warning'; ?>">
                    <?php echo $projectsUp; ?>/<?php echo $totalProjects; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-label">Average Uptime (30d)</div>
                <div class="stat-value <?php echo $averageUptime >= 99.9 ? 'success' : ($averageUptime >= 99 ? 'warning' : 'danger'); ?>">
                    <?php echo number_format($averageUptime, 2); ?>%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">Active Incidents</div>
                <div class="stat-value <?php echo $totalIncidents > 0 ? 'danger' : 'success'; ?>">
                    <?php echo $totalIncidents; ?>
                </div>
            </div>
        </div>

        <!-- Monitors List -->
        <div class="monitors-section">
            <div class="section-header">
                <h2 class="section-title">All Monitors</h2>
                <a href="add_project.php" class="add-monitor-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Monitor
                </a>
            </div>

            <?php if (empty($projects)): ?>
                <div class="monitors-list">
                    <div class="empty-state">
                        <div class="empty-state-icon">📡</div>
                        <h3>No monitors yet</h3>
                        <p>Start monitoring your websites by adding your first monitor.</p>
                        <a href="add_project.php" class="add-monitor-btn" style="margin-top: 1.5rem;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Your First Monitor
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="monitors-list">
                    <?php foreach ($projects as $project): ?>
                        <div class="monitor-row" onclick="window.location.href='project-modern.php?id=<?php echo $project['id']; ?>'">
                            <div class="monitor-status-indicator <?php echo $project['is_up'] ? 'up' : 'down'; ?>"></div>
                            
                            <div class="monitor-info">
                                <div class="monitor-name"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                <div class="monitor-url"><?php echo htmlspecialchars($project['project_url'] ?? 'No URL'); ?></div>
                            </div>
                            
                            <div class="monitor-uptime">
                                <div class="uptime-bar">
                                    <div class="uptime-fill" style="width: <?php echo $project['uptime_30d']['percentage'] ?? 100; ?>%"></div>
                                </div>
                                <div class="uptime-text"><?php echo number_format($project['uptime_30d']['percentage'] ?? 100, 1); ?>%</div>
                            </div>
                            
                            <div class="monitor-ssl">
                                <?php 
                                $sslDaysLeft = $project['ssl_info']['ssl_days_left'] ?? null;
                                if ($sslDaysLeft !== null):
                                    $sslClass = $sslDaysLeft > 30 ? 'valid' : ($sslDaysLeft > 0 ? 'expiring' : 'expired');
                                ?>
                                    <span class="ssl-badge <?php echo $sslClass; ?>">
                                        SSL: <?php echo $sslDaysLeft; ?>d
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="monitor-check-interval">
                                <span title="Last checked: <?php echo formatLithuanianTime($project['last_checked']); ?>">
                                    Every 1 min
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>