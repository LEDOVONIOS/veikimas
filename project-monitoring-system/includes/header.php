<?php
// Get user role information if logged in
$userRole = null;
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/role_functions.php';
    $userRole = getUserRole($pdo, $_SESSION['user_id']);
    $isAdmin = isAdmin($pdo, $_SESSION['user_id']);
}
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="dashboard.php">Project Monitor</a>
        </div>
        <div class="nav-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="add_project.php" class="nav-link">Add Project</a>
                <?php if ($isAdmin): ?>
                    <a href="manage_roles.php" class="nav-link">Manage Roles</a>
                    <a href="manage_users.php" class="nav-link">Manage Users</a>
                <?php endif; ?>
                <a href="notifications.php" class="nav-link">
                    Notifications
                    <?php if (isset($unreadNotifications) && $unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <div class="nav-user">
                    <span class="user-info">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                        <?php if ($userRole): ?>
                            <span class="role-badge"><?php echo htmlspecialchars($userRole['role_name']); ?></span>
                        <?php endif; ?>
                    </span>
                    <a href="logout.php" class="nav-link">Logout</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="nav-link">Login</a>
                <a href="register.php" class="nav-link">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
.navbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem 0;
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand a {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    text-decoration: none;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.nav-link {
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s;
    position: relative;
}

.nav-link:hover {
    color: var(--text-primary);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #EF4444;
    color: white;
    font-size: 0.75rem;
    padding: 0.125rem 0.375rem;
    border-radius: 999px;
    font-weight: 600;
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-info {
    color: var(--text-primary);
    font-weight: 500;
}

.role-badge {
    display: inline-block;
    margin-left: 0.5rem;
    padding: 0.125rem 0.5rem;
    background: #E0E7FF;
    color: #4338CA;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>