<?php
require_once 'db.php';
require_once 'includes/roles.php';
require_once 'includes/monitoring_functions.php';
requireLogin();

// Get user role and URL information
$userId = $_SESSION['user_id'];
$userRole = getUserRole($userId);
$urlCount = getUserUrlCount($userId);
$urlLimit = getUserUrlLimit($userId);
$remainingUrls = getRemainingUrlSlots($userId);

// Get user's projects with monitoring status
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(DISTINCT i.id) as incident_count,
               COUNT(DISTINCT CASE WHEN i.status = 'Open' THEN i.id END) as open_incidents,
               (SELECT is_up FROM uptime_logs WHERE project_id = p.id ORDER BY checked_at DESC LIMIT 1) as current_status,
               (SELECT response_time FROM uptime_logs WHERE project_id = p.id ORDER BY checked_at DESC LIMIT 1) as last_response_time,
               (SELECT checked_at FROM uptime_logs WHERE project_id = p.id ORDER BY checked_at DESC LIMIT 1) as last_checked
        FROM projects p
        LEFT JOIN incidents i ON p.id = i.project_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY p.date_created DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
    
    // Calculate statistics
    $totalMonitors = count($projects);
    $upMonitors = 0;
    $downMonitors = 0;
    $pausedMonitors = 0;
    
    foreach ($projects as $project) {
        // If there's no monitoring data, consider it as paused
        if ($project['last_checked'] === null) {
            $pausedMonitors++;
        } elseif ($project['current_status'] === '1') {
            $upMonitors++;
        } elseif ($project['current_status'] === '0') {
            $downMonitors++;
        } else {
            // No recent monitoring data
            $pausedMonitors++;
        }
    }
    
    // Get last 24 hours statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN ul.is_up = 0 THEN ul.project_id END) as affected_monitors,
            COUNT(CASE WHEN ul.is_up = 0 THEN 1 END) as total_incidents,
            AVG(CASE WHEN ul.is_up = 1 THEN 100 ELSE 0 END) as overall_uptime
        FROM uptime_logs ul
        INNER JOIN projects p ON ul.project_id = p.id
        WHERE p.user_id = ? AND ul.checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$userId]);
    $last24Stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $projects = [];
    $totalMonitors = 0;
    $upMonitors = 0;
    $downMonitors = 0;
    $pausedMonitors = 0;
    $last24Stats = ['affected_monitors' => 0, 'total_incidents' => 0, 'overall_uptime' => 100];
}

