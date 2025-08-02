<?php
require_once '../db.php';
require_once '../includes/roles.php';
require_once '../includes/monitoring_functions.php';

// Require admin access
requireLogin();
requireAdmin();

$userId = filter_var($_GET['user_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$userId) {
    header("Location: users.php");
    exit();
}

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit();
}

// Get user's projects
$projects = getAdminAccessibleProjects($_SESSION['user_id'], $userId);

// Log admin access (only log once per session per user)
$sessionKey = 'admin_viewed_user_' . $userId;
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = true;
    // Log access to first project if exists
    if (!empty($projects)) {
        logAdminProjectAccess($_SESSION['user_id'], $projects[0]['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['full_name']); ?>'s Projects - Admin View</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Dark Theme CSS -->
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    
    <style>
        body {
            background-color: var(--bg-primary);
        }
        
        .admin-header {
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 24px 0;
            margin-bottom: 32px;
        }
        
        .admin-header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header img {
            height: 40px;
            margin-right: 16px;
        }
        
        .admin-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }
        
        .admin-badge {
            background-color: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 12px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .user-info-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .user-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .user-details h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .user-meta {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .role-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .role-badge.admin {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--accent);
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .project-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            position: relative;
        }
        
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .read-only-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .project-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .project-url {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 16px;
            word-break: break-all;
        }
        
        .project-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .project-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .view-btn {
            flex: 1;
            padding: 10px 16px;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .view-btn:hover {
            background-color: var(--bg-hover);
            transform: translateY(-1px);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--text-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-indicator.up {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-indicator.down {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-indicator.unknown {
            background-color: var(--bg-tertiary);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="admin-header-content">
            <h1 class="admin-title">
                <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket">
                User Projects
                <span class="admin-badge">ADMIN VIEW</span>
            </h1>
            <nav class="nav-menu" style="background: none; padding: 0;">
                <a href="../dashboard.php" class="nav-item">Dashboard</a>
                <a href="../logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <a href="users.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 12L6 8l4-4"></path>
            </svg>
            Back to User Management
        </a>
        
        <div class="user-info-card">
            <div class="user-info-header">
                <div class="user-details">
                    <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <div class="user-meta">
                        <?php echo htmlspecialchars($user['email']); ?> • 
                        Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>
                <div class="role-badge <?php echo strtolower($user['role_name']); ?>">
                    <?php echo htmlspecialchars($user['role_name']); ?>
                </div>
            </div>
            <?php 
            $limitDetails = getUserProjectLimitDetails($userId);
            ?>
            <div class="user-meta">
                Projects: <?php echo $limitDetails['current_projects']; ?> / <?php echo $limitDetails['max_projects']; ?>
                <?php if ($limitDetails['limit_message']): ?>
                    • Limit Note: <?php echo htmlspecialchars($limitDetails['limit_message']); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No Projects Found</h3>
                <p>This user hasn't created any projects yet.</p>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): 
                    // Get project status
                    $stmt = $pdo->prepare("SELECT is_up FROM uptime_logs WHERE project_id = ? ORDER BY checked_at DESC LIMIT 1");
                    $stmt->execute([$project['id']]);
                    $status = $stmt->fetch();
                    $isUp = $status ? ($status['is_up'] ? 'up' : 'down') : 'unknown';
                    
                    // Get project statistics
                    $uptime7d = calculateUptime($pdo, $project['id'], 7);
                ?>
                <div class="project-card">
                    <span class="read-only-badge">READ-ONLY</span>
                    <h3 class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></h3>
                    <?php if ($project['project_url']): ?>
                        <div class="project-url"><?php echo htmlspecialchars($project['project_url']); ?></div>
                    <?php endif; ?>
                    
                    <div class="status-indicator <?php echo $isUp; ?>">
                        <span class="status-dot <?php echo $isUp; ?>" style="width: 8px; height: 8px; border-radius: 50%; background-color: currentColor; display: inline-block;"></span>
                        <?php echo ucfirst($isUp); ?>
                    </div>
                    
                    <div class="project-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $uptime7d ? number_format($uptime7d['percentage'], 1) : '100'; ?>%</div>
                            <div class="stat-label">7-day Uptime</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo date('M d', strtotime($project['date_created'])); ?></div>
                            <div class="stat-label">Created</div>
                        </div>
                    </div>
                    
                    <div class="project-actions">
                        <a href="../project.php?id=<?php echo $project['id']; ?>&admin_view=1" class="view-btn">
                            View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>