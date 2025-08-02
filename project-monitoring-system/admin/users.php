<?php
require_once '../db.php';
require_once '../includes/roles.php';

// Require admin access
requireLogin();
requireAdmin();

$success = '';
$error = '';

// Handle user role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $roleId = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);
    
    if ($userId && $roleId && $userId != $_SESSION['user_id']) {
        if (updateUserRole($userId, $roleId)) {
            $success = "User role updated successfully.";
        } else {
            $error = "Failed to update user role.";
        }
    } else {
        $error = "Cannot change your own role or invalid data provided.";
    }
}

// Handle project limit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_limit'])) {
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $limit = filter_var($_POST['project_limit'], FILTER_VALIDATE_INT);
    $message = sanitizeInput($_POST['limit_message'] ?? '');
    
    if ($userId && $limit !== false && $limit >= 0) {
        if (setUserProjectLimit($userId, $limit, $_SESSION['user_id'], $message)) {
            $success = "Project limit updated successfully.";
        } else {
            $error = "Failed to update project limit.";
        }
    } else {
        $error = "Invalid project limit value.";
    }
}

// Get all users with pagination
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$users = getUsersWithRoles($perPage, $offset);
$roles = getAllRoles();

// Get total user count for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    
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
        
        .users-table {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .users-table table {
            width: 100%;
        }
        
        .users-table th {
            background-color: var(--bg-tertiary);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .users-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .user-email {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .role-select, .limit-input {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .limit-input {
            width: 80px;
        }
        
        .update-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .update-btn:hover {
            background-color: #5558e3;
            transform: translateY(-1px);
        }
        
        .view-projects-btn {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .view-projects-btn:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .stats-badge.normal {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stats-badge.warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stats-badge.danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 32px;
        }
        
        .pagination a {
            padding: 8px 12px;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .pagination a.active {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .limit-message-input {
            width: 200px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .admin-nav {
            background-color: var(--bg-secondary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
        }
        
        .admin-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .admin-nav a:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .admin-nav a.active {
            background-color: var(--accent);
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="admin-header-content">
            <h1 class="admin-title">
                <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket">
                User Management
                <span class="admin-badge">ADMIN</span>
            </h1>
            <nav class="nav-menu" style="background: none; padding: 0;">
                <a href="../dashboard.php" class="nav-item">Dashboard</a>
                <a href="../logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <nav class="admin-nav">
            <a href="users.php" class="active">Users</a>
            <a href="projects.php">All Projects</a>
            <a href="settings.php">Settings</a>
        </nav>
        
        <?php if ($success): ?>
            <div class="alert alert-success mb-3">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error mb-3">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Projects</th>
                        <th>Project Limit</th>
                        <th>Limit Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $projectUsage = $user['project_count'] . '/' . $user['project_limit'];
                        $usagePercent = $user['project_limit'] > 0 ? ($user['project_count'] / $user['project_limit'] * 100) : 0;
                        $usageClass = $usagePercent >= 100 ? 'danger' : ($usagePercent >= 80 ? 'warning' : 'normal');
                    ?>
                    <tr>
                        <td>
                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role_id" class="role-select" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button type="submit" name="update_role" class="update-btn">Update</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <span class="stats-badge <?php echo $usageClass; ?>">
                                <?php echo $projectUsage; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="number" name="project_limit" value="<?php echo $user['project_limit']; ?>" 
                                       min="0" max="9999" class="limit-input">
                        </td>
                        <td>
                                <input type="text" name="limit_message" value="<?php echo htmlspecialchars($user['limit_message'] ?? ''); ?>" 
                                       placeholder="Optional message" class="limit-message-input">
                                <button type="submit" name="update_limit" class="update-btn">Set Limit</button>
                            </form>
                        </td>
                        <td>
                            <a href="user-projects.php?user_id=<?php echo $user['id']; ?>" class="view-projects-btn">
                                View Projects
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>