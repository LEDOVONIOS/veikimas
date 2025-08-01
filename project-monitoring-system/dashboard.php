<?php
require_once 'db.php';
require_once 'includes/roles.php';
requireLogin();

// Get user role and URL information
$userId = $_SESSION['user_id'];
$userRole = getUserRole($userId);
$urlCount = getUserUrlCount($userId);
$urlLimit = getUserUrlLimit($userId);
$remainingUrls = getRemainingUrlSlots($userId);

// Get user's projects
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(DISTINCT i.id) as incident_count,
               COUNT(DISTINCT CASE WHEN i.status = 'Open' THEN i.id END) as open_incidents
        FROM projects p
        LEFT JOIN incidents i ON p.id = i.project_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY p.date_created DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    $projects = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Project Monitoring System</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f9fafb;
            color: #111827;
            line-height: 1.5;
        }
        
        /* Navigation Bar */
        .navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-brand h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }
        
        .nav-menu {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 32px;
        }
        
        .nav-menu a {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .nav-menu a:hover {
            color: #111827;
        }
        
        .nav-menu a.active {
            color: #111827;
            font-weight: 600;
        }
        
        .nav-user {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .btn-logout {
            background-color: #f3f4f6;
            color: #374151;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-logout:hover {
            background-color: #e5e7eb;
        }
        
        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        
        /* Add Project Button */
        .btn-add-project {
            background-color: #6366f1;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-add-project:hover {
            background-color: #4f46e5;
        }
        
        .plus-icon {
            font-size: 16px;
            line-height: 1;
        }
        
        /* URL Limit Info */
        .url-limit-info {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .url-limit-info strong {
            font-weight: 600;
        }
        
        /* Projects Grid */
        .projects-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, 300px);
            gap: 24px;
        }
        
        /* Project Card */
        .project-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0px 1px 4px rgba(0, 0, 0, 0.08);
            padding: 20px;
            width: 300px;
            transition: box-shadow 0.2s;
        }
        
        .project-card:hover {
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.12);
        }
        
        /* Card Header */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        
        .project-name {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            text-decoration: none;
            flex: 1;
            margin-right: 12px;
        }
        
        .project-name:hover {
            color: #6366f1;
        }
        
        /* Status Badge */
        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }
        
        /* Project URL */
        .project-url {
            margin-top: 4px;
            margin-bottom: 16px;
        }
        
        .project-url a {
            color: #3b82f6;
            font-size: 14px;
            text-decoration: underline;
            word-break: break-all;
        }
        
        .project-url a:hover {
            color: #2563eb;
        }
        
        /* Divider */
        .card-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 16px 0;
        }
        
        /* Card Footer */
        .card-footer {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #6b7280;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: #6b7280;
            margin-bottom: 24px;
        }
        
        .empty-state .btn-add-project {
            display: inline-flex;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar-container,
            .main-container {
                padding-left: 20px;
                padding-right: 20px;
            }
            
            .nav-menu {
                gap: 20px;
                font-size: 13px;
            }
            
            .nav-user span {
                display: none;
            }
            
            .projects-container {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                justify-items: center;
            }
            
            .project-card {
                width: 100%;
                max-width: 300px;
            }
        }
        
        @media (max-width: 640px) {
            .page-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .btn-add-project {
                width: 100%;
                justify-content: center;
            }
            
            .page-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="nav-brand">
                <h2>Project Monitor</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">Home</a></li>
                <li><a href="add_project.php">Add Project</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/manage-users.php">Manage Users</a></li>
                <?php endif; ?>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (<?php echo $userRole['name']; ?>)</span>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <div class="page-header">
            <h1 class="page-title">My Projects</h1>
            <a href="add_project.php" class="btn-add-project">
                <span class="plus-icon">+</span> Add New Project
            </a>
        </div>
        
        <?php if ($userRole['name'] === 'Customer'): ?>
            <div class="url-limit-info">
                <strong>URL Limit:</strong> You have used <?php echo $urlCount; ?> out of <?php echo $urlLimit; ?> URLs. 
                <?php if ($remainingUrls > 0): ?>
                    (<?php echo $remainingUrls; ?> remaining)
                <?php else: ?>
                    <span style="color: #dc3545;">(Limit reached)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <h3>No projects yet</h3>
                <p>Start monitoring your projects by creating your first one.</p>
                <a href="add_project.php" class="btn-add-project">
                    <span class="plus-icon">+</span> Create Project
                </a>
            </div>
        <?php else: ?>
            <div class="projects-container">
                <?php foreach ($projects as $project): ?>
                    <?php 
                    // Determine status
                    if ($project['open_incidents'] > 0) {
                        $statusLabel = 'Down';
                        $statusTextColor = '#dc2626';
                        $statusBgColor = '#fee2e2';
                    } else {
                        $statusLabel = 'Operational';
                        $statusTextColor = '#10b981';
                        $statusBgColor = '#d1fae5';
                    }
                    ?>
                    <div class="project-card">
                        <div class="card-header">
                            <a href="project.php?id=<?php echo $project['id']; ?>" class="project-name">
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </a>
                            <span class="status-badge" style="color: <?php echo $statusTextColor; ?>; background-color: <?php echo $statusBgColor; ?>;">
                                <?php echo $statusLabel; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($project['project_url'])): ?>
                            <div class="project-url">
                                <a href="<?php echo htmlspecialchars($project['project_url']); ?>" 
                                   target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars($project['project_url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-divider"></div>
                        
                        <div class="card-footer">
                            <span>Total Incidents: <?php echo $project['incident_count']; ?></span>
                            <span>Created: <?php echo date('M d, Y', strtotime($project['date_created'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>