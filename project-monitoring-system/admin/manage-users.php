<?php
require_once '../db.php';
require_once '../includes/roles.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();
requireAdmin(); // Only admins can access this page

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_role':
                $userId = intval($_POST['user_id']);
                $roleId = intval($_POST['role_id']);
                
                if (updateUserRole($userId, $roleId)) {
                    $message = "User role updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to update user role.";
                    $messageType = 'error';
                }
                break;
                
            case 'update_url_limit':
                $userId = intval($_POST['user_id']);
                $urlLimit = intval($_POST['url_limit']);
                
                if ($urlLimit > 0 && setUserUrlLimit($userId, $urlLimit)) {
                    $message = "URL limit updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to update URL limit. Please enter a valid positive number.";
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all roles for the dropdown
$roles = getAllRoles();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get users with their roles and URL counts
$users = getUsersWithRoles($perPage, $offset);

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
    <title>Manage Users - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .users-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        
        .users-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .role-badge.admin {
            background-color: #dc3545;
            color: white;
        }
        
        .role-badge.customer {
            background-color: #28a745;
            color: white;
        }
        
        .url-count {
            font-weight: 500;
        }
        
        .url-count.exceeded {
            color: #dc3545;
        }
        
        .action-form {
            display: inline-block;
            margin-right: 10px;
        }
        
        .action-form select,
        .action-form input[type="number"] {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .action-form button {
            padding: 4px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
        }
        
        .btn-update {
            background-color: #007bff;
            color: white;
        }
        
        .btn-update:hover {
            background-color: #0056b3;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination .current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Project Monitor - Admin</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="../dashboard.php">Dashboard</a></li>
                <li><a href="manage-users.php" class="active">Manage Users</a></li>
                <li class="nav-user">
                    <span>Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="admin-container">
        <div class="admin-header">
            <h1>Manage Users</h1>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span>Total Users: <?php echo $totalUsers; ?></span>
                <a href="create-user.php" class="btn btn-primary">Create New User</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>URLs (Used/Limit)</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge <?php echo strtolower($user['role_name']); ?>">
                                <?php echo $user['role_name']; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $isExceeded = $user['role_name'] === 'Customer' && $user['url_count'] >= $user['url_limit'];
                            ?>
                            <span class="url-count <?php echo $isExceeded ? 'exceeded' : ''; ?>">
                                <?php echo $user['url_count']; ?> / <?php echo $user['role_name'] === 'Admin' ? '∞' : $user['url_limit']; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                        <td>
                            <!-- Update Role Form -->
                            <form method="POST" class="action-form">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role_id" onchange="this.form.submit()">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                                <?php echo $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo $role['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            
                            <!-- Update URL Limit Form (only for customers) -->
                            <?php if ($user['role_name'] === 'Customer'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="action" value="update_url_limit">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="number" name="url_limit" value="<?php echo $user['url_limit']; ?>" 
                                           min="1" max="9999" style="width: 60px;">
                                    <button type="submit" class="btn-update">Set Limit</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">First</a>
                    <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next</a>
                    <a href="?page=<?php echo $totalPages; ?>">Last</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>