<?php
/**
 * User Roles Management Functions
 * Handles role checking, project limit validation, and role-based permissions
 */

require_once dirname(__DIR__) . '/db.php';

/**
 * Get user role by user ID
 */
function getUserRole($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT r.name, r.id 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Check if user is admin
 */
function isAdmin($userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return false;
    
    $role = getUserRole($userId);
    return $role && $role['name'] === 'Admin';
}

/**
 * Check if user is regular user
 */
function isUser($userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return false;
    
    $role = getUserRole($userId);
    return $role && $role['name'] === 'User';
}

/**
 * Check if user is customer (deprecated - use isUser instead)
 */
function isCustomer($userId = null) {
    return isUser($userId);
}

/**
 * Get project limit for a user
 */
function getUserProjectLimit($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(pl.max_projects, 10) as project_limit
        FROM users u
        LEFT JOIN project_limits pl ON u.id = pl.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ? $result['project_limit'] : 10; // Default to 10 if not found
}

/**
 * Get URL limit for a user (deprecated - use getUserProjectLimit)
 */
function getUserUrlLimit($userId) {
    return getUserProjectLimit($userId);
}

/**
 * Get current project count for a user
 */
function getUserProjectCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id) as project_count
        FROM projects
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ? $result['project_count'] : 0;
}

/**
 * Get current URL count for a user (deprecated - use getUserProjectCount)
 */
function getUserUrlCount($userId) {
    return getUserProjectCount($userId);
}

/**
 * Check if user can add more projects
 */
function canAddMoreProjects($userId) {
    // Admins have unlimited projects
    if (isAdmin($userId)) {
        return true;
    }
    
    $currentCount = getUserProjectCount($userId);
    $limit = getUserProjectLimit($userId);
    
    return $currentCount < $limit;
}

/**
 * Check if user can add more URLs (deprecated - use canAddMoreProjects)
 */
function canAddMoreUrls($userId) {
    return canAddMoreProjects($userId);
}

/**
 * Get remaining project slots for user
 */
function getRemainingProjectSlots($userId) {
    if (isAdmin($userId)) {
        return PHP_INT_MAX; // Unlimited for admins
    }
    
    $currentCount = getUserProjectCount($userId);
    $limit = getUserProjectLimit($userId);
    
    return max(0, $limit - $currentCount);
}

/**
 * Get remaining URL slots for user (deprecated - use getRemainingProjectSlots)
 */
function getRemainingUrlSlots($userId) {
    return getRemainingProjectSlots($userId);
}

/**
 * Update user role
 */
function updateUserRole($userId, $roleId) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    return $stmt->execute([$roleId, $userId]);
}

/**
 * Set project limit for a user
 */
function setUserProjectLimit($userId, $limit, $adminId = null, $message = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO project_limits (user_id, max_projects, set_by_admin_id, limit_message) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            max_projects = VALUES(max_projects),
            set_by_admin_id = VALUES(set_by_admin_id),
            limit_message = VALUES(limit_message),
            updated_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$userId, $limit, $adminId, $message]);
}

/**
 * Set URL limit for a user (deprecated - use setUserProjectLimit)
 */
function setUserUrlLimit($userId, $limit) {
    return setUserProjectLimit($userId, $limit, $_SESSION['user_id'] ?? null);
}

/**
 * Get all roles
 */
function getAllRoles() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get all users with their roles and project counts
 */
function getUsersWithRoles($limit = 50, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.created_at,
            r.name as role_name,
            r.id as role_id,
            COUNT(DISTINCT p.id) as project_count,
            COALESCE(pl.max_projects, 10) as project_limit,
            pl.limit_message,
            pl.set_by_admin_id,
            admin.full_name as limit_set_by
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN projects p ON u.id = p.user_id
        LEFT JOIN project_limits pl ON u.id = pl.user_id
        LEFT JOIN users admin ON pl.set_by_admin_id = admin.id
        GROUP BY u.id, u.full_name, u.email, u.created_at, r.name, r.id, pl.max_projects, pl.limit_message, pl.set_by_admin_id, admin.full_name
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Get user projects accessible by admin
 */
function getAdminAccessibleProjects($adminId, $targetUserId = null) {
    global $pdo;
    
    // Verify admin status
    if (!isAdmin($adminId)) {
        return false;
    }
    
    $query = "
        SELECT 
            p.*,
            u.full_name as user_name,
            u.email as user_email
        FROM projects p
        JOIN users u ON p.user_id = u.id
    ";
    
    $params = [];
    if ($targetUserId !== null) {
        $query .= " WHERE p.user_id = ?";
        $params[] = $targetUserId;
    }
    
    $query .= " ORDER BY p.date_created DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Log admin access to a project
 */
function logAdminProjectAccess($adminId, $projectId) {
    global $pdo;
    
    if (!isAdmin($adminId)) {
        return false;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO admin_project_access_log (admin_id, project_id)
        VALUES (?, ?)
    ");
    
    return $stmt->execute([$adminId, $projectId]);
}

/**
 * Check if admin can access a project
 */
function canAdminAccessProject($adminId, $projectId) {
    // Admins can access all projects (read-only)
    return isAdmin($adminId);
}

/**
 * Require admin access
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

/**
 * Get project limit exceeded message
 */
function getProjectLimitExceededMessage() {
    return "You have exceeded your project limit. Please contact info@seorocket.lt";
}

/**
 * Get URL limit exceeded message (deprecated - use getProjectLimitExceededMessage)
 */
function getUrlLimitExceededMessage() {
    return getProjectLimitExceededMessage();
}

/**
 * Assign role to first user (make them admin)
 */
function assignFirstUserAsAdmin() {
    global $pdo;
    
    // Check if there are any users
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    
    if ($result['user_count'] == 1) {
        // Get the admin role ID
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Admin'");
        $stmt->execute();
        $adminRole = $stmt->fetch();
        
        if ($adminRole) {
            // Update the first user to be admin
            $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = (SELECT MIN(id) FROM (SELECT id FROM users) AS u)");
            $stmt->execute([$adminRole['id']]);
            return true;
        }
    }
    
    return false;
}

/**
 * Get project limit details for a user
 */
function getUserProjectLimitDetails($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            r.name as role_name,
            COUNT(DISTINCT p.id) as current_projects,
            COALESCE(pl.max_projects, 10) as max_projects,
            pl.limit_message,
            pl.updated_at as limit_updated,
            admin.full_name as limit_set_by
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN projects p ON u.id = p.user_id
        LEFT JOIN project_limits pl ON u.id = pl.user_id
        LEFT JOIN users admin ON pl.set_by_admin_id = admin.id
        WHERE u.id = ?
        GROUP BY u.id, u.full_name, u.email, r.name, pl.max_projects, pl.limit_message, pl.updated_at, admin.full_name
    ");
    
    $stmt->execute([$userId]);
    return $stmt->fetch();
}
?>