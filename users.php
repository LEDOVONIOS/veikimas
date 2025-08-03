<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Check if user is admin
if (!$auth->isAdmin()) {
    redirect('dashboard.php');
}

$pageTitle = 'User Management';
$search = getGet('search');
$page = (int) getGet('page', 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total users count
$whereClause = '';
$whereParams = [];

if ($search) {
    $whereClause = " WHERE (username LIKE ? OR email LIKE ?)";
    $searchParam = '%' . $search . '%';
    $whereParams = [$searchParam, $searchParam];
}

$totalUsers = $db->fetchValue("SELECT COUNT(*) FROM " . DB_PREFIX . "users" . $whereClause, $whereParams);
$totalPages = ceil($totalUsers / $perPage);

// Get users
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE user_id = u.id) as project_count
        FROM " . DB_PREFIX . "users u" . $whereClause . "
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";

$params = array_merge($whereParams, [$perPage, $offset]);
$users = $db->fetchAllArray($sql, $params);

include 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-users"></i> User Management
                    </h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by username or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($search): ?>
                                    <a href="users.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Projects</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <a href="profile.php?id=<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['project_count']; ?></td>
                                        <td>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['last_login']): ?>
                                                <?php echo timeAgo($user['last_login']); ?>
                                            <?php else: ?>
                                                Never
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="profile.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($user['id'] !== $auth->getUserId()): ?>
                                                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <a href="user-action.php?id=<?php echo $user['id']; ?>&action=deactivate" 
                                                           class="btn btn-outline-warning" title="Deactivate"
                                                           onclick="return confirm('Are you sure you want to deactivate this user?');">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="user-action.php?id=<?php echo $user['id']; ?>&action=activate" 
                                                           class="btn btn-outline-success" title="Activate">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No users found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>