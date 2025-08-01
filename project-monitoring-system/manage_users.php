<?php
session_start();
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/role_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is admin
if (!isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_user_role':
                $userId = intval($_POST['user_id']);
                $roleId = intval($_POST['role_id']);
                
                if (assignUserRole($pdo, $userId, $roleId)) {
                    $message = "User role updated successfully.";
                } else {
                    $error = "Failed to update user role.";
                }
                break;
                
            case 'update_user_limit':
                $userId = intval($_POST['user_id']);
                $urlLimit = intval($_POST['url_limit']);
                
                if (updateUserUrlLimit($pdo, $userId, $urlLimit)) {
                    $message = "User URL limit updated successfully.";
                } else {
                    $error = "Failed to update user URL limit.";
                }
                break;
        }
    }
}

// Get all users with their roles and URL counts
$stmt = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.created_at, u.url_limit as custom_limit,
           r.id as role_id, r.name as role_name,
           COALESCE(rul.url_limit, u.url_limit, 10) as effective_url_limit,
           COUNT(DISTINCT p.id) as project_count,
           COUNT(DISTINCT CASE WHEN p.project_url IS NOT NULL AND p.status = 'active' THEN p.id END) as url_count
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN role_url_limits rul ON r.id = rul.role_id
    LEFT JOIN projects p ON u.id = p.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Get all roles for dropdown
$roles = getAllRoles($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .users-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .users-table {
            background: var(--surface);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-top: 2rem;
        }
        
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .users-table th {
            background: var(--background);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .user-email {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .role-select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background: var(--background);
            font-size: 0.875rem;
        }
        
        .btn-update {
            padding: 0.5rem 1rem;
            background: #3B82F6;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        
        .btn-update:hover {
            background: #2563EB;
        }
        
        .btn-limit {
            padding: 0.5rem 0.75rem;
            background: #8B5CF6;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        
        .btn-limit:hover {
            background: #7C3AED;
        }
        
        .url-usage {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .url-progress {
            width: 100px;
            height: 8px;
            background: #E5E7EB;
            border-radius: 999px;
            overflow: hidden;
        }
        
        .url-progress-bar {
            height: 100%;
            background: #10B981;
            transition: width 0.3s;
        }
        
        .url-progress-bar.warning {
            background: #F59E0B;
        }
        
        .url-progress-bar.danger {
            background: #EF4444;
        }
        
        .url-count {
            font-size: 0.875rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .filters {
            background: var(--surface);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .filter-input {
            flex: 1;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background: var(--background);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="users-container">
        <h1>Manage Users</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- User Statistics -->
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo count($users); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Admin Users</div>
                <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role_name'] === 'Admin')); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Customer Users</div>
                <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role_name'] === 'Customer')); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Active URLs</div>
                <div class="stat-value"><?php echo array_sum(array_column($users, 'url_count')); ?></div>
            </div>
        </div>
        
        <!-- Search/Filter -->
        <div class="filters">
            <input type="text" class="filter-input" id="userSearch" placeholder="Search by name or email...">
            <select class="role-select" id="roleFilter">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role['name']); ?>">
                        <?php echo htmlspecialchars($role['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Projects</th>
                        <th>URL Usage</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                        $usagePercent = $user['effective_url_limit'] > 0 
                            ? ($user['url_count'] / $user['effective_url_limit']) * 100 
                            : 0;
                        $progressClass = $usagePercent >= 100 ? 'danger' : ($usagePercent >= 80 ? 'warning' : '');
                    ?>
                    <tr data-role="<?php echo htmlspecialchars($user['role_name'] ?? ''); ?>">
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span style="color: #10B981; font-size: 0.75rem;">(You)</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_user_role">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role_id" class="role-select" onchange="this.form.submit()">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                            <?php echo $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><?php echo $user['project_count']; ?></td>
                        <td>
                            <div class="url-usage">
                                <div class="url-progress">
                                    <div class="url-progress-bar <?php echo $progressClass; ?>" 
                                         style="width: <?php echo min(100, $usagePercent); ?>%"></div>
                                </div>
                                <span class="url-count">
                                    <?php echo $user['url_count']; ?> / 
                                    <?php echo $user['role_name'] === 'Admin' ? '∞' : $user['effective_url_limit']; ?>
                                </span>
                            </div>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="user-actions">
                                <button class="btn-limit" onclick="showUrlLimitModal(<?php echo $user['id']; ?>, <?php echo $user['custom_limit'] ?? $user['effective_url_limit']; ?>)">
                                    Set Limit
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- URL Limit Modal -->
    <div id="urlLimitModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: relative; background: white; margin: 10% auto; padding: 2rem; width: 90%; max-width: 400px; border-radius: 0.5rem;">
            <h3>Set Custom URL Limit</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_user_limit">
                <input type="hidden" name="user_id" id="limit_user_id">
                <div class="form-group" style="margin: 1rem 0;">
                    <label for="url_limit">URL Limit</label>
                    <input type="number" id="url_limit" name="url_limit" min="0" required 
                           style="width: 100%; padding: 0.5rem; border: 1px solid #E5E7EB; border-radius: 0.375rem;">
                    <small style="color: #6B7280;">Set to 0 for unlimited URLs</small>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">Update Limit</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUrlLimitModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('userSearch').addEventListener('input', function() {
            filterUsers();
        });
        
        document.getElementById('roleFilter').addEventListener('change', function() {
            filterUsers();
        });
        
        function filterUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const role = row.getAttribute('data-role');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesRole = roleFilter === '' || role === roleFilter;
                
                row.style.display = matchesSearch && matchesRole ? '' : 'none';
            });
        }
        
        function showUrlLimitModal(userId, currentLimit) {
            document.getElementById('limit_user_id').value = userId;
            document.getElementById('url_limit').value = currentLimit;
            document.getElementById('urlLimitModal').style.display = 'block';
        }
        
        function closeUrlLimitModal() {
            document.getElementById('urlLimitModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('urlLimitModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUrlLimitModal();
            }
        });
    </script>
</body>
</html>