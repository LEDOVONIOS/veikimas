<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Get user ID
$userId = (int) getGet('id', $auth->getUserId());
$isOwnProfile = $userId === $auth->getUserId();

// Get user details
$user = $db->fetchOne("SELECT * FROM " . DB_PREFIX . "users WHERE id = ?", [$userId]);

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    redirect('dashboard.php');
}

// Only admin can view other user profiles
if (!$isOwnProfile && !$auth->isAdmin()) {
    redirect('profile.php');
}

$pageTitle = $isOwnProfile ? 'My Profile' : 'User Profile: ' . htmlspecialchars($user['username']);
$errors = [];
$success = false;

// Handle form submission
if (isPost() && $isOwnProfile) {
    $name = sanitize(getPost('name'));
    $email = sanitize(getPost('email'));
    $currentPassword = getPost('current_password');
    $newPassword = getPost('new_password');
    $confirmPassword = getPost('confirm_password');
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Check if email is already taken by another user
    if ($email !== $user['email']) {
        $existingUser = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existingUser) {
            $errors[] = 'This email is already registered to another account.';
        }
    }
    
    // Handle password change
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $errors[] = 'Please enter your current password to change your password.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }
    }
    
    if (empty($errors)) {
        $updateData = [
            'name' => $name,
            'email' => $email,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Update password if provided
        if (!empty($newPassword)) {
            $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        $result = $db->update(DB_PREFIX . 'users', $updateData, 'id = ?', [$userId]);
        
        if ($result) {
            $success = true;
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM " . DB_PREFIX . "users WHERE id = ?", [$userId]);
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}

// Get user statistics
$stats = [
    'total_projects' => $db->fetchValue("SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE user_id = ?", [$userId]),
    'active_projects' => $db->fetchValue("SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE user_id = ? AND status = 'active'", [$userId]),
    'total_checks' => $db->fetchValue("SELECT COUNT(*) FROM " . DB_PREFIX . "checks WHERE project_id IN (SELECT id FROM " . DB_PREFIX . "projects WHERE user_id = ?)", [$userId]),
    'recent_logins' => $db->fetchValue("SELECT COUNT(*) FROM " . DB_PREFIX . "login_attempts WHERE user_id = ? AND success = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)", [$userId])
];

// Get recent projects
$recentProjects = $db->fetchAllArray(
    "SELECT * FROM " . DB_PREFIX . "projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

include 'templates/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-user"></i> <?php echo $pageTitle; ?>
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
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Profile updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-sm-3">
                            <div class="text-center">
                                <i class="fas fa-user-circle fa-5x text-muted"></i>
                                <h6 class="mt-2"><?php echo htmlspecialchars($user['username']); ?></h6>
                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-9">
                            <div class="row">
                                <div class="col-6 col-md-3 text-center">
                                    <h5 class="mb-0"><?php echo $stats['total_projects']; ?></h5>
                                    <small class="text-muted">Total Projects</small>
                                </div>
                                <div class="col-6 col-md-3 text-center">
                                    <h5 class="mb-0"><?php echo $stats['active_projects']; ?></h5>
                                    <small class="text-muted">Active Projects</small>
                                </div>
                                <div class="col-6 col-md-3 text-center">
                                    <h5 class="mb-0"><?php echo number_format($stats['total_checks']); ?></h5>
                                    <small class="text-muted">Total Checks</small>
                                </div>
                                <div class="col-6 col-md-3 text-center">
                                    <h5 class="mb-0"><?php echo $stats['recent_logins']; ?></h5>
                                    <small class="text-muted">Logins (30d)</small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-sm-4">
                                    <h6 class="mb-0">Member Since</h6>
                                </div>
                                <div class="col-sm-8 text-secondary">
                                    <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4">
                                    <h6 class="mb-0">Last Login</h6>
                                </div>
                                <div class="col-sm-8 text-secondary">
                                    <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?>
                                </div>
                            </div>
                            <?php if (!$isOwnProfile && $auth->isAdmin()): ?>
                                <hr>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h6 class="mb-0">Status</h6>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($isOwnProfile): ?>
                        <hr>
                        <h5>Edit Profile</h5>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="form-text text-muted">Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($user['name'] ?: ''); ?>" 
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="form-group">
                                <label>Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <hr>
                            <h6>Change Password</h6>
                            <p class="text-muted">Leave empty to keep current password</p>
                            
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" class="form-control" name="current_password">
                            </div>
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" class="form-control" name="new_password">
                                <small class="form-text text-muted">At least 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password">
                            </div>
                            
                            <hr>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (!empty($recentProjects)): ?>
                        <hr>
                        <h5>Recent Projects</h5>
                        <div class="list-group">
                            <?php foreach ($recentProjects as $project): ?>
                                <a href="project.php?id=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($project['name']); ?></h6>
                                        <small><?php echo timeAgo($project['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($project['url']); ?></p>
                                    <small>
                                        Status: 
                                        <span class="badge badge-<?php echo $project['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>