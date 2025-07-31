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
    <title>Notifications - Project Monitoring System</title>
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
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        
        <div class="notifications-page">
            <div class="page-header">
                <h1>Notifications</h1>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-secondary">
                            Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="notification-filters">
                <a href="?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Notifications
                </a>
                <a href="?filter=unread" class="filter-link <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    Unread (<?php echo $unreadCount; ?>)
                </a>
            </div>
            
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <p>No notifications to display.</p>
                    <p class="text-muted">You'll receive notifications when your projects have important updates.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="notification-icon">
                                <?php
                                echo match($notification['type']) {
                                    'down' => '🔴',
                                    'up' => '✅',
                                    'ssl_expiry' => '⚠️',
                                    'domain_expiry' => '⚠️',
                                    'cron_failed' => '❌',
                                    default => '📢'
                                };
                                ?>
                            </div>
                            <div class="notification-content">
                                <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="notification-meta">
                                    <span class="project-name">
                                        <a href="project.php?id=<?php echo $notification['project_id']; ?>">
                                            <?php echo htmlspecialchars($notification['project_name']); ?>
                                        </a>
                                    </span>
                                    <span class="notification-time">
                                        <?php echo date('M d, Y g:i A', strtotime($notification['sent_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <form method="POST" class="notification-action">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="mark_read" class="btn-icon" title="Mark as read">
                                        ✓
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        .notifications-page {
            background-color: var(--surface);
            border-radius: 0.5rem;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            margin: 0;
        }
        
        .notification-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .filter-link {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        
        .filter-link:hover {
            background-color: var(--background);
        }
        
        .filter-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .notification-item {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            background-color: var(--background);
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .notification-item:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        
        .notification-item.unread {
            background-color: #EEF2FF;
            border-color: var(--primary-color);
        }
        
        .notification-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            color: var(--text-primary);
        }
        
        .notification-content p {
            margin: 0 0 0.75rem 0;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .project-name a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .project-name a:hover {
            text-decoration: underline;
        }
        
        .notification-action {
            flex-shrink: 0;
        }
        
        .btn-icon {
            background-color: var(--success-color);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: #4B5563;
        }
    </style>
</body>
</html>