<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/notification_handler.php';
requireLogin();

// Get project ID and time filter
$projectId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$timeRange = $_GET['range'] ?? '24h';

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
    
    // Get monitoring data
    $statusCodeData = getRealStatusCodeData($pdo, $projectId, 7);
    $uptime7Days = calculateUptime($pdo, $projectId, 7);
    $uptime30Days = calculateUptime($pdo, $projectId, 30);
    $uptime365Days = calculateUptime($pdo, $projectId, 365);
    $sslInfo = getSSLInfo($pdo, $projectId);
    $latestIncidents = getLatestIncidents($pdo, $projectId, 5);
    $responseTimeData = getResponseTimeStats($pdo, $projectId);
    $performanceMetrics = getPerformanceMetrics($pdo, $projectId);
    $lastChecked = getLastChecked($pdo, $projectId);
    
    // Get response time data based on time range
    $responseHours = match($timeRange) {
        '1h' => 1,
        '24h' => 24,
        '7d' => 168,
        default => 24
    };
    $responseTimeData = getResponseTimeStats($pdo, $projectId, $responseHours);
    
    $cronJobs = getCronJobs($pdo, $projectId);
    $lastChecked = getLastChecked($pdo, $projectId);
    $unreadNotifications = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
    
    // Calculate uptime duration
    $stmt = $pdo->prepare("
        SELECT MIN(checked_at) as first_check,
               MAX(CASE WHEN is_up = 1 THEN checked_at END) as last_up_check
        FROM uptime_logs
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $uptimeInfo = $stmt->fetch();
    
    $uptimeDuration = '';
    if ($uptimeInfo && $uptimeInfo['last_up_check']) {
        $lastUp = new DateTime($uptimeInfo['last_up_check']);
        $now = new DateTime();
        $diff = $now->diff($lastUp);
        $uptimeDuration = sprintf('%dd %dh %dm', $diff->days, $diff->h, $diff->i);
    }
    
    // Get recent incidents
    $stmt = $pdo->prepare("
        SELECT * FROM incidents 
        WHERE project_id = ?
        ORDER BY started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$projectId]);
    $incidents = $stmt->fetchAll();
    
    // Check current status
    $stmt = $pdo->prepare("
        SELECT is_up FROM uptime_logs 
        WHERE project_id = ? 
        ORDER BY checked_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$projectId]);
    $currentStatus = $stmt->fetch();
    $isUp = $currentStatus ? $currentStatus['is_up'] : true;
    
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
    <title><?php echo htmlspecialchars($project['project_name']); ?> - Monitoring Dashboard</title>
    <link rel="stylesheet" href="assets/css/dark-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="dark-nav">
        <div class="dark-container">
            <div class="dark-nav-content">
                <a href="dashboard.php" class="dark-nav-brand">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Project Monitor
                </a>
                <div class="dark-nav-menu">
                    <a href="dashboard.php" class="dark-nav-link">Dashboard</a>
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
        <!-- Top Header Block -->
        <div class="monitor-header">
            <div class="monitor-header-content">
                <div class="monitor-header-info">
                    <h1 class="monitor-name">
                        <?php echo htmlspecialchars($project['project_name']); ?>
                        <span class="status-pill <?php echo $isUp ? 'up' : 'down'; ?>">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                                <circle cx="4" cy="4" r="4" />
                            </svg>
                            <?php echo $isUp ? 'Up' : 'Down'; ?>
                        </span>
                    </h1>
                    <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" class="monitor-url">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <?php echo htmlspecialchars($project['project_url']); ?>
                    </a>
                    <?php if ($uptimeDuration && $isUp): ?>
                        <p class="monitor-uptime-duration">Up for <?php echo $uptimeDuration; ?></p>
                    <?php endif; ?>
                </div>
                <div class="monitor-actions">
                    <button class="action-button" title="Test Notification">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>
                    <button class="action-button" title="Pause Monitoring">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                    <button class="action-button" title="Edit" onclick="window.location.href='edit_project.php?id=<?php echo $projectId; ?>'">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Uptime Overview Cards -->
        <div class="uptime-cards-grid">
            <?php
            $uptimeData = [
                ['period' => 'LAST 7 DAYS', 'data' => $uptime7Days, 'days' => 7],
                ['period' => 'LAST 30 DAYS', 'data' => $uptime30Days, 'days' => 30],
                ['period' => 'LAST 365 DAYS', 'data' => $uptime365Days, 'days' => 365]
            ];
            
            foreach ($uptimeData as $item):
                $data = $item['data'];
                $percentage = $data ? $data['percentage'] : 100;
                $incidents = $data ? $data['incidents'] : 0;
                $downtime = $data ? $data['downtime'] : '0m';
                
                $percentageClass = 'perfect';
                if ($percentage < 99) $percentageClass = 'warning';
                if ($percentage < 95) $percentageClass = 'critical';
                
                // Generate mini chart data
                $chartBars = [];
                for ($i = 0; $i < 20; $i++) {
                    $isUp = rand(0, 100) > 2; // 98% uptime simulation
                    $chartBars[] = ['height' => rand(60, 100), 'up' => $isUp];
                }
            ?>
            <div class="uptime-card">
                <h3 class="uptime-period"><?php echo $item['period']; ?></h3>
                <div class="uptime-percentage <?php echo $percentageClass; ?>">
                    <?php echo number_format($percentage, 1); ?>%
                </div>
                <div class="uptime-stats">
                    <div class="uptime-stat">
                        <span class="uptime-stat-value"><?php echo $incidents; ?></span> incident<?php echo $incidents !== 1 ? 's' : ''; ?>
                    </div>
                    <div class="uptime-stat">
                        <span class="uptime-stat-value"><?php echo $downtime; ?></span> down
                    </div>
                </div>
                <div class="uptime-mini-chart">
                    <?php foreach ($chartBars as $bar): ?>
                        <div class="uptime-bar <?php echo !$bar['up'] ? 'down' : ''; ?>" 
                             style="height: <?php echo $bar['height']; ?>%"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="monitor-layout">
            <!-- Main Content -->
            <div class="monitor-main">
                <!-- Response Time Graph -->
                <div class="response-time-section">
                    <div class="section-header">
                        <h2 class="section-title">Response Time</h2>
                        <div class="time-range-selector">
                            <button class="time-range-option <?php echo $timeRange === '1h' ? 'active' : ''; ?>" 
                                    onclick="window.location.href='?id=<?php echo $projectId; ?>&range=1h'">
                                Last hour
                            </button>
                            <button class="time-range-option <?php echo $timeRange === '24h' ? 'active' : ''; ?>" 
                                    onclick="window.location.href='?id=<?php echo $projectId; ?>&range=24h'">
                                24 hours
                            </button>
                            <button class="time-range-option <?php echo $timeRange === '7d' ? 'active' : ''; ?>" 
                                    onclick="window.location.href='?id=<?php echo $projectId; ?>&range=7d'">
                                7 days
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($responseTimeData && count($responseTimeData) > 0): ?>
                    <div class="chart-container">
                        <canvas id="responseTimeChart"></canvas>
                    </div>
                    
                    <?php
                    $allResponseTimes = array_column($responseTimeData, 'avg_time');
                    $avgResponseTime = count($allResponseTimes) > 0 ? round(array_sum($allResponseTimes) / count($allResponseTimes)) : 0;
                    $minResponseTime = count($allResponseTimes) > 0 ? round(min($allResponseTimes)) : 0;
                    $maxResponseTime = count($allResponseTimes) > 0 ? round(max($allResponseTimes)) : 0;
                    ?>
                    
                    <div class="response-stats-grid">
                        <div class="response-stat">
                            <div class="response-stat-label">Average</div>
                            <div class="response-stat-value"><?php echo $avgResponseTime; ?>ms</div>
                        </div>
                        <div class="response-stat">
                            <div class="response-stat-label">Minimum</div>
                            <div class="response-stat-value"><?php echo $minResponseTime; ?>ms</div>
                        </div>
                        <div class="response-stat">
                            <div class="response-stat-label">Maximum</div>
                            <div class="response-stat-value"><?php echo $maxResponseTime; ?>ms</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <div class="empty-state-text">No response time data available</div>
                        <div class="empty-state-subtext">Response time monitoring will begin shortly</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Latest Incidents Table -->
                <div class="incidents-section">
                    <div class="section-header">
                        <h2 class="section-title">Latest Incidents</h2>
                        <a href="export_logs.php?project_id=<?php echo $projectId; ?>" class="btn-export" target="_blank">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export Logs
                        </a>
                    </div>
                    
                    <?php if (empty($incidents)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎉</div>
                        <div class="empty-state-text">No incidents recorded</div>
                        <div class="empty-state-subtext">Your service has been running smoothly</div>
                    </div>
                    <?php else: ?>
                    <div class="incidents-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Root Cause</th>
                                    <th>Started</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td>
                                        <div class="incident-status">
                                            <?php if ($incident['status'] === 'Resolved'): ?>
                                                <span class="status-icon resolved">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </span>
                                                <span>Resolved</span>
                                            <?php else: ?>
                                                <span class="status-icon open">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </span>
                                                <span>Open</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['root_cause']); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($incident['started_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($incident['duration'] ?: 'Ongoing'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="monitor-sidebar">
                <!-- Domain & SSL Info -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Domain & SSL
                    </h3>
                    
                    <?php if ($sslInfo): ?>
                        <?php
                        $sslExpiry = new DateTime($sslInfo['expiry_date']);
                        $domainExpiry = new DateTime($sslInfo['domain_expiry_date']);
                        $now = new DateTime();
                        $sslDaysLeft = $now->diff($sslExpiry)->days;
                        $domainDaysLeft = $now->diff($domainExpiry)->days;
                        ?>
                        <div class="ssl-info-item">
                            <span class="ssl-label">Domain valid until</span>
                            <span class="ssl-value <?php echo $domainDaysLeft < 60 ? 'expiring' : 'valid'; ?>">
                                <?php echo $domainExpiry->format('M d, Y'); ?>
                            </span>
                        </div>
                        <div class="ssl-info-item">
                            <span class="ssl-label">SSL valid until</span>
                            <span class="ssl-value <?php echo $sslDaysLeft < 30 ? 'expiring' : 'valid'; ?>">
                                <?php echo $sslExpiry->format('M d, Y'); ?>
                            </span>
                        </div>
                        <div class="ssl-info-item">
                            <span class="ssl-label">Issuer</span>
                            <span class="ssl-value"><?php echo htmlspecialchars($sslInfo['issuer']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p class="text-muted">SSL information not available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Next Maintenance -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Next Maintenance
                    </h3>
                    <button class="btn-export" style="width: 100%;">Set up maintenance</button>
                </div>

                <!-- Documentation -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Documentation
                    </h3>
                    <p class="text-muted">
                        For more details about this project, please refer to the project documentation.
                    </p>
                    <a href="project_docs.php?id=<?php echo $projectId; ?>" class="btn-export" target="_blank">
                        View Documentation
                    </a>
                </div>

                <!-- To Be Notified -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        To Be Notified
                    </h3>
                    <p class="text-muted"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>

                <!-- Last Checked -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Last Checked
                    </h3>
                    <p class="text-muted">
                        <?php if ($lastChecked): ?>
                            <?php echo date('M d, Y g:i:s A', strtotime($lastChecked)); ?>
                        <?php else: ?>
                            Never
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Appears On -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Appears On
                    </h3>
                    <a href="status.php?project=<?php echo $projectId; ?>" class="status-page-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        Public Status Page
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php if ($responseTimeData && count($responseTimeData) > 0): ?>
    <script>
        // Dark theme Chart.js configuration
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = '#334155';
        
        const ctx = document.getElementById('responseTimeChart').getContext('2d');
        const responseTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) {
                    return date('M d H:i', strtotime($d['hour']));
                }, $responseTimeData)); ?>,
                datasets: [{
                    label: 'Average',
                    data: <?php echo json_encode(array_column($responseTimeData, 'avg_time')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, {
                    label: 'Maximum',
                    data: <?php echo json_encode(array_column($responseTimeData, 'max_time')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    fill: false,
                    borderDash: [5, 5],
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, {
                    label: 'Minimum',
                    data: <?php echo json_encode(array_column($responseTimeData, 'min_time')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    fill: false,
                    borderDash: [2, 2],
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a1f2e',
                        titleColor: '#ffffff',
                        bodyColor: '#94a3b8',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Math.round(context.parsed.y) + 'ms';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: '#1e293b',
                            drawBorder: false
                        },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#1e293b',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value + 'ms';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Test Notification functionality
        document.querySelector('.action-button[title="Test Notification"]').addEventListener('click', function() {
            // Create modal overlay
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3 style="margin-bottom: 1rem;">Test Notification Sent!</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        A test notification has been sent to all configured channels.
                    </p>
                    <button class="btn-export" onclick="this.closest('.modal-overlay').remove()">
                        Close
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
            setTimeout(() => modal.classList.add('active'), 10);
        });
        
        // Pause Monitoring functionality
        let isPaused = false;
        document.querySelector('.action-button[title="Pause Monitoring"]').addEventListener('click', function() {
            isPaused = !isPaused;
            this.innerHTML = isPaused ? 
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' :
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
            this.title = isPaused ? 'Resume Monitoring' : 'Pause Monitoring';
            
            // Update status pill if paused
            const statusPill = document.querySelector('.status-pill');
            if (isPaused) {
                statusPill.className = 'status-pill paused';
                statusPill.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4" /></svg> Paused';
            }
        });
        
        // Add tooltips
        document.querySelectorAll('.action-button').forEach(button => {
            button.classList.add('tooltip');
            button.setAttribute('data-tooltip', button.title);
        });
        
        // Animate uptime bars on page load
        window.addEventListener('load', function() {
            document.querySelectorAll('.uptime-bar').forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.opacity = '1';
                }, index * 20);
            });
        });
    </script>
</body>
</html>