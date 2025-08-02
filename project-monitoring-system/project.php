<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/notification_handler.php';
requireLogin();

// Get project ID and time filter
$projectId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$timeFilter = $_GET['time'] ?? '7d';

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
    $responseTimeData = getResponseTimeStats($pdo, $projectId, 24);
    $cronJobs = getCronJobs($pdo, $projectId);
    $lastChecked = getLastChecked($pdo, $projectId);
    $unreadNotifications = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
    
    // Get incidents with time filter
    $incidentDays = match($timeFilter) {
        '24h' => 1,
        '7d' => 7,
        '30d' => 30,
        default => 7
    };
    
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify("-{$incidentDays} days");
    
    $stmt = $pdo->prepare("
        SELECT * FROM incidents 
        WHERE project_id = ? AND started_at BETWEEN ? AND ?
        ORDER BY started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
    $incidents = $stmt->fetchAll();
    
    // Get current status
    $stmt = $pdo->prepare("SELECT is_up FROM uptime_logs WHERE project_id = ? ORDER BY checked_at DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $currentStatus = $stmt->fetch();
    $isUp = $currentStatus ? $currentStatus['is_up'] : true;
    
    // Calculate days until SSL and domain expiry
    $sslDaysLeft = null;
    $domainDaysLeft = null;
    
    if ($sslInfo) {
        if ($sslInfo['expiry_date']) {
            $sslExpiry = new DateTime($sslInfo['expiry_date']);
            $now = new DateTime();
            $sslDaysLeft = $now->diff($sslExpiry)->days;
            if ($sslExpiry < $now) {
                $sslDaysLeft = -$sslDaysLeft;
            }
        }
        
        if ($sslInfo['domain_expiry_date']) {
            $domainExpiry = new DateTime($sslInfo['domain_expiry_date']);
            $now = new DateTime();
            $domainDaysLeft = $now->diff($domainExpiry)->days;
            if ($domainExpiry < $now) {
                $domainDaysLeft = -$domainDaysLeft;
            }
        }
    }
    
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
    <title><?php echo htmlspecialchars($project['project_name']); ?> - Uptime Monitoring System</title>
    
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
        
        .project-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        
        @media (max-width: 1024px) {
            .project-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Header Section */
        .project-header {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .project-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .project-url {
            color: var(--text-secondary);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .project-url a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .project-url a:hover {
            text-decoration: underline;
        }
        
        .status-large {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background-color: var(--bg-tertiary);
            border-radius: 8px;
            font-weight: 600;
        }
        
        .status-large.up {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-large.down {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-dot-large {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot-large.up {
            background-color: var(--success);
        }
        
        .status-dot-large.down {
            background-color: var(--danger);
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* Uptime Cards */
        .uptime-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            .uptime-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .uptime-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .uptime-period {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .uptime-percentage {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .uptime-percentage.perfect { color: var(--success); }
        .uptime-percentage.good { color: var(--warning); }
        .uptime-percentage.poor { color: var(--danger); }
        
        .uptime-details {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        /* Response Time Chart */
        .chart-container {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .chart-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .time-filters {
            display: flex;
            gap: 8px;
        }
        
        .time-filter {
            padding: 6px 12px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .time-filter:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .time-filter.active {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        #responseChart {
            max-height: 300px;
        }
        
        /* HTTP Status Distribution */
        .status-distribution {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 20px;
        }
        
        .status-item {
            text-align: center;
        }
        
        .status-code {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .status-count {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .status-percent {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .status-2xx { color: var(--success); }
        .status-3xx { color: var(--info); }
        .status-4xx { color: var(--warning); }
        .status-5xx { color: var(--danger); }
        
        /* Incidents Table */
        .incidents-section {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
        }
        
        .incidents-table {
            width: 100%;
            margin-top: 20px;
        }
        
        .incidents-table th {
            text-align: left;
            padding: 12px;
            background-color: var(--bg-tertiary);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }
        
        .incidents-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .incidents-table tr:last-child td {
            border-bottom: none;
        }
        
        .incident-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .incident-status.resolved {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .incident-status.open {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .sidebar-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }
        
        .info-value.success { color: var(--success); }
        .info-value.warning { color: var(--warning); }
        .info-value.danger { color: var(--danger); }
        
        .cert-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .cert-valid {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .cert-expiring {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .cert-expired {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .actions-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
            transform: translateY(-1px);
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
                <a href="add_project.php" class="nav-item">Add Monitor</a>
                <a href="notifications.php" class="nav-item">
                    Notifications
                    <?php if ($unreadNotifications > 0): ?>
                        <span style="background-color: var(--danger); color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 4px;">
                            <?php echo $unreadNotifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php if (isAdmin()): ?>
                <a href="admin/users.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </nav>

    <div class="project-container">
        <div class="main-content">
            <!-- Project Header -->
            <div class="project-header">
                <div class="header-top">
                    <div>
                        <h1 class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                        <div class="project-url">
                            <span>🌐</span>
                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank">
                                <?php echo htmlspecialchars($project['project_url']); ?>
                            </a>
                        </div>
                    </div>
                    <div class="status-large <?php echo $isUp ? 'up' : 'down'; ?>">
                        <span class="status-dot-large <?php echo $isUp ? 'up' : 'down'; ?>"></span>
                        <?php echo $isUp ? 'Operational' : 'Down'; ?>
                    </div>
                </div>
                
                <?php if ($project['description']): ?>
                <p style="color: var(--text-secondary); margin-top: 16px;">
                    <?php echo htmlspecialchars($project['description']); ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Uptime Statistics -->
            <div class="uptime-grid">
                <?php 
                $uptimeData = [
                    ['period' => 'Last 7 days', 'data' => $uptime7Days],
                    ['period' => 'Last 30 days', 'data' => $uptime30Days],
                    ['period' => 'Last 365 days', 'data' => $uptime365Days]
                ];
                
                foreach ($uptimeData as $uptime): 
                    $percentage = $uptime['data']['percentage'] ?? 100;
                    $class = $percentage >= 99.9 ? 'perfect' : ($percentage >= 95 ? 'good' : 'poor');
                ?>
                <div class="uptime-card">
                    <div class="uptime-period"><?php echo $uptime['period']; ?></div>
                    <div class="uptime-percentage <?php echo $class; ?>">
                        <?php echo number_format($percentage, 2); ?>%
                    </div>
                    <?php if ($uptime['data']): ?>
                    <div class="uptime-details">
                        Downtime: <?php echo $uptime['data']['downtime']; ?><br>
                        Incidents: <?php echo $uptime['data']['incidents']; ?>
                    </div>
                    <?php else: ?>
                    <div class="uptime-details">No data available</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Response Time Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h2 class="chart-title">Response Time</h2>
                    <div class="time-filters">
                        <a href="?id=<?php echo $projectId; ?>&time=24h" class="time-filter <?php echo $timeFilter === '24h' ? 'active' : ''; ?>">24h</a>
                        <a href="?id=<?php echo $projectId; ?>&time=7d" class="time-filter <?php echo $timeFilter === '7d' ? 'active' : ''; ?>">7d</a>
                        <a href="?id=<?php echo $projectId; ?>&time=30d" class="time-filter <?php echo $timeFilter === '30d' ? 'active' : ''; ?>">30d</a>
                    </div>
                </div>
                <canvas id="responseChart"></canvas>
            </div>

            <!-- HTTP Status Distribution -->
            <?php if ($statusCodeData && $statusCodeData['total'] > 0): ?>
            <div class="status-distribution">
                <h2 class="chart-title">HTTP Status Distribution</h2>
                <div class="status-grid">
                    <?php 
                    $statusCategories = ['2xx' => 'status-2xx', '3xx' => 'status-3xx', '4xx' => 'status-4xx', '5xx' => 'status-5xx'];
                    foreach ($statusCategories as $category => $class): 
                        $data = $statusCodeData['distribution'][$category] ?? ['count' => 0, 'percentage' => 0];
                    ?>
                    <div class="status-item">
                        <div class="status-code"><?php echo $category; ?></div>
                        <div class="status-count <?php echo $class; ?>"><?php echo $data['count']; ?></div>
                        <div class="status-percent"><?php echo $data['percentage']; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Incidents -->
            <div class="incidents-section">
                <h2 class="chart-title">Recent Incidents</h2>
                <?php if (!empty($incidents)): ?>
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Duration</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $incident): 
                            $duration = 'Ongoing';
                            if ($incident['resolved_at']) {
                                $start = new DateTime($incident['started_at']);
                                $end = new DateTime($incident['resolved_at']);
                                $diff = $start->diff($end);
                                if ($diff->days > 0) {
                                    $duration = $diff->days . 'd ' . $diff->h . 'h';
                                } elseif ($diff->h > 0) {
                                    $duration = $diff->h . 'h ' . $diff->i . 'm';
                                } else {
                                    $duration = $diff->i . 'm';
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="incident-status <?php echo $incident['status'] === 'Resolved' ? 'resolved' : 'open'; ?>">
                                    <?php echo $incident['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($incident['started_at'])); ?></td>
                            <td><?php echo $duration; ?></td>
                            <td><?php echo htmlspecialchars($incident['description'] ?: 'No description'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <p>No incidents in the selected time period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- SSL Certificate Info -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <span>🔒</span>
                    SSL Certificate
                </h3>
                <?php if ($sslInfo && $sslInfo['issuer']): ?>
                    <div class="info-row">
                        <span class="info-label">Issuer</span>
                        <span class="info-value"><?php echo htmlspecialchars($sslInfo['issuer']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Expires</span>
                        <span class="info-value <?php echo $sslDaysLeft < 30 ? ($sslDaysLeft < 0 ? 'danger' : 'warning') : 'success'; ?>">
                            <?php 
                            if ($sslDaysLeft !== null) {
                                if ($sslDaysLeft < 0) {
                                    echo 'Expired ' . abs($sslDaysLeft) . ' days ago';
                                } else {
                                    echo $sslDaysLeft . ' days';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="cert-badge <?php echo $sslDaysLeft < 0 ? 'cert-expired' : ($sslDaysLeft < 30 ? 'cert-expiring' : 'cert-valid'); ?>">
                            <?php echo $sslDaysLeft < 0 ? 'Expired' : ($sslDaysLeft < 30 ? 'Expiring Soon' : 'Valid'); ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No SSL information available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Domain Info -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <span>🌐</span>
                    Domain Information
                </h3>
                <?php if ($sslInfo && $sslInfo['domain_expiry_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Domain Expires</span>
                        <span class="info-value <?php echo $domainDaysLeft < 30 ? ($domainDaysLeft < 0 ? 'danger' : 'warning') : 'success'; ?>">
                            <?php 
                            if ($domainDaysLeft !== null) {
                                if ($domainDaysLeft < 0) {
                                    echo 'Expired ' . abs($domainDaysLeft) . ' days ago';
                                } else {
                                    echo $domainDaysLeft . ' days';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Expiry Date</span>
                        <span class="info-value"><?php echo date('Y-m-d', strtotime($sslInfo['domain_expiry_date'])); ?></span>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No domain information available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Monitor Info -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <span>📊</span>
                    Monitor Details
                </h3>
                <div class="info-row">
                    <span class="info-label">Check Interval</span>
                    <span class="info-value">Every 5 minutes</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Checked</span>
                    <span class="info-value">
                        <?php echo $lastChecked ? date('Y-m-d H:i:s', strtotime($lastChecked)) : 'Never'; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created</span>
                    <span class="info-value"><?php echo date('Y-m-d', strtotime($project['date_created'])); ?></span>
                </div>
                
                <div class="actions-group">
                    <a href="export_logs.php?project_id=<?php echo $projectId; ?>" class="btn-icon" title="Export Logs">
                        <span>📥</span>
                    </a>
                    <a href="#" class="btn-icon" title="Settings" onclick="alert('Settings page coming soon!'); return false;">
                        <span>⚙️</span>
                    </a>
                    <?php if (!hasMonitoringData($pdo, $projectId)): ?>
                    <a href="generate_sample_data.php?project_id=<?php echo $projectId; ?>" class="btn-icon" title="Generate Sample Data">
                        <span>📊</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Response Time Chart
        const ctx = document.getElementById('responseChart').getContext('2d');
        const responseChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php 
                    $labels = [];
                    $values = [];
                    if ($responseTimeData) {
                        foreach ($responseTimeData as $data) {
                            $labels[] = date('H:i', strtotime($data['hour']));
                            $values[] = round($data['avg_time']);
                        }
                    }
                    echo json_encode($labels);
                ?>,
                datasets: [{
                    label: 'Response Time (ms)',
                    data: <?php echo json_encode($values); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#a0a0a0'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#a0a0a0'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>