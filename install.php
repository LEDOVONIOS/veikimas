<?php
/**
 * Installation wizard
 */

session_start();

// Check if already installed
if (file_exists('config/config.php')) {
    die('System is already installed. Please delete config/config.php to reinstall.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Test database connection
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        $db_prefix = $_POST['db_prefix'] ?: 'monitor_';
        
        $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($conn->connect_error) {
            $errors[] = "Database connection failed: " . $conn->connect_error;
        } else {
            // Save to session and proceed
            $_SESSION['install'] = [
                'db_host' => $db_host,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_pass' => $db_pass,
                'db_prefix' => $db_prefix
            ];
            $conn->close();
            header('Location: install.php?step=2');
            exit;
        }
    } elseif ($step === 2) {
        // Site configuration
        $_SESSION['install']['site_url'] = rtrim($_POST['site_url'], '/');
        $_SESSION['install']['site_name'] = $_POST['site_name'];
        $_SESSION['install']['timezone'] = $_POST['timezone'];
        $_SESSION['install']['mail_from'] = $_POST['mail_from'];
        $_SESSION['install']['mail_from_name'] = $_POST['mail_from_name'];
        header('Location: install.php?step=3');
        exit;
    } elseif ($step === 3) {
        // Admin account
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        if (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if ($password !== $password_confirm) {
            $errors[] = "Passwords do not match.";
        }
        
        if (empty($errors)) {
            $_SESSION['install']['admin_username'] = $username;
            $_SESSION['install']['admin_email'] = $email;
            $_SESSION['install']['admin_password'] = $password;
            header('Location: install.php?step=4');
            exit;
        }
    } elseif ($step === 4) {
        // Perform installation
        $config = $_SESSION['install'];
        
        // Create database connection
        $conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
        if ($conn->connect_error) {
            $errors[] = "Database connection failed: " . $conn->connect_error;
        } else {
            // Read and execute SQL schema
            $sql = file_get_contents('database/schema.sql');
            $sql = str_replace('{PREFIX}', $config['db_prefix'], $sql);
            
            // Split by semicolon and execute each query
            $queries = array_filter(explode(';', $sql));
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    if (!$conn->query($query)) {
                        $errors[] = "SQL Error: " . $conn->error;
                        break;
                    }
                }
            }
            
            if (empty($errors)) {
                // Insert admin user
                $hashedPassword = password_hash($config['admin_password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE " . $config['db_prefix'] . "users SET username = ?, email = ?, password = ? WHERE id = 1");
                $stmt->bind_param("sss", $config['admin_username'], $config['admin_email'], $hashedPassword);
                
                if ($stmt->execute()) {
                    // Generate random keys
                    $cron_key = bin2hex(random_bytes(16));
                    $salt = bin2hex(random_bytes(32));
                    
                    // Create config file
                    $configContent = file_get_contents('config/config.sample.php');
                    $replacements = [
                        'localhost' => $config['db_host'],
                        'your_database_name' => $config['db_name'],
                        'your_database_user' => $config['db_user'],
                        'your_database_password' => $config['db_pass'],
                        'monitor_' => $config['db_prefix'],
                        'https://yourdomain.com' => $config['site_url'],
                        'Website Monitor' => $config['site_name'],
                        'Europe/Vilnius' => $config['timezone'],
                        'noreply@yourdomain.com' => $config['mail_from'],
                        'generate_random_key_here' => $cron_key,
                        'generate_random_salt_here' => $salt
                    ];
                    
                    foreach ($replacements as $search => $replace) {
                        $configContent = str_replace($search, $replace, $configContent);
                    }
                    
                    // Update mail from name
                    $configContent = str_replace(
                        "define('MAIL_FROM_NAME', 'Website Monitor');",
                        "define('MAIL_FROM_NAME', '" . addslashes($config['mail_from_name']) . "');",
                        $configContent
                    );
                    
                    // Write config file
                    if (file_put_contents('config/config.php', $configContent)) {
                        $success = true;
                        unset($_SESSION['install']);
                    } else {
                        $errors[] = "Failed to create config file. Please check permissions.";
                    }
                } else {
                    $errors[] = "Failed to create admin user: " . $stmt->error;
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}

// Get timezones
$timezones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Website Monitor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
        }
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step.completed::after {
            background: #28a745;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .step-title {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1 class="text-center mb-4">
            <i class="fas fa-chart-line text-primary"></i> Website Monitor Installation
        </h1>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-title">Database</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-title">Configuration</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-title">Admin Account</div>
            </div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-title">Complete</div>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <!-- Step 1: Database Configuration -->
            <h3>Database Configuration</h3>
            <p class="text-muted">Enter your MySQL/MariaDB database details.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                    <small class="form-text text-muted">Usually 'localhost'</small>
                </div>
                
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" class="form-control" name="db_name" required>
                </div>
                
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" class="form-control" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" class="form-control" name="db_pass">
                </div>
                
                <div class="form-group">
                    <label>Table Prefix</label>
                    <input type="text" class="form-control" name="db_prefix" value="monitor_">
                    <small class="form-text text-muted">Useful if you're sharing the database</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
        <?php elseif ($step === 2): ?>
            <!-- Step 2: Site Configuration -->
            <h3>Site Configuration</h3>
            <p class="text-muted">Configure your monitoring system settings.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Site URL</label>
                    <input type="url" class="form-control" name="site_url" 
                           value="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>" 
                           required>
                    <small class="form-text text-muted">Full URL to your installation (without trailing slash)</small>
                </div>
                
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" class="form-control" name="site_name" value="Website Monitor" required>
                </div>
                
                <div class="form-group">
                    <label>Timezone</label>
                    <select class="form-control" name="timezone" required>
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?php echo $tz; ?>" <?php echo $tz === 'Europe/Vilnius' ? 'selected' : ''; ?>>
                                <?php echo $tz; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Email From Address</label>
                    <input type="email" class="form-control" name="mail_from" placeholder="noreply@yourdomain.com" required>
                    <small class="form-text text-muted">Email address for system notifications</small>
                </div>
                
                <div class="form-group">
                    <label>Email From Name</label>
                    <input type="text" class="form-control" name="mail_from_name" value="Website Monitor" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
        <?php elseif ($step === 3): ?>
            <!-- Step 3: Admin Account -->
            <h3>Admin Account</h3>
            <p class="text-muted">Create your administrator account.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="username" required minlength="3">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" class="form-control" name="password" required minlength="6">
                    <small class="form-text text-muted">At least 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" class="form-control" name="password_confirm" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Install <i class="fas fa-check"></i>
                </button>
            </form>
            
        <?php elseif ($step === 4): ?>
            <!-- Step 4: Installation Complete -->
            <?php if ($success): ?>
                <div class="text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h3 class="mt-3">Installation Complete!</h3>
                    <p class="text-muted">Your monitoring system has been successfully installed.</p>
                    
                    <div class="alert alert-info mt-4">
                        <h5>Important Information:</h5>
                        <p class="mb-2"><strong>Cron URL:</strong><br>
                        <code><?php echo $_SESSION['install']['site_url']; ?>/cron.php?key=<?php echo $cron_key ?? 'CHECK_CONFIG_FILE'; ?></code></p>
                        <p>Add this to your cron jobs to run every 5 minutes.</p>
                    </div>
                    
                    <div class="mt-4">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Security Notice:</strong> Please delete the install.php file for security reasons.
                    </div>
                </div>
            <?php else: ?>
                <form method="POST">
                    <p>Click the button below to complete the installation.</p>
                    <button type="submit" class="btn btn-primary">
                        Complete Installation <i class="fas fa-check"></i>
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>