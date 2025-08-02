<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if files exist before requiring them
$requiredFiles = ['db.php', 'includes/roles.php', 'includes/monitoring_functions.php'];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        die("Error: Required file '$file' not found. Please ensure all files are properly uploaded.");
    }
}

try {
    require_once 'db.php';
    require_once 'includes/roles.php';
    require_once 'includes/monitoring_functions.php';
} catch (Exception $e) {
    die("Error loading required files: " . $e->getMessage());
}

// Check if user is logged in
requireLogin();

// Initialize variables with default values
$projects = [];
$totalMonitors = 0;
$upMonitors = 0;
$downMonitors = 0;
$pausedMonitors = 0;
$userRole = ['name' => 'User'];
$urlCount = 0;
$urlLimit = 10;
$remainingUrls = 10;

try {
    // Get user role and URL information
    $userId = $_SESSION['user_id'];
    
    // Get user role with error handling
    $userRole = getUserRole($userId);
    if (!$userRole) {
        // If no role found, set default
        $userRole = ['name' => 'User', 'id' => 2];
    }
    
    // Get URL counts with error handling
    $urlCount = getUserUrlCount($userId);
    $urlLimit = getUserUrlLimit($userId);
    $remainingUrls = getRemainingUrlSlots($userId);
    
    // Get user's projects with monitoring status
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
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll();
    
    // Calculate statistics
    $totalMonitors = count($projects);
    
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
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Dashboard error: " . $e->getMessage());
    $errorMessage = "Unable to load dashboard data. Please try again later.";
    if (ini_get('display_errors')) {
        $errorMessage .= "<br>Debug info: " . $e->getMessage();
    }
}

// Function to format uptime percentage
function formatUptime($percentage) {
    if ($percentage === null) {
        return '<span class="text-muted">No data</span>';
    }
    
    $class = 'text-success';
    if ($percentage < 99) $class = 'text-warning';
    if ($percentage < 95) $class = 'text-danger';
    
    return '<span class="' . $class . '">' . number_format($percentage, 3) . '%</span>';
}

// Function to format status badge
function getStatusBadge($status, $lastChecked) {
    if ($lastChecked === null) {
        return '<span class="badge badge-secondary">Not Monitored</span>';
    }
    
    // Check if last check was more than 15 minutes ago
    $lastCheckedTime = strtotime($lastChecked);
    $timeDiff = time() - $lastCheckedTime;
    
    if ($timeDiff > 900) { // 15 minutes
        return '<span class="badge badge-warning">Paused</span>';
    }
    
    if ($status === '1') {
        return '<span class="badge badge-success">Up</span>';
    } elseif ($status === '0') {
        return '<span class="badge badge-danger">Down</span>';
    } else {
        return '<span class="badge badge-secondary">Unknown</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Project Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dashboard-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            border-left: 4px solid;
            padding: 1rem;
        }
        .stat-card.up { border-left-color: #28a745; }
        .stat-card.down { border-left-color: #dc3545; }
        .stat-card.paused { border-left-color: #ffc107; }
        .stat-card.total { border-left-color: #007bff; }
        .project-card {
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .project-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .response-time {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .url-limit-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
            padding: 0.75rem 1.25rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-line"></i> Project Monitor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_project.php">
                            <i class="fas fa-plus"></i> Add Project
                        </a>
                    </li>
                    <?php if (isAdmin($userId)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            <span class="badge bg-info"><?php echo htmlspecialchars($userRole['name']); ?></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- URL Limit Warning -->
        <?php if ($remainingUrls <= 2 && $remainingUrls > 0): ?>
        <div class="url-limit-warning">
            <i class="fas fa-exclamation-triangle"></i> You have <?php echo $remainingUrls; ?> URL slot(s) remaining out of <?php echo $urlLimit; ?>.
        </div>
        <?php elseif ($remainingUrls <= 0): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> You have reached your URL limit (<?php echo $urlLimit; ?> URLs). Please upgrade your plan or remove existing projects.
        </div>
        <?php endif; ?>

        <h1 class="mb-4">Dashboard</h1>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card stat-card total">
                    <div class="card-body">
                        <h5 class="card-title">Total Monitors</h5>
                        <h2 class="mb-0"><?php echo $totalMonitors; ?></h2>
                        <small class="text-muted">of <?php echo $urlLimit; ?> allowed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card stat-card up">
                    <div class="card-body">
                        <h5 class="card-title">Up</h5>
                        <h2 class="mb-0"><?php echo $upMonitors; ?></h2>
                        <small class="text-muted">Currently online</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card stat-card down">
                    <div class="card-body">
                        <h5 class="card-title">Down</h5>
                        <h2 class="mb-0"><?php echo $downMonitors; ?></h2>
                        <small class="text-muted">Currently offline</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card stat-card paused">
                    <div class="card-body">
                        <h5 class="card-title">Paused</h5>
                        <h2 class="mb-0"><?php echo $pausedMonitors; ?></h2>
                        <small class="text-muted">Not monitored</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects List -->
        <div class="row">
            <div class="col-12">
                <h2 class="mb-3">Your Monitors</h2>
                <?php if (empty($projects)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't added any projects yet. 
                    <a href="add_project.php" class="alert-link">Add your first project</a>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card project-card dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </h5>
                                    <?php echo getStatusBadge($project['current_status'], $project['last_checked']); ?>
                                </div>
                                
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars($project['url']); ?>
                                </p>
                                
                                <?php if ($project['last_response_time'] !== null): ?>
                                <p class="response-time mb-2">
                                    <i class="fas fa-tachometer-alt"></i> <?php echo number_format($project['last_response_time']); ?>ms
                                </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>
                                        <i class="fas fa-exclamation-circle"></i> 
                                        <?php echo $project['open_incidents']; ?> open incidents
                                    </span>
                                    <span>
                                        <?php 
                                        if ($project['last_checked']) {
                                            $lastChecked = new DateTime($project['last_checked']);
                                            $now = new DateTime();
                                            $diff = $now->diff($lastChecked);
                                            if ($diff->d > 0) {
                                                echo $diff->d . 'd ago';
                                            } elseif ($diff->h > 0) {
                                                echo $diff->h . 'h ago';
                                            } else {
                                                echo $diff->i . 'm ago';
                                            }
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-chart-line"></i> View Details
                                    </a>
                                    <?php if ($project['incident_count'] > 0): ?>
                                    <a href="project.php?id=<?php echo $project['id']; ?>#incidents" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-exclamation-triangle"></i> Incidents
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>