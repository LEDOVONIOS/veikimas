<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

$pageTitle = 'Dashboard';
$user = $auth->getUser();
$monitor = new Monitor();

// Get filter parameters
$search = sanitize(getGet('search', ''));
$tag = sanitize(getGet('tag', ''));
$status_filter = sanitize(getGet('status', ''));
$sort = sanitize(getGet('sort', 'down_first'));
$user_id = (int) getGet('user_id', 0);

// Build query with filters
$where_conditions = [];
$params = [];

if ($auth->isAdmin()) {
    $base_query = "SELECT p.*, u.username, u.email as user_email FROM " . DB_PREFIX . "projects p LEFT JOIN " . DB_PREFIX . "users u ON p.user_id = u.id";
    
    // If user_id is specified, filter by that user
    if ($user_id > 0) {
        $where_conditions[] = "p.user_id = ?";
        $params[] = $user_id;
    }
} else {
    $base_query = "SELECT * FROM " . DB_PREFIX . "projects";
    $where_conditions[] = "user_id = ?";
    $params[] = $user['id'];
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR url LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Status filter
if (!empty($status_filter)) {
    if ($status_filter === 'up') {
        $where_conditions[] = "current_status = 'up'";
    } elseif ($status_filter === 'down') {
        $where_conditions[] = "current_status = 'down'";
    } elseif ($status_filter === 'paused') {
        $where_conditions[] = "status = 'paused'";
    }
}

// Build final query
if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Apply sorting
switch ($sort) {
    case 'down_first':
        $base_query .= " ORDER BY CASE WHEN current_status = 'down' THEN 0 ELSE 1 END, name ASC";
        break;
    case 'name_asc':
        $base_query .= " ORDER BY name ASC";
        break;
    case 'name_desc':
        $base_query .= " ORDER BY name DESC";
        break;
    case 'newest':
        $base_query .= " ORDER BY created_at DESC";
        break;
    default:
        $base_query .= " ORDER BY created_at DESC";
}

$projects = $db->fetchAllArray($base_query, $params);

// Calculate statistics
$totalProjects = count($projects);
$onlineProjects = 0;
$offlineProjects = 0;
$pausedProjects = 0;

foreach ($projects as &$project) {
    if ($project['status'] === 'paused') {
        $pausedProjects++;
    } elseif ($project['current_status'] === 'up') {
        $onlineProjects++;
    } elseif ($project['current_status'] === 'down') {
        $offlineProjects++;
    }
    
    // Get uptime percentage
    $project['uptime_24h'] = $monitor->getUptimePercentage($project['id'], 24);
    $project['response_stats'] = $monitor->getResponseTimeStats($project['id'], date('Y-m-d H:i:s', strtotime('-24 hours')));
}

// Get recent incidents for sidebar
$incidents_24h = $db->fetchValue(
    "SELECT COUNT(DISTINCT project_id) FROM " . DB_PREFIX . "incident_logs 
     WHERE started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" . 
     ($auth->isAdmin() ? "" : " AND project_id IN (SELECT id FROM " . DB_PREFIX . "projects WHERE user_id = " . $user['id'] . ")")
);

// Calculate overall uptime
$total_checks_24h = $db->fetchOne(
    "SELECT COUNT(*) as total, SUM(is_up) as up_count 
     FROM " . DB_PREFIX . "monitor_logs 
     WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" .
     ($auth->isAdmin() ? "" : " AND project_id IN (SELECT id FROM " . DB_PREFIX . "projects WHERE user_id = " . $user['id'] . ")")
);

$overall_uptime = 100;
if ($total_checks_24h && $total_checks_24h['total'] > 0) {
    $overall_uptime = ($total_checks_24h['up_count'] / $total_checks_24h['total']) * 100;
}

// Custom CSS for modern dark theme
$additionalCSS = '
<style>
/* Dark theme overrides */
.page-header {
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border-color);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* Toolbar styles */
.toolbar {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.toolbar .form-control,
.toolbar .custom-select {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.toolbar .form-control:focus,
.toolbar .custom-select:focus {
    background: var(--bg-tertiary);
    border-color: var(--primary-color);
    color: var(--text-primary);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.toolbar .form-control::placeholder {
    color: var(--text-muted);
}

/* Monitor list styles */
.monitor-list {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.monitor-item {
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem;
    transition: all 0.2s ease;
}

.monitor-item:hover {
    background: var(--bg-hover);
}

.monitor-item:last-child {
    border-bottom: none;
}

/* Status indicator */
.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
}

.status-indicator.up {
    background: var(--success-color);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.status-indicator.down {
    background: var(--danger-color);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
    animation: pulse 2s infinite;
}

.status-indicator.paused {
    background: var(--text-muted);
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
    50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.1); }
    100% { box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
}

/* Monitor info */
.monitor-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.monitor-name-link {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

.monitor-name-link:hover {
    color: var(--primary-color);
    text-decoration: none;
}

.monitor-url {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.monitor-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Uptime bar */
.uptime-bar {
    background: var(--bg-tertiary);
    border-radius: 4px;
    height: 8px;
    overflow: hidden;
    margin-top: 0.25rem;
}

.uptime-fill {
    background: var(--success-color);
    height: 100%;
    transition: width 0.3s ease;
}

.uptime-fill.warning {
    background: var(--warning-color);
}

.uptime-fill.danger {
    background: var(--danger-color);
}

/* Sidebar */
.sidebar {
    position: sticky;
    top: 1rem;
}

.sidebar-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.sidebar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

/* Status circle */
.status-circle {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
    position: relative;
}

.status-circle svg {
    transform: rotate(-90deg);
}

.status-circle-bg {
    fill: none;
    stroke: var(--bg-tertiary);
    stroke-width: 8;
}

.status-circle-fill {
    fill: none;
    stroke: var(--success-color);
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}

.status-circle-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.status-circle-text .percentage {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.status-circle-text .label {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Stats list */
.stats-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.stats-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    color: var(--text-secondary);
}

.stats-list .value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Action buttons */
.btn-new-monitor {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-new-monitor:hover {
    background: var(--secondary-color);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-action {
    background: transparent;
    border: none;
    color: var(--text-muted);
    padding: 0.25rem 0.5rem;
    transition: all 0.2s ease;
}

.btn-action:hover {
    color: var(--text-primary);
}

/* Checkbox styling */
.custom-control-input:checked ~ .custom-control-label::before {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .monitor-item {
        padding: 1rem;
    }
    
    .toolbar .form-group {
        margin-bottom: 0.5rem;
    }
}
</style>';

include 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main content -->
        <div class="col-lg-9">
            <!-- Page header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Monitors.</h1>
                    <?php if ($auth->isAdmin() && $user_id > 0): ?>
                        <?php 
                        $viewed_user = $db->fetchOne("SELECT username FROM " . DB_PREFIX . "users WHERE id = ?", [$user_id]);
                        if ($viewed_user): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user"></i> Viewing projects for user: <strong><?php echo htmlspecialchars($viewed_user['username']); ?></strong>
                                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary ml-2">
                                    <i class="fas fa-times"></i> Clear filter
                                </a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($auth->canCreateProject()): ?>
                    <a href="project-add.php" class="btn btn-new-monitor">
                        <i class="fas fa-plus"></i> New monitor
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Toolbar -->
            <div class="toolbar">
                <form method="GET" action="">
                    <?php if ($auth->isAdmin() && $user_id > 0): ?>
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <?php endif; ?>
                    <div class="form-row align-items-center">
                        <div class="col-auto">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="selectAll">
                                <label class="custom-control-label" for="selectAll"></label>
                            </div>
                        </div>
                        
                        <div class="col-auto">
                            <select class="custom-select" id="bulkActions" disabled>
                                <option value="">Bulk actions</option>
                                <option value="pause">Pause selected</option>
                                <option value="resume">Resume selected</option>
                                <option value="delete">Delete selected</option>
                            </select>
                        </div>
                        
                        <div class="col-auto">
                            <select class="custom-select" name="tag">
                                <option value="">All tags</option>
                                <!-- Add tags here if implemented -->
                            </select>
                        </div>
                        
                        <div class="col">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by name or url" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-auto">
                            <select class="custom-select" name="sort">
                                <option value="down_first" <?php echo $sort === 'down_first' ? 'selected' : ''; ?>>Down first</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                            </select>
                        </div>
                        
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-light">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Monitors list -->
            <div class="monitor-list">
                <?php if (empty($projects)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-server fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No monitors found. Create your first monitor to get started.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <div class="monitor-item" data-monitor-id="<?php echo $project['id']; ?>">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input monitor-checkbox" 
                                               id="monitor-<?php echo $project['id']; ?>">
                                        <label class="custom-control-label" for="monitor-<?php echo $project['id']; ?>"></label>
                                    </div>
                                </div>
                                
                                <div class="col-auto">
                                    <span class="status-indicator <?php echo $project['status'] === 'paused' ? 'paused' : $project['current_status']; ?>"></span>
                                </div>
                                
                                <div class="col">
                                    <div class="monitor-name">
                                        <a href="project.php?id=<?php echo $project['id']; ?>" class="monitor-name-link">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </a>
                                    </div>
                                    <div class="monitor-url">
                                        <?php 
                                        $urlParts = parse_url($project['url']);
                                        echo htmlspecialchars($urlParts['host'] ?? $project['url']);
                                        ?>
                                    </div>
                                    <div class="monitor-meta mt-1">
                                        <span class="mr-3">
                                            <i class="fas fa-globe"></i> 
                                            <?php echo strtoupper($project['method']); ?> 
                                            (<?php echo round($project['uptime_24h'], 1); ?>%)
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            Every <?php echo round($project['check_interval'] / 60); ?> min
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-lg-3">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1 mr-3">
                                            <div class="small text-muted mb-1">
                                                Uptime: <?php echo number_format($project['uptime_24h'], 2); ?>%
                                            </div>
                                            <div class="uptime-bar">
                                                <?php
                                                $uptimeClass = '';
                                                if ($project['uptime_24h'] < 95) {
                                                    $uptimeClass = 'danger';
                                                } elseif ($project['uptime_24h'] < 99) {
                                                    $uptimeClass = 'warning';
                                                }
                                                ?>
                                                <div class="uptime-fill <?php echo $uptimeClass; ?>" 
                                                     style="width: <?php echo $project['uptime_24h']; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-auto">
                                    <div class="dropdown">
                                        <button class="btn btn-action" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="project.php?id=<?php echo $project['id']; ?>">
                                                <i class="fas fa-eye"></i> View details
                                            </a>
                                            <a class="dropdown-item" href="project-edit.php?id=<?php echo $project['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($project['status'] === 'paused'): ?>
                                                <a class="dropdown-item" href="project-action.php?id=<?php echo $project['id']; ?>&action=resume">
                                                    <i class="fas fa-play"></i> Resume
                                                </a>
                                            <?php else: ?>
                                                <a class="dropdown-item" href="project-action.php?id=<?php echo $project['id']; ?>&action=pause">
                                                    <i class="fas fa-pause"></i> Pause
                                                </a>
                                            <?php endif; ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger delete-confirm" 
                                               href="project-delete.php?id=<?php echo $project['id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-3 mt-4 mt-lg-0">
            <div class="sidebar">
                <!-- Current status -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">Current status.</h3>
                    
                    <div class="status-circle">
                        <?php
                        $healthPercentage = $totalProjects > 0 ? ($onlineProjects / $totalProjects) * 100 : 100;
                        $circumference = 2 * M_PI * 54; // radius = 54
                        $dashOffset = $circumference * (1 - $healthPercentage / 100);
                        ?>
                        <svg width="120" height="120">
                            <circle class="status-circle-bg" cx="60" cy="60" r="54"></circle>
                            <circle class="status-circle-fill" cx="60" cy="60" r="54"
                                    style="stroke-dasharray: <?php echo $circumference; ?>;
                                           stroke-dashoffset: <?php echo $dashOffset; ?>"></circle>
                        </svg>
                        <div class="status-circle-text">
                            <div class="percentage"><?php echo round($healthPercentage); ?>%</div>
                            <div class="label">Healthy</div>
                        </div>
                    </div>
                    
                    <ul class="stats-list">
                        <li>
                            <span><i class="fas fa-check-circle text-success"></i> Up</span>
                            <span class="value"><?php echo $onlineProjects; ?></span>
                        </li>
                        <li>
                            <span><i class="fas fa-times-circle text-danger"></i> Down</span>
                            <span class="value"><?php echo $offlineProjects; ?></span>
                        </li>
                        <li>
                            <span><i class="fas fa-pause-circle text-muted"></i> Paused</span>
                            <span class="value"><?php echo $pausedProjects; ?></span>
                        </li>
                        <li class="mt-2 pt-2 border-top">
                            <span>Using</span>
                            <span class="value"><?php echo $totalProjects; ?> of <?php echo $auth->isAdmin() ? '∞' : $auth->getProjectLimit(); ?> monitors</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Last 24 hours -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">Last 24 hours.</h3>
                    
                    <ul class="stats-list">
                        <li>
                            <span>Overall uptime</span>
                            <span class="value <?php echo $overall_uptime < 99 ? 'text-warning' : 'text-success'; ?>">
                                <?php echo number_format($overall_uptime, 3); ?>%
                            </span>
                        </li>
                        <li>
                            <span>Incidents</span>
                            <span class="value <?php echo $incidents_24h > 0 ? 'text-warning' : ''; ?>">
                                <?php echo $incidents_24h; ?>
                            </span>
                        </li>
                        <li>
                            <span>Without incidents</span>
                            <span class="value">
                                <?php
                                // Calculate time without incidents
                                $lastIncident = $db->fetchValue(
                                    "SELECT MAX(started_at) FROM " . DB_PREFIX . "incident_logs" .
                                    ($auth->isAdmin() ? "" : " WHERE project_id IN (SELECT id FROM " . DB_PREFIX . "projects WHERE user_id = " . $user['id'] . ")")
                                );
                                
                                if ($lastIncident) {
                                    $timeSince = time() - strtotime($lastIncident);
                                    if ($timeSince < 3600) {
                                        echo round($timeSince / 60) . 'm';
                                    } elseif ($timeSince < 86400) {
                                        echo round($timeSince / 3600, 1) . 'h';
                                    } else {
                                        echo round($timeSince / 86400, 1) . 'd';
                                    }
                                } else {
                                    echo 'Always';
                                }
                                ?>
                            </span>
                        </li>
                        <li>
                            <span>Affected monitors</span>
                            <span class="value"><?php echo $offlineProjects; ?></span>
                        </li>
                    </ul>
                </div>
                
                <?php if (!$auth->canCreateProject() && !$auth->isAdmin()): ?>
                <div class="sidebar-card bg-warning text-dark">
                    <h4 class="font-weight-bold mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Limit Reached
                    </h4>
                    <p class="mb-0">You've reached your monitor limit. Contact <a href="mailto:info@seorocket.lt" class="text-dark font-weight-bold">info@seorocket.lt</a> to upgrade.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Select all checkbox functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.monitor-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

// Individual checkbox change
document.querySelectorAll('.monitor-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checkedBoxes = document.querySelectorAll('.monitor-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    bulkActions.disabled = checkedBoxes.length === 0;
}

// Bulk actions handler
document.getElementById('bulkActions').addEventListener('change', function() {
    const action = this.value;
    if (!action) return;
    
    const checkedBoxes = document.querySelectorAll('.monitor-checkbox:checked');
    const monitorIds = Array.from(checkedBoxes).map(cb => 
        cb.closest('.monitor-item').dataset.monitorId
    );
    
    if (monitorIds.length === 0) return;
    
    if (action === 'delete') {
        if (!confirm('Are you sure you want to delete ' + monitorIds.length + ' monitor(s)?')) {
            this.value = '';
            return;
        }
    }
    
    // Submit bulk action
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'bulk-action.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    monitorIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'monitors[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
});
</script>

<?php include 'templates/footer.php'; ?>