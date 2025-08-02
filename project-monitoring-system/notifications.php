<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/notification_handler.php';
requireLogin();

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
    if ($notificationId) {
        markNotificationRead($pdo, $notificationId, $_SESSION['user_id']);
    }
    header("Location: notifications.php");
    exit();
}

// Handle marking all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    header("Location: notifications.php");
    exit();
}

// Get notifications
$filter = $_GET['filter'] ?? 'all';
$notifications = getUserNotifications($pdo, $_SESSION['user_id'], 50, $filter === 'unread');
$unreadCount = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Uptime Monitoring System</title>
    
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
        
        .page-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }
        
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
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            background-color: var(--bg-secondary);
            padding: 8px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }
        
        .filter-tab:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .filter-tab.active {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .unread-badge {
            background-color: var(--accent);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 6px;
            font-weight: 600;
        }
        
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .notification-item {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            gap: 16px;
            transition: all 0.2s;
            position: relative;
        }
        
        .notification-item:hover {
            background-color: var(--bg-hover);
            transform: translateY(-1px);
        }
        
        .notification-item.unread {
            border-left: 3px solid var(--accent);
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            background-color: var(--bg-tertiary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .notification-message {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .notification-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .notification-meta a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .notification-meta a:hover {
            text-decoration: underline;
        }
        
        .notification-actions {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .mark-read-btn {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s;
        }
        
        .mark-read-btn:hover {
            background-color: var(--bg-hover);
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
        
        .empty-state h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .type-down { background-color: rgba(239, 68, 68, 0.1); }
        .type-up { background-color: rgba(16, 185, 129, 0.1); }
        .type-ssl { background-color: rgba(245, 158, 11, 0.1); }
        .type-domain { background-color: rgba(245, 158, 11, 0.1); }
        .type-cron { background-color: rgba(239, 68, 68, 0.1); }
        
        /* Notification type styling */
        .notification-item[data-type="down"] .notification-icon {
            background-color: rgba(239, 68, 68, 0.1);
        }
        
        .notification-item[data-type="up"] .notification-icon {
            background-color: rgba(16, 185, 129, 0.1);
        }
        
        .notification-item[data-type="ssl_expiry"] .notification-icon,
        .notification-item[data-type="domain_expiry"] .notification-icon {
            background-color: rgba(245, 158, 11, 0.1);
        }
        
        .notification-item[data-type="cron_failed"] .notification-icon {
            background-color: rgba(239, 68, 68, 0.1);
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
        
        /* Time ago formatting */
        .time-ago {
            position: relative;
        }
        
        .time-ago:hover::after {
            content: attr(data-time);
            position: absolute;
            bottom: 100%;
            left: 0;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            margin-bottom: 4px;
            z-index: 10;
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
                <a href="notifications.php" class="nav-item active">Notifications</a>
                <?php if (isAdmin()): ?>
                <a href="admin/users.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </nav>

    <div class="page-container">
        <a href="dashboard.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 12L6 8l4-4"></path>
            </svg>
            Back to dashboard
        </a>
        
        <div class="page-header">
            <h1 class="page-title">Notifications</h1>
            <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-secondary">
                        Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Notifications
            </a>
            <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                Unread
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔔</div>
                <h3>No notifications yet</h3>
                <p>You'll receive notifications when your monitors have important updates.</p>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): 
                    // Calculate time ago
                    $sentTime = new DateTime($notification['sent_at']);
                    $now = new DateTime();
                    $diff = $now->diff($sentTime);
                    
                    if ($diff->days > 0) {
                        $timeAgo = $diff->days . 'd ago';
                    } elseif ($diff->h > 0) {
                        $timeAgo = $diff->h . 'h ago';
                    } elseif ($diff->i > 0) {
                        $timeAgo = $diff->i . 'm ago';
                    } else {
                        $timeAgo = 'Just now';
                    }
                ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" data-type="<?php echo $notification['type']; ?>">
                        <div class="notification-icon">
                            <?php
                            echo match($notification['type']) {
                                'down' => '🔴',
                                'up' => '✅',
                                'ssl_expiry' => '🔒',
                                'domain_expiry' => '🌐',
                                'cron_failed' => '❌',
                                default => '📢'
                            };
                            ?>
                        </div>
                        <div class="notification-content">
                            <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-meta">
                                <span>
                                    <a href="project.php?id=<?php echo $notification['project_id']; ?>">
                                        <?php echo htmlspecialchars($notification['project_name']); ?>
                                    </a>
                                </span>
                                <span class="time-ago" data-time="<?php echo date('M d, Y g:i A', strtotime($notification['sent_at'])); ?>">
                                    <?php echo $timeAgo; ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                            <div class="notification-actions">
                                <form method="POST">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="mark_read" class="mark-read-btn" title="Mark as read">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 8l2 2 4-4"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>