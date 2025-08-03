<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Check if user can create projects
if (!$auth->canCreateProject()) {
    redirect('dashboard.php');
}

$pageTitle = 'Add New Project';
$errors = [];
$success = false;

// Handle form submission
if (isPost()) {
    $name = sanitize(getPost('name'));
    $url = getPost('url');
    $check_interval = (int) getPost('check_interval', 300);
    $timeout = (int) getPost('timeout', 30);
    $method = getPost('method', 'GET');
    $expected_status = (int) getPost('expected_status', 200);
    $search_string = sanitize(getPost('search_string'));
    $notify_email = sanitize(getPost('notify_email'));
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Project name is required.';
    }
    
    if (empty($url)) {
        $errors[] = 'URL is required.';
    } elseif (!validateUrl($url)) {
        $errors[] = 'Please enter a valid URL.';
    }
    
    if ($check_interval < 60) {
        $errors[] = 'Check interval must be at least 60 seconds.';
    }
    
    if ($timeout < 1 || $timeout > 60) {
        $errors[] = 'Timeout must be between 1 and 60 seconds.';
    }
    
    if (!empty($notify_email) && !validateEmail($notify_email)) {
        $errors[] = 'Please enter a valid notification email.';
    }
    
    if (empty($errors)) {
        // Insert project
        $projectId = $db->insert(DB_PREFIX . 'projects', [
            'user_id' => $auth->getUserId(),
            'name' => $name,
            'url' => $url,
            'check_interval' => $check_interval,
            'timeout' => $timeout,
            'method' => $method,
            'expected_status' => $expected_status,
            'search_string' => $search_string,
            'notify_email' => $notify_email,
            'notify_down' => getPost('notify_down') ? 1 : 0,
            'notify_up' => getPost('notify_up') ? 1 : 0,
            'notify_ssl' => getPost('notify_ssl') ? 1 : 0,
            'notify_domain' => getPost('notify_domain') ? 1 : 0,
            'status' => 'active'
        ]);
        
        if ($projectId) {
            $_SESSION['success'] = 'Project added successfully!';
            redirect('project.php?id=' . $projectId);
        } else {
            $errors[] = 'Failed to add project. Please try again.';
        }
    }
}

include 'templates/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus"></i> Add New Project
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
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            
                            <div class="form-group">
                                <label>Project Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars(getPost('name', '')); ?>" 
                                       placeholder="My Website" required>
                            </div>
                            
                            <div class="form-group">
                                <label>URL to Monitor <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="url" 
                                       value="<?php echo htmlspecialchars(getPost('url', '')); ?>" 
                                       placeholder="https://example.com" required>
                                <small class="form-text text-muted">Full URL including http:// or https://</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Check Interval</label>
                                <select class="form-control" name="check_interval">
                                    <option value="60" <?php echo getPost('check_interval') == 60 ? 'selected' : ''; ?>>1 minute</option>
                                    <option value="300" <?php echo getPost('check_interval', 300) == 300 ? 'selected' : ''; ?>>5 minutes</option>
                                    <option value="600" <?php echo getPost('check_interval') == 600 ? 'selected' : ''; ?>>10 minutes</option>
                                    <option value="1800" <?php echo getPost('check_interval') == 1800 ? 'selected' : ''; ?>>30 minutes</option>
                                    <option value="3600" <?php echo getPost('check_interval') == 3600 ? 'selected' : ''; ?>>1 hour</option>
                                </select>
                                <small class="form-text text-muted">How often to check the website</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Advanced Settings</h5>
                            
                            <div class="form-group">
                                <label>Request Method</label>
                                <select class="form-control" name="method">
                                    <option value="GET" <?php echo getPost('method', 'GET') == 'GET' ? 'selected' : ''; ?>>GET</option>
                                    <option value="HEAD" <?php echo getPost('method') == 'HEAD' ? 'selected' : ''; ?>>HEAD</option>
                                    <option value="POST" <?php echo getPost('method') == 'POST' ? 'selected' : ''; ?>>POST</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Expected Status Code</label>
                                <input type="number" class="form-control" name="expected_status" 
                                       value="<?php echo getPost('expected_status', 200); ?>" 
                                       min="100" max="599">
                                <small class="form-text text-muted">Usually 200 for success</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Timeout (seconds)</label>
                                <input type="number" class="form-control" name="timeout" 
                                       value="<?php echo getPost('timeout', 30); ?>" 
                                       min="1" max="60">
                            </div>
                            
                            <div class="form-group">
                                <label>Search String (Optional)</label>
                                <input type="text" class="form-control" name="search_string" 
                                       value="<?php echo htmlspecialchars(getPost('search_string', '')); ?>" 
                                       placeholder="Text to search for in response">
                                <small class="form-text text-muted">Check will fail if this text is not found</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5>Notification Settings</h5>
                    
                    <div class="form-group">
                        <label>Notification Email (Optional)</label>
                        <input type="email" class="form-control" name="notify_email" 
                               value="<?php echo htmlspecialchars(getPost('notify_email', '')); ?>" 
                               placeholder="alerts@example.com">
                        <small class="form-text text-muted">Leave empty to use your account email</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Send Notifications For:</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_down" 
                                   name="notify_down" value="1" 
                                   <?php echo getPost('notify_down', 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_down">
                                Website goes down
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_up" 
                                   name="notify_up" value="1" 
                                   <?php echo getPost('notify_up', 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_up">
                                Website comes back online
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_ssl" 
                                   name="notify_ssl" value="1" 
                                   <?php echo getPost('notify_ssl', 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_ssl">
                                SSL certificate expiring soon
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_domain" 
                                   name="notify_domain" value="1" 
                                   <?php echo getPost('notify_domain', 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_domain">
                                Domain expiring soon
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-right">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>