<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Get project ID
$projectId = (int) getGet('id');

if (!$projectId) {
    redirect('dashboard.php');
}

// Get project details
$project = $db->fetchOne(
    "SELECT * FROM " . DB_PREFIX . "projects WHERE id = ? AND user_id = ?",
    [$projectId, $auth->getUserId()]
);

if (!$project) {
    $_SESSION['error'] = 'Project not found or access denied.';
    redirect('dashboard.php');
}

$pageTitle = 'Edit Project: ' . htmlspecialchars($project['name']);
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
    $status = getPost('status', 'active');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Project name is required.';
    }
    
    // Check if name already exists for another project
    if ($name !== $project['name']) {
        $existingProject = $db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "projects WHERE user_id = ? AND name = ? AND id != ?",
            [$auth->getUserId(), $name, $projectId]
        );
        if ($existingProject) {
            $errors[] = 'A project with this name already exists.';
        }
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
        // Update project
        $result = $db->update(DB_PREFIX . 'projects', [
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
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$projectId]);
        
        if ($result) {
            $_SESSION['success'] = 'Project updated successfully!';
            redirect('project.php?id=' . $projectId);
        } else {
            $errors[] = 'Failed to update project. Please try again.';
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
                    <i class="fas fa-edit"></i> Edit Project
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
                                       value="<?php echo htmlspecialchars(getPost('name', $project['name'])); ?>" 
                                       placeholder="My Website" required>
                            </div>
                            
                            <div class="form-group">
                                <label>URL to Monitor <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="url" 
                                       value="<?php echo htmlspecialchars(getPost('url', $project['url'])); ?>" 
                                       placeholder="https://example.com" required>
                                <small class="form-text text-muted">Full URL including http:// or https://</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <option value="active" <?php echo getPost('status', $project['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="paused" <?php echo getPost('status', $project['status']) === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Check Interval</label>
                                <select class="form-control" name="check_interval">
                                    <option value="60" <?php echo getPost('check_interval', $project['check_interval']) == 60 ? 'selected' : ''; ?>>1 minute</option>
                                    <option value="300" <?php echo getPost('check_interval', $project['check_interval']) == 300 ? 'selected' : ''; ?>>5 minutes</option>
                                    <option value="600" <?php echo getPost('check_interval', $project['check_interval']) == 600 ? 'selected' : ''; ?>>10 minutes</option>
                                    <option value="1800" <?php echo getPost('check_interval', $project['check_interval']) == 1800 ? 'selected' : ''; ?>>30 minutes</option>
                                    <option value="3600" <?php echo getPost('check_interval', $project['check_interval']) == 3600 ? 'selected' : ''; ?>>1 hour</option>
                                </select>
                                <small class="form-text text-muted">How often to check the website</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Advanced Settings</h5>
                            
                            <div class="form-group">
                                <label>Request Method</label>
                                <select class="form-control" name="method">
                                    <option value="GET" <?php echo getPost('method', $project['method']) === 'GET' ? 'selected' : ''; ?>>GET</option>
                                    <option value="HEAD" <?php echo getPost('method', $project['method']) === 'HEAD' ? 'selected' : ''; ?>>HEAD</option>
                                    <option value="POST" <?php echo getPost('method', $project['method']) === 'POST' ? 'selected' : ''; ?>>POST</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Expected Status Code</label>
                                <input type="number" class="form-control" name="expected_status" 
                                       value="<?php echo getPost('expected_status', $project['expected_status']); ?>" 
                                       min="100" max="599">
                                <small class="form-text text-muted">Usually 200 for success</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Timeout (seconds)</label>
                                <input type="number" class="form-control" name="timeout" 
                                       value="<?php echo getPost('timeout', $project['timeout']); ?>" 
                                       min="1" max="60">
                            </div>
                            
                            <div class="form-group">
                                <label>Search String (Optional)</label>
                                <input type="text" class="form-control" name="search_string" 
                                       value="<?php echo htmlspecialchars(getPost('search_string', $project['search_string'])); ?>" 
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
                               value="<?php echo htmlspecialchars(getPost('notify_email', $project['notify_email'])); ?>" 
                               placeholder="alerts@example.com">
                        <small class="form-text text-muted">Leave empty to use your account email</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Send Notifications For:</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_down" 
                                   name="notify_down" value="1" 
                                   <?php echo getPost('notify_down', $project['notify_down']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_down">
                                Website goes down
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_up" 
                                   name="notify_up" value="1" 
                                   <?php echo getPost('notify_up', $project['notify_up']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_up">
                                Website comes back online
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_ssl" 
                                   name="notify_ssl" value="1" 
                                   <?php echo getPost('notify_ssl', $project['notify_ssl']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_ssl">
                                SSL certificate expiring soon
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_domain" 
                                   name="notify_domain" value="1" 
                                   <?php echo getPost('notify_domain', $project['notify_domain']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="notify_domain">
                                Domain expiring soon
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-right">
                        <a href="project.php?id=<?php echo $projectId; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>