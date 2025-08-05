<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Check if user is admin
if (!$auth->isAdmin()) {
    redirect('dashboard.php');
}

$userId = (int) getGet('id');
if (!$userId) {
    redirect('users.php');
}

// Get user details
$user = $db->fetchOne(
    "SELECT * FROM " . DB_PREFIX . "users WHERE id = ?",
    [$userId]
);

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    redirect('users.php');
}

// Prevent editing own account through this page
if ($userId === $auth->getUserId()) {
    redirect('profile.php');
}

$pageTitle = 'Edit User';
$errors = [];
$success = false;

// Handle form submission
if (isPost()) {
    $username = sanitize(getPost('username'));
    $email = sanitize(getPost('email'));
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    $role = getPost('role', 'user');
    $status = getPost('status', 'active');
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Password is optional when editing
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
    }
    
    if (!in_array($role, ['user', 'admin'])) {
        $errors[] = 'Invalid role selected.';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Invalid status selected.';
    }
    
    if (empty($errors)) {
        // Check if username already exists (excluding current user)
        $existingUser = $db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "users WHERE username = ? AND id != ?",
            [$username, $userId]
        );
        
        if ($existingUser) {
            $errors[] = 'Username already exists. Please choose a different username.';
        } else {
            // Check if email already exists (excluding current user)
            $existingEmail = $db->fetchOne(
                "SELECT id FROM " . DB_PREFIX . "users WHERE email = ? AND id != ?",
                [$email, $userId]
            );
            
            if ($existingEmail) {
                $errors[] = 'Email already exists. Please use a different email address.';
            } else {
                // Update user
                $updateData = [
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Only update password if provided
                if (!empty($password)) {
                    $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $updated = $db->update(
                    DB_PREFIX . 'users',
                    $updateData,
                    'id = ?',
                    [$userId]
                );
                
                if ($updated !== false) {
                    $_SESSION['success'] = 'User updated successfully!';
                    redirect('users.php');
                } else {
                    $errors[] = 'Failed to update user. Please try again.';
                }
            }
        }
    }
}

include 'templates/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user-edit"></i> Edit User
                </h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" 
                               value="<?php echo htmlspecialchars(getPost('username', $user['username'])); ?>" 
                               placeholder="johndoe" required>
                        <small class="form-text text-muted">Minimum 3 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars(getPost('email', $user['email'])); ?>" 
                               placeholder="john@example.com" required>
                    </div>
                    
                    <hr>
                    
                    <h5>Change Password</h5>
                    <p class="text-muted">Leave blank to keep current password</p>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" class="form-control" name="password" 
                               placeholder="Enter new password">
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="Confirm new password">
                    </div>
                    
                    <hr>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" name="role">
                            <option value="user" <?php echo getPost('role', $user['role']) === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo getPost('role', $user['role']) === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="active" <?php echo getPost('status', $user['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo getPost('status', $user['status']) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <hr>
                    
                    <div class="text-right">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>