// Calculate uptime for each project
foreach ($projects as &$project) {
    $project['uptime_7d'] = calculateUptime($pdo, $project['id'], 7);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitors - Uptime Monitoring Dashboard</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #0f0f0f;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #242424;
            --bg-hover: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --text-muted: #666666;
            --border-color: #2a2a2a;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --accent: #6366f1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        .sidebar {
            width: 320px;
            background-color: var(--bg-secondary);
            border-left: 1px solid var(--border-color);
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .btn-new-monitor {
            background-color: var(--accent);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-new-monitor:hover {
            background-color: #5558e3;
            transform: translateY(-1px);
        }
        
        /* Toolbar */
        .toolbar {
            background-color: var(--bg-secondary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .toolbar-left {
            display: flex;
            gap: 12px;
            align-items: center;
            flex: 1;
        }
        
        .toolbar-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }
        
        .dropdown, .search-input {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .search-input {
            min-width: 250px;
        }
        
        .filter-btn {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        
        /* Monitors Table */
        .monitors-list {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .monitor-row {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
            gap: 16px;
        }
        
        .monitor-row:hover {
            background-color: var(--bg-hover);
        }
        
        .monitor-row:last-child {
            border-bottom: none;
        }
        
        .monitor-checkbox {
            flex-shrink: 0;
        }
        
        .monitor-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .status-up {
            background-color: var(--success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .status-down {
            background-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
            animation: pulse 2s infinite;
        }
        
        .status-paused {
            background-color: var(--text-muted);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
            50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.1); }
            100% { box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
        }
        
        .monitor-info {
            flex: 1;
            min-width: 0;
        }
        
        .monitor-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .monitor-type {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .monitor-interval {
            color: var(--text-secondary);
            font-size: 13px;
            white-space: nowrap;
        }
        
        .monitor-uptime {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
        }
        
        .uptime-bar {
            flex: 1;
            height: 8px;
            background-color: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .uptime-fill {
            height: 100%;
            background-color: var(--success);
            transition: width 0.3s ease;
        }
        
        .uptime-text {
            font-size: 13px;
            color: var(--text-secondary);
            min-width: 45px;
            text-align: right;
        }
        
        .monitor-actions {
            position: relative;
        }
        
        .actions-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .actions-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        /* Sidebar Styles */
        .sidebar-block {
            background-color: var(--bg-tertiary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-primary);
        }
        
        .status-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .status-ring {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                var(--success) 0deg,
                var(--success) calc(var(--up-percentage) * 3.6deg),
                var(--danger) calc(var(--up-percentage) * 3.6deg),
                var(--danger) calc((var(--up-percentage) + var(--down-percentage)) * 3.6deg),
                var(--text-muted) calc((var(--up-percentage) + var(--down-percentage)) * 3.6deg)
            );
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .status-ring::before {
            content: '';
            width: 80%;
            height: 80%;
            background-color: var(--bg-tertiary);
            border-radius: 50%;
        }
        
        .status-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 12px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-count {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .stat-up { color: var(--success); }
        .stat-down { color: var(--danger); }
        .stat-paused { color: var(--text-muted); }
        
        .monitor-usage {
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .stats-grid {
            display: grid;
            gap: 12px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-row-label {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .stat-row-value {
            font-weight: 600;
            font-size: 15px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                border-left: none;
                border-top: 1px solid var(--border-color);
            }
            
            .main-content {
                padding: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar-left, .toolbar-right {
                flex-direction: column;
                width: 100%;
            }
            
            .search-input {
                width: 100%;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .monitor-row {
                flex-wrap: wrap;
                gap: 12px;
            }
            
            .monitor-uptime {
                width: 100%;
                order: 3;
            }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .empty-state p {
            margin-bottom: 24px;
        }
        
        /* Navigation */
        .nav-menu {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background-color: var(--bg-secondary);
            padding: 12px;
            border-radius: 12px;
        }
        
        .nav-item {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background-color: var(--accent);
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <!-- Navigation -->
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item active">Monitors</a>
                <a href="notifications.php" class="nav-item">Notifications</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Monitors.</h1>
                <a href="add_project.php" class="btn-new-monitor">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="3" x2="8" y2="13"></line>
                        <line x1="3" y1="8" x2="13" y2="8"></line>
                    </svg>
                    New monitor
                </a>
            </div>
            
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    </div>
                    <select class="dropdown" id="bulkActions">
                        <option>Bulk actions</option>
                        <option value="pause">Pause selected</option>
                        <option value="resume">Resume selected</option>
                        <option value="delete">Delete selected</option>
                    </select>
                    <select class="dropdown" id="tagFilter">
                        <option>All tags</option>
                    </select>
                    <input type="text" class="search-input" placeholder="Search by name or url" id="searchInput" onkeyup="filterMonitors()">
                </div>
                <div class="toolbar-right">
                    <select class="dropdown" id="sortBy" onchange="sortMonitors()">
                        <option value="status">Down first</option>
                        <option value="name">Name A-Z</option>
                        <option value="uptime">Uptime</option>
                        <option value="response">Response time</option>
                    </select>
                    <button class="filter-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 4h12M4 8h8M6 12h4"></path>
                        </svg>
                        Filter
                    </button>
                </div>
            </div>
            
            <!-- Monitors List -->
            <div class="monitors-list" id="monitorsList">
                <?php if (empty($projects)): ?>
                    <div class="empty-state">
                        <h3>No monitors yet</h3>
                        <p>Start monitoring your websites by adding your first monitor.</p>
                        <a href="add_project.php" class="btn-new-monitor">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="8" y1="3" x2="8" y2="13"></line>
                                <line x1="3" y1="8" x2="13" y2="8"></line>
                            </svg>
                            Create your first monitor
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $project): 
                        $status = 'paused';
                        if ($project['last_checked'] !== null) {
                            $status = $project['current_status'] === '1' ? 'up' : ($project['current_status'] === '0' ? 'down' : 'paused');
                        }
                        $uptime = $project['uptime_7d']['percentage'] ?? 100;
                        $responseTime = $project['last_response_time'] ?? 0;
                        
                        // Parse URL to get domain
                        $parsedUrl = parse_url($project['project_url']);
                        $domain = $parsedUrl['host'] ?? $project['project_url'];
                    ?>
                        <div class="monitor-row" data-monitor-id="<?php echo $project['id']; ?>" data-status="<?php echo $status; ?>" data-name="<?php echo htmlspecialchars($project['project_name']); ?>" data-url="<?php echo htmlspecialchars($project['project_url']); ?>">
                            <div class="monitor-checkbox">
                                <input type="checkbox" class="monitor-select" value="<?php echo $project['id']; ?>">
                            </div>
                            <div class="monitor-status status-<?php echo $status; ?>"></div>
                            <div class="monitor-info">
                                <div class="monitor-name"><?php echo htmlspecialchars($domain); ?></div>
                                <div class="monitor-type">
                                    🌐 HTTP(S) · <?php echo number_format($responseTime); ?>ms
                                </div>
                            </div>
                            <div class="monitor-interval">
                                🔄 Every 1 min
                            </div>
                            <div class="monitor-uptime">
                                <div class="uptime-bar">
                                    <div class="uptime-fill" style="width: <?php echo $uptime; ?>%"></div>
                                </div>
                                <div class="uptime-text"><?php echo number_format($uptime, 1); ?>%</div>
                            </div>
                            <div class="monitor-actions">
                                <button class="actions-btn" onclick="showActions(<?php echo $project['id']; ?>)">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                        <circle cx="10" cy="4" r="1.5"></circle>
                                        <circle cx="10" cy="10" r="1.5"></circle>
                                        <circle cx="10" cy="16" r="1.5"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Current Status Block -->
            <div class="sidebar-block">
                <h2 class="sidebar-title">Current status.</h2>
                <div class="status-circle">
                    <div class="status-ring" style="--up-percentage: <?php echo $totalMonitors > 0 ? ($upMonitors / $totalMonitors * 100) : 0; ?>; --down-percentage: <?php echo $totalMonitors > 0 ? ($downMonitors / $totalMonitors * 100) : 0; ?>;">
                    </div>
                </div>
                <div class="status-stats">
                    <div class="stat-item">
                        <span class="stat-count stat-up"><?php echo $upMonitors; ?></span>
                        <span class="stat-label">Up</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-count stat-down"><?php echo $downMonitors; ?></span>
                        <span class="stat-label">Down</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-count stat-paused"><?php echo $pausedMonitors; ?></span>
                        <span class="stat-label">Paused</span>
                    </div>
                </div>
                <div class="monitor-usage">
                    Using <?php echo $totalMonitors; ?> of <?php echo $urlLimit; ?> monitors
                </div>
            </div>
            
            <!-- Last 24 Hours Block -->
            <div class="sidebar-block">
                <h2 class="sidebar-title">Last 24 hours.</h2>
                <div class="stats-grid">
                    <div class="stat-row">
                        <span class="stat-row-label">Overall uptime</span>
                        <span class="stat-row-value" style="color: var(--success);">
                            <?php echo number_format($last24Stats['overall_uptime'] ?? 100, 3); ?>%
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-row-label">Incidents</span>
                        <span class="stat-row-value">
                            <?php echo $last24Stats['total_incidents'] ?? 0; ?>
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-row-label">Without incidents</span>
                        <span class="stat-row-value">
                            <?php 
                            if (($last24Stats['total_incidents'] ?? 0) == 0) {
                                echo "24h";
                            } else {
                                echo "< 24h";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-row-label">Affected monitors</span>
                        <span class="stat-row-value">
                            <?php echo $last24Stats['affected_monitors'] ?? 0; ?>
                        </span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
    
    <script>
        // Toggle select all checkboxes
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.monitor-select');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        // Filter monitors by search
        function filterMonitors() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const monitors = document.querySelectorAll('.monitor-row');
            
            monitors.forEach(monitor => {
                const name = monitor.dataset.name.toLowerCase();
                const url = monitor.dataset.url.toLowerCase();
                
                if (name.includes(searchInput) || url.includes(searchInput)) {
                    monitor.style.display = 'flex';
                } else {
                    monitor.style.display = 'none';
                }
            });
        }
        
        // Sort monitors
        function sortMonitors() {
            const sortBy = document.getElementById('sortBy').value;
            const monitorsList = document.getElementById('monitorsList');
            const monitors = Array.from(document.querySelectorAll('.monitor-row'));
            
            monitors.sort((a, b) => {
                switch(sortBy) {
                    case 'status':
                        const statusOrder = {'down': 0, 'up': 1, 'paused': 2};
                        return statusOrder[a.dataset.status] - statusOrder[b.dataset.status];
                    case 'name':
                        return a.dataset.name.localeCompare(b.dataset.name);
                    case 'uptime':
                        const uptimeA = parseFloat(a.querySelector('.uptime-text').textContent);
                        const uptimeB = parseFloat(b.querySelector('.uptime-text').textContent);
                        return uptimeA - uptimeB;
                    case 'response':
                        const responseA = parseInt(a.querySelector('.monitor-type').textContent.match(/\d+/)[0]);
                        const responseB = parseInt(b.querySelector('.monitor-type').textContent.match(/\d+/)[0]);
                        return responseB - responseA;
                }
            });
            
            // Re-append sorted monitors
            monitors.forEach(monitor => monitorsList.appendChild(monitor));
        }
        
        // Show actions menu (placeholder)
        function showActions(monitorId) {
            // For now, redirect to project page
            window.location.href = 'project.php?id=' + monitorId;
        }
        
        // Handle bulk actions
        document.getElementById('bulkActions').addEventListener('change', function() {
            const action = this.value;
            const selected = document.querySelectorAll('.monitor-select:checked');
            
            if (selected.length === 0) {
                alert('Please select at least one monitor');
                this.value = 'Bulk actions';
                return;
            }
            
            if (action === 'delete') {
                if (confirm(`Are you sure you want to delete ${selected.length} monitor(s)?`)) {
                    // Implement delete logic
                }
            }
            
            this.value = 'Bulk actions';
        });
    </script>
</body>
</html>