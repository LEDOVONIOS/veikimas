<?php
/**
 * User Roles Management Functions
 * Handles role checking, URL limit validation, and role-based permissions
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
 * Check if user is customer
 */
function isCustomer($userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return false;
    
    $role = getUserRole($userId);
    return $role && $role['name'] === 'Customer';
}

/**
 * Get URL limit for a user
 */
function getUserUrlLimit($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(ul.max_urls, 10) as url_limit
        FROM users u
        LEFT JOIN url_limits ul ON u.id = ul.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ? $result['url_limit'] : 10; // Default to 10 if not found
}

/**
 * Get current URL count for a user
 */
function getUserUrlCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT project_url) as url_count
        FROM projects
        WHERE user_id = ? 
        AND project_url IS NOT NULL 
        AND project_url != ''
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ? $result['url_count'] : 0;
}

/**
 * Check if user can add more URLs
 */
function canAddMoreUrls($userId) {
    // Admins have unlimited URLs
    if (isAdmin($userId)) {
        return true;
    }
    
    $currentCount = getUserUrlCount($userId);
    $limit = getUserUrlLimit($userId);
    
    return $currentCount < $limit;
}

/**
 * Get remaining URL slots for user
 */
function getRemainingUrlSlots($userId) {
    if (isAdmin($userId)) {
        return PHP_INT_MAX; // Unlimited for admins
    }
    
    $currentCount = getUserUrlCount($userId);
    $limit = getUserUrlLimit($userId);
    
    return max(0, $limit - $currentCount);
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
 * Set URL limit for a user
 */
function setUserUrlLimit($userId, $limit) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO url_limits (user_id, max_urls) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE max_urls = VALUES(max_urls)
    ");
    return $stmt->execute([$userId, $limit]);
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
 * Get all users with their roles and URL counts
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
            COUNT(DISTINCT p.project_url) as url_count,
            COALESCE(ul.max_urls, 10) as url_limit
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN projects p ON u.id = p.user_id AND p.project_url IS NOT NULL AND p.project_url != ''
        LEFT JOIN url_limits ul ON u.id = ul.user_id
        GROUP BY u.id, u.full_name, u.email, u.created_at, r.name, r.id, ul.max_urls
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
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
 * Get URL limit exceeded message
 */
function getUrlLimitExceededMessage() {
    return "You have exceeded the allowed number of URLs. Please contact support at info@seorocket.lt.";
}
?>