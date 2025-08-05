<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Check if user is admin
if (!$auth->isAdmin()) {
    redirect('dashboard.php');
}

$pageTitle = 'Add New User';
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
    $project_limit = (int)getPost('project_limit', 5);
    
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
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!in_array($role, ['user', 'admin'])) {
        $errors[] = 'Invalid role selected.';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Invalid status selected.';
    }
    
    if ($project_limit < 0 || $project_limit > 999) {
        $errors[] = 'Project limit must be between 0 and 999.';
    }
    
    if (empty($errors)) {
        // Check if username already exists
        $existingUser = $db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "users WHERE username = ?",
            [$username]
        );
        
        if ($existingUser) {
            $errors[] = 'Username already exists. Please choose a different username.';
        } else {
            // Check if email already exists
            $existingEmail = $db->fetchOne(
                "SELECT id FROM " . DB_PREFIX . "users WHERE email = ?",
                [$email]
            );
            
            if ($existingEmail) {
                $errors[] = 'Email already exists. Please use a different email address.';
            } else {
                // Insert user
                $userId = $db->insert(DB_PREFIX . 'users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => $status,
                    'project_limit' => $project_limit,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($userId) {
                    $_SESSION['success'] = 'User added successfully!';
                    redirect('users.php');
                } else {
                    $errors[] = 'Failed to add user. Please try again.';
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
                    <i class="fas fa-user-plus"></i> Add New User
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
                               value="<?php echo htmlspecialchars(getPost('username', '')); ?>" 
                               placeholder="johndoe" required>
                        <small class="form-text text-muted">Minimum 3 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars(getPost('email', '')); ?>" 
                               placeholder="john@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" 
                               placeholder="Enter password" required>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="Confirm password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" name="role">
                            <option value="user" <?php echo getPost('role', 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo getPost('role') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="active" <?php echo getPost('status', 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo getPost('status') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Project Limit</label>
                        <input type="number" class="form-control" name="project_limit" 
                               value="<?php echo getPost('project_limit', 5); ?>"
                               min="0" max="999" placeholder="Number of projects allowed">
                        <small class="form-text text-muted">Maximum number of projects this user can create (0 = unlimited)</small>
                    </div>
                    
                    <hr>
                    
                    <div class="text-right">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>