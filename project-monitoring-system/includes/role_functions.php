<?php
/**
 * Role Management Functions
 * Handles user roles, permissions, and URL limit checking
 */

/**
 * Get user role information
 */
function getUserRole($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.description as role_description,
                   COALESCE(rul.url_limit, u.url_limit, 10) as effective_url_limit
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN role_url_limits rul ON r.id = rul.role_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting user role: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user has specific permission
 */
function hasPermission($pdo, $userId, $permissionName) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as has_permission
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.name = ?
        ");
        $stmt->execute([$userId, $permissionName]);
        $result = $stmt->fetch();
        return $result['has_permission'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking permission: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is admin
 */
function isAdmin($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_admin
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.name = 'Admin'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['is_admin'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's current URL count
 */
function getUserUrlCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as url_count
            FROM projects
            WHERE user_id = ? AND status = 'active' AND project_url IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['url_count'];
    } catch (PDOException $e) {
        error_log("Error counting user URLs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user can add more URLs
 */
function canAddMoreUrls($pdo, $userId) {
    $userInfo = getUserRole($pdo, $userId);
    if (!$userInfo) {
        return false;
    }
    
    // Admins have unlimited URLs
    if ($userInfo['role_name'] === 'Admin') {
        return true;
    }
    
    $currentCount = getUserUrlCount($pdo, $userId);
    return $currentCount < $userInfo['effective_url_limit'];
}

/**
 * Get remaining URL slots for user
 */
function getRemainingUrlSlots($pdo, $userId) {
    $userInfo = getUserRole($pdo, $userId);
    if (!$userInfo) {
        return 0;
    }
    
    // Admins have unlimited URLs
    if ($userInfo['role_name'] === 'Admin') {
        return 999999;
    }
    
    $currentCount = getUserUrlCount($pdo, $userId);
    $remaining = $userInfo['effective_url_limit'] - $currentCount;
    return max(0, $remaining);
}

/**
 * Get all roles
 */
function getAllRoles($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT r.*, 
                   COALESCE(rul.url_limit, 10) as url_limit,
                   COUNT(DISTINCT u.id) as user_count
            FROM roles r
            LEFT JOIN role_url_limits rul ON r.id = rul.role_id
            LEFT JOIN users u ON r.id = u.role_id
            GROUP BY r.id
            ORDER BY r.name
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Create new role
 */
function createRole($pdo, $name, $description, $urlLimit = 10) {
    try {
        $pdo->beginTransaction();
        
        // Insert role
        $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $roleId = $pdo->lastInsertId();
        
        // Set URL limit
        $stmt = $pdo->prepare("INSERT INTO role_url_limits (role_id, url_limit) VALUES (?, ?)");
        $stmt->execute([$roleId, $urlLimit]);
        
        $pdo->commit();
        return $roleId;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error creating role: " . $e->getMessage());
        return false;
    }
}

/**
 * Update role
 */
function updateRole($pdo, $roleId, $name, $description, $urlLimit) {
    try {
        $pdo->beginTransaction();
        
        // Update role
        $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $roleId]);
        
        // Update URL limit
        $stmt = $pdo->prepare("
            INSERT INTO role_url_limits (role_id, url_limit) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE url_limit = VALUES(url_limit)
        ");
        $stmt->execute([$roleId, $urlLimit]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating role: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete role
 */
function deleteRole($pdo, $roleId) {
    try {
        // Don't delete Admin or Customer roles
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        
        if ($role && in_array($role['name'], ['Admin', 'Customer'])) {
            return false;
        }
        
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error deleting role: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign role to user
 */
function assignUserRole($pdo, $userId, $roleId) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        $stmt->execute([$roleId, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error assigning role: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user URL limit (individual override)
 */
function updateUserUrlLimit($pdo, $userId, $urlLimit) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET url_limit = ? WHERE id = ?");
        $stmt->execute([$urlLimit, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating user URL limit: " . $e->getMessage());
        return false;
    }
}

/**
 * Get role permissions
 */
function getRolePermissions($pdo, $roleId) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting role permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Update role permissions
 */
function updateRolePermissions($pdo, $roleId, $permissionIds) {
    try {
        $pdo->beginTransaction();
        
        // Remove existing permissions
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        // Add new permissions
        if (!empty($permissionIds)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permissionId) {
                $stmt->execute([$roleId, $permissionId]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating role permissions: " . $e->getMessage());
        return false;
    }
}