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
            case 'create_role':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $urlLimit = intval($_POST['url_limit']);
                
                if (empty($name)) {
                    $error = "Role name is required.";
                } else {
                    $roleId = createRole($pdo, $name, $description, $urlLimit);
                    if ($roleId) {
                        $message = "Role created successfully.";
                    } else {
                        $error = "Failed to create role. Name might already exist.";
                    }
                }
                break;
                
            case 'update_role':
                $roleId = intval($_POST['role_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $urlLimit = intval($_POST['url_limit']);
                
                if (updateRole($pdo, $roleId, $name, $description, $urlLimit)) {
                    $message = "Role updated successfully.";
                } else {
                    $error = "Failed to update role.";
                }
                break;
                
            case 'delete_role':
                $roleId = intval($_POST['role_id']);
                if (deleteRole($pdo, $roleId)) {
                    $message = "Role deleted successfully.";
                } else {
                    $error = "Cannot delete system roles (Admin/Customer).";
                }
                break;
        }
    }
}

// Get all roles
$roles = getAllRoles($pdo);
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .roles-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .role-form {
            background: var(--surface);
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            font-size: 1rem;
            background: var(--background);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .roles-table {
            background: var(--surface);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .roles-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .roles-table th,
        .roles-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .roles-table th {
            background: var(--background);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .role-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit,
        .btn-delete {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #3B82F6;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563EB;
        }
        
        .btn-delete {
            background: #EF4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #DC2626;
        }
        
        .system-role {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        
        .url-limit-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #E0E7FF;
            color: #4338CA;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .user-count-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #F3F4F6;
            color: #374151;
            border-radius: 999px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="roles-container">
        <h1>Manage Roles</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Create New Role Form -->
        <div class="role-form">
            <h2>Create New Role</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_role">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Role Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="url_limit">URL Limit</label>
                        <input type="number" id="url_limit" name="url_limit" value="10" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Role</button>
            </form>
        </div>
        
        <!-- Existing Roles -->
        <div class="roles-table">
            <table>
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>URL Limit</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                            <?php if (in_array($role['name'], ['Admin', 'Customer'])): ?>
                                <span style="color: #9CA3AF; font-size: 0.875rem;">(System)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                        <td>
                            <span class="url-limit-badge">
                                <?php echo $role['url_limit'] >= 999999 ? 'Unlimited' : $role['url_limit'] . ' URLs'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="user-count-badge">
                                <?php echo $role['user_count']; ?> users
                            </span>
                        </td>
                        <td>
                            <div class="role-actions">
                                <button class="btn-edit" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                    Edit
                                </button>
                                <?php if (!in_array($role['name'], ['Admin', 'Customer'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                        <button type="submit" class="btn-delete">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-delete system-role" disabled>Delete</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Edit Role Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: relative; background: white; margin: 5% auto; padding: 2rem; width: 90%; max-width: 600px; border-radius: 0.5rem;">
            <h2>Edit Role</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_name">Role Name</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_url_limit">URL Limit</label>
                        <input type="number" id="edit_url_limit" name="url_limit" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Update Role</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function editRole(role) {
            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_name').value = role.name;
            document.getElementById('edit_url_limit').value = role.url_limit;
            document.getElementById('edit_description').value = role.description || '';
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>