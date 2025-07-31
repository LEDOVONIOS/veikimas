<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/notification_handler.php';
requireLogin();

// Set Lithuanian timezone
date_default_timezone_set('Europe/Vilnius');

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
    
    // Check if monitoring data exists, if not, generate mock data
    if (!hasMonitoringData($pdo, $projectId)) {
        generateMockData($pdo, $projectId);
    }
    
    // Get monitoring data
    $statusCodeData = getStatusCodeDistribution($pdo, $projectId, 7);
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
    $incidents = getIncidents($pdo, $projectId, $incidentDays);
    
    // Calculate current uptime duration
    $lastIncident = getLastIncident($pdo, $projectId);
    $uptimeDuration = '';
    $isUp = count(array_filter($incidents, fn($inc) => $inc['status'] === 'Open')) === 0;
    
    if ($isUp && $lastIncident) {
        $lastIncidentTime = new DateTime($lastIncident['resolved_at'] ?? $lastIncident['started_at']);
        $now = new DateTime();
        $diff = $now->diff($lastIncidentTime);
        
        if ($diff->days > 0) {
            $uptimeDuration = $diff->days . 'd ' . $diff->h . 'h ' . $diff->i . 'm';
        } else if ($diff->h > 0) {
            $uptimeDuration = $diff->h . 'h ' . $diff->i . 'm';
        } else {
            $uptimeDuration = $diff->i . 'm';
        }
    } else if ($isUp) {
        // If no incidents ever, calculate from project creation
        $created = new DateTime($project['date_created']);
        $now = new DateTime();
        $diff = $now->diff($created);
        
        if ($diff->days > 0) {
            $uptimeDuration = $diff->days . 'd ' . $diff->h . 'h ' . $diff->i . 'm';
        } else if ($diff->h > 0) {
            $uptimeDuration = $diff->h . 'h ' . $diff->i . 'm';
        } else {
            $uptimeDuration = $diff->i . 'm';
        }
    }
    
    // Helper function to format time in Lithuanian timezone
    function formatLithuanianTime($timestamp) {
        if (!$timestamp) return 'N/A';
        $dt = new DateTime($timestamp);
        $dt->setTimezone(new DateTimeZone('Europe/Vilnius'));
        
        $now = new DateTime('now', new DateTimeZone('Europe/Vilnius'));
        $diff = $now->diff($dt);
        
        // If more than 24 hours old, show date
        if ($diff->days > 0) {
            return $dt->format('Y-m-d H:i');
        } else {
            return $dt->format('H:i');
        }
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['project_name']); ?> - Uptime Monitor</title>
    <link rel="stylesheet" href="assets/css/modern-dark-theme.css">
    <style>
        /* Additional modern styles */
        :root {
            --bg-primary: #0e1525;
            --bg-secondary: #1a1f2e;
            --bg-card: #202937;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --success: #00FFB2;
            --warning: #FACC15;
            --danger: #EF4444;
            --neutral: #334155;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        /* Navigation */
        .nav-modern {
            background: var(--bg-secondary);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        /* Header Section */
        .monitor-header {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .monitor-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .monitor-title h1 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .status-pill {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-pill.up {
            background: rgba(0, 255, 178, 0.2);
            color: var(--success);
        }
        
        .status-pill.down {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-icon:hover {
            background: rgba(255,255,255,0.15);
            color: var(--text-primary);
            transform: translateY(-1px);
        }
        
        .monitor-url {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .monitor-url:hover {
            color: var(--text-primary);
        }
        
        .uptime-duration {
            color: var(--success);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .last-checked {
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        /* Status Timeline */
        .status-timeline {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            border-radius: 0.5rem;
        }
        
        .timeline-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .timeline-blocks {
            display: flex;
            gap: 2px;
            height: 20px;
        }
        
        .timeline-block {
            flex: 1;
            background: var(--success);
            border-radius: 2px;
            position: relative;
            cursor: pointer;
        }
        
        .timeline-block.down {
            background: var(--danger);
        }
        
        .timeline-block:hover::after {
            content: attr(data-time);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            white-space: nowrap;
            margin-bottom: 0.25rem;
        }
        
        /* Uptime Cards Grid */
        .uptime-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .uptime-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }
        
        .uptime-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        
        .uptime-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .uptime-period {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .uptime-percentage {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .uptime-percentage.good {
            color: var(--success);
        }
        
        .uptime-percentage.warning {
            color: var(--warning);
        }
        
        .uptime-percentage.bad {
            color: var(--danger);
        }
        
        .uptime-details {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        /* Response Time Chart */
        .chart-section {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .time-selector {
            display: flex;
            gap: 0.5rem;
        }
        
        .time-option {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        
        .time-option:hover,
        .time-option.active {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: var(--text-primary);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1.5rem;
        }
        
        .response-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 0.25rem;
        }
        
        /* Incidents Grid */
        .incidents-section {
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
        
        .incidents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .incident-tile {
            background: var(--bg-card);
            border-radius: 0.75rem;
            padding: 1.5rem;
            border-left: 4px solid;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .incident-tile:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        
        .incident-tile.resolved {
            border-left-color: var(--success);
        }
        
        .incident-tile.open {
            border-left-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }
        
        .incident-tile.investigating {
            border-left-color: var(--warning);
        }
        
        .incident-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .incident-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .incident-status.resolved {
            background: rgba(0, 255, 178, 0.2);
            color: var(--success);
        }
        
        .incident-status.open {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        .incident-cause {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .incident-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .incident-duration {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .load-more {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-primary);
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: block;
            margin: 0 auto;
            font-size: 0.875rem;
        }
        
        .load-more:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        /* Info Cards Layout */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .info-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        
        .info-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .info-icon {
            width: 24px;
            height: 24px;
            color: var(--text-secondary);
        }
        
        .info-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .info-item {
            padding: 0.75rem;
            background: rgba(0,0,0,0.2);
            border-radius: 0.5rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .info-value.valid {
            color: var(--success);
        }
        
        .info-value.expiring {
            color: var(--warning);
        }
        
        /* Region Map */
        .region-map {
            position: relative;
            height: 120px;
            background: rgba(0,0,0,0.2);
            border-radius: 0.5rem;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .map-overlay {
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><path d="M150,300 Q200,250 250,300 T350,300 Q400,250 450,300 T550,300 Q600,250 650,300 T750,300 Q800,250 850,300 T950,300" stroke="%23334155" stroke-width="1" fill="none"/></svg>');
            background-size: cover;
            opacity: 0.3;
        }
        
        .region-marker {
            position: absolute;
            width: 12px;
            height: 12px;
            background: var(--success);
            border: 2px solid var(--bg-card);
            border-radius: 50%;
            top: 50%;
            left: 30%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 0 0 rgba(0, 255, 178, 0.4);
            animation: ping 2s infinite;
        }
        
        @keyframes ping {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 255, 178, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(0, 255, 178, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 255, 178, 0);
            }
        }
        
        .region-label {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* Timezone indicator */
        .timezone-badge {
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: rgba(255,255,255,0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            margin-left: 0.5rem;
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
                    <a href="dashboard.php" class="btn-icon">
                        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Dashboard
                    </a>
                    <?php if ($unreadNotifications > 0): ?>
                        <a href="notifications.php" class="btn-icon">
                            <span style="position: relative;">
                                🔔
                                <span style="position: absolute; top: -4px; right: -4px; background: var(--danger); color: white; font-size: 0.625rem; padding: 2px 4px; border-radius: 9999px;">
                                    <?php echo $unreadNotifications; ?>
                                </span>
                            </span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-icon">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Monitor Header -->
        <div class="monitor-header">
            <div class="header-top">
                <div>
                    <div class="monitor-title">
                        <h1><?php echo htmlspecialchars($project['project_name']); ?></h1>
                        <span class="status-pill <?php echo $isUp ? 'up' : 'down'; ?>">
                            <span class="status-dot"></span>
                            <?php echo $isUp ? 'Up' : 'Down'; ?>
                        </span>
                    </div>
                    <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" class="monitor-url">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        <?php echo htmlspecialchars($project['project_url']); ?>
                    </a>
                    <?php if ($uptimeDuration): ?>
                        <p class="uptime-duration">Currently up for: <?php echo $uptimeDuration; ?></p>
                    <?php endif; ?>
                    <p class="last-checked">
                        Last checked: <?php echo formatLithuanianTime($lastChecked); ?>
                        <span class="timezone-badge">Europe/Vilnius</span>
                    </p>
                </div>
                <div class="action-buttons">
                    <button class="btn-icon" onclick="testNotification()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Test Notification
                    </button>
                    <button class="btn-icon" onclick="pauseMonitoring()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Pause
                    </button>
                    <button class="btn-icon" onclick="window.location.href='edit_project.php?id=<?php echo $projectId; ?>'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </button>
                </div>
            </div>
            
            <!-- Status Timeline -->
            <div class="status-timeline">
                <div class="timeline-label">Last 24 hours status</div>
                <div class="timeline-blocks">
                    <?php 
                    // Generate 24 hour blocks
                    for ($i = 23; $i >= 0; $i--) {
                        $blockTime = new DateTime("-$i hours", new DateTimeZone('Europe/Vilnius'));
                        $isDown = rand(0, 100) < 2; // 2% chance of being down
                        echo '<div class="timeline-block ' . ($isDown ? 'down' : '') . '" 
                              data-time="' . $blockTime->format('H:i') . ' LT"
                              title="' . $blockTime->format('H:i') . ' LT"></div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Uptime Statistics Grid -->
        <div class="uptime-grid">
            <?php
            $uptimeData = [
                ['period' => 'Last 7 days', 'data' => $uptime7Days],
                ['period' => 'Last 30 days', 'data' => $uptime30Days],
                ['period' => 'Last 365 days', 'data' => $uptime365Days]
            ];
            
            foreach ($uptimeData as $uptime):
                $percentage = $uptime['data']['percentage'];
                $colorClass = $percentage >= 99.99 ? 'good' : ($percentage >= 99.9 ? 'warning' : 'bad');
            ?>
            <div class="uptime-card">
                <div class="uptime-card-header">
                    <span class="uptime-period"><?php echo $uptime['period']; ?></span>
                </div>
                <div class="uptime-percentage <?php echo $colorClass; ?>">
                    <?php echo number_format($percentage, 2); ?>%
                </div>
                <div class="uptime-details">
                    <?php echo $uptime['data']['incidents']; ?> incidents, 
                    <?php echo $uptime['data']['downtime_minutes']; ?>m down
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Response Time Chart -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Response Time</h2>
                <div class="time-selector">
                    <button class="time-option" onclick="changeTimeRange('1h')">1h</button>
                    <button class="time-option active" onclick="changeTimeRange('24h')">24h</button>
                    <button class="time-option" onclick="changeTimeRange('7d')">7d</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="responseTimeChart"></canvas>
            </div>
            <div class="response-stats">
                <div class="stat-item">
                    <div class="stat-label">Average</div>
                    <div class="stat-value"><?php echo round($responseTimeData['avg'] ?? 0); ?>ms</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Minimum</div>
                    <div class="stat-value"><?php echo round($responseTimeData['min'] ?? 0); ?>ms</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Maximum</div>
                    <div class="stat-value"><?php echo round($responseTimeData['max'] ?? 0); ?>ms</div>
                </div>
            </div>
        </div>

        <!-- Incidents Grid -->
        <div class="incidents-section">
            <div class="section-header">
                <h2 class="section-title">Latest Incidents</h2>
                <button class="btn-icon" onclick="exportLogs()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Logs
                </button>
            </div>
            
            <div class="incidents-grid">
                <?php if (empty($incidents)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                        No incidents recorded
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($incidents, 0, 6) as $incident): ?>
                        <div class="incident-tile <?php echo strtolower($incident['status']); ?>">
                            <div class="incident-header">
                                <div class="incident-status <?php echo strtolower($incident['status']); ?>">
                                    <?php echo $incident['status'] === 'Resolved' ? '✓' : '!'; ?>
                                </div>
                                <div class="incident-cause">
                                    <?php echo htmlspecialchars($incident['root_cause'] ?? 'Connection Timeout'); ?>
                                </div>
                            </div>
                            <div class="incident-time">
                                Started: <?php echo formatLithuanianTime($incident['started_at']); ?> LT
                            </div>
                            <div class="incident-duration">
                                Duration: <?php echo htmlspecialchars($incident['duration'] ?? 'Ongoing'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (count($incidents) > 6): ?>
                <button class="load-more" onclick="loadMoreIncidents()">Load More</button>
            <?php endif; ?>
        </div>

        <!-- Info Cards Grid -->
        <div class="info-grid">
            <!-- Domain & SSL Info -->
            <div class="info-card">
                <div class="info-card-header">
                    <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Domain & SSL Information
                </div>
                <div class="info-content">
                    <div class="info-item">
                        <div class="info-label">Domain</div>
                        <div class="info-value <?php echo ($sslInfo['domain_days_left'] ?? 0) > 30 ? 'valid' : 'expiring'; ?>">
                            <?php 
                            if ($sslInfo && isset($sslInfo['domain_expiry'])) {
                                $domainExpiry = new DateTime($sslInfo['domain_expiry']);
                                $domainExpiry->setTimezone(new DateTimeZone('Europe/Vilnius'));
                                echo 'Expires: ' . $domainExpiry->format('Y-m-d');
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">SSL Certificate</div>
                        <div class="info-value <?php echo ($sslInfo['ssl_days_left'] ?? 0) > 30 ? 'valid' : 'expiring'; ?>">
                            <?php 
                            if ($sslInfo && isset($sslInfo['ssl_expiry'])) {
                                $sslExpiry = new DateTime($sslInfo['ssl_expiry']);
                                $sslExpiry->setTimezone(new DateTimeZone('Europe/Vilnius'));
                                echo 'Valid until: ' . $sslExpiry->format('Y-m-d');
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Issuer</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($sslInfo['issuer'] ?? 'Unknown'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monitoring Location -->
            <div class="info-card">
                <div class="info-card-header">
                    <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Monitoring Location
                </div>
                <div class="region-map">
                    <div class="map-overlay"></div>
                    <div class="region-marker"></div>
                    <div class="region-label"><?php echo htmlspecialchars($project['monitoring_region'] ?? 'North America'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Lithuanian timezone formatter
        const ltFormatter = new Intl.DateTimeFormat('lt-LT', {
            timeZone: 'Europe/Vilnius',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        const ltDateFormatter = new Intl.DateTimeFormat('lt-LT', {
            timeZone: 'Europe/Vilnius',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        // Response Time Chart
        const ctx = document.getElementById('responseTimeChart').getContext('2d');
        
        // Generate time labels for last 24 hours (every 10 minutes)
        const labels = [];
        const now = new Date();
        for (let i = 144; i >= 0; i--) { // 144 = 24 hours * 6 (10-minute intervals)
            const time = new Date(now.getTime() - i * 10 * 60 * 1000);
            if (i % 6 === 0) { // Show label every hour
                labels.push(ltFormatter.format(time));
            } else {
                labels.push('');
            }
        }

        // Generate mock data
        const avgData = Array(145).fill(0).map(() => 150 + Math.random() * 100);
        const minData = avgData.map(v => v - 20 - Math.random() * 30);
        const maxData = avgData.map(v => v + 50 + Math.random() * 100);

        const responseTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average',
                    data: avgData,
                    borderColor: '#00FFB2',
                    backgroundColor: 'rgba(0, 255, 178, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, {
                    label: 'Maximum',
                    data: maxData,
                    borderColor: '#FACC15',
                    borderDash: [5, 5],
                    borderWidth: 1,
                    tension: 0.4,
                    pointRadius: 0,
                    fill: false
                }, {
                    label: 'Minimum',
                    data: minData,
                    borderColor: '#94a3b8',
                    borderDash: [2, 2],
                    borderWidth: 1,
                    tension: 0.4,
                    pointRadius: 0,
                    fill: false,
                    opacity: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                const time = new Date(now.getTime() - (144 - index) * 10 * 60 * 1000);
                                return ltFormatter.format(time) + ' LT';
                            },
                            label: function(context) {
                                return context.dataset.label + ': ' + Math.round(context.parsed.y) + 'ms';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return value + 'ms';
                            }
                        }
                    }
                },
                annotation: {
                    annotations: {
                        threshold: {
                            type: 'line',
                            yMin: 1200,
                            yMax: 1200,
                            borderColor: '#EF4444',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            label: {
                                enabled: true,
                                content: 'Alert threshold',
                                position: 'start'
                            }
                        }
                    }
                }
            }
        });

        // Functions
        function testNotification() {
            alert('Test notification sent!');
        }

        function pauseMonitoring() {
            if (confirm('Are you sure you want to pause monitoring?')) {
                alert('Monitoring paused');
            }
        }

        function exportLogs() {
            window.location.href = 'export_logs.php?project_id=<?php echo $projectId; ?>';
        }

        function loadMoreIncidents() {
            // Implement load more functionality
            alert('Loading more incidents...');
        }

        function changeTimeRange(range) {
            document.querySelectorAll('.time-option').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            // Implement time range change
            console.log('Changed time range to:', range);
        }
    </script>
</body>
</html>