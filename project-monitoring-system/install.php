<?php
session_start();

// Check if already installed
if (file_exists('config.php')) {
    require_once 'config.php';
    if (defined('INSTALLED') && INSTALLED === true) {
        die('<div style="text-align:center; margin-top:50px;">
            <h2>System Already Installed</h2>
            <p>The Project Monitoring System is already installed.</p>
            <p>To reinstall, please delete the config.php file and try again.</p>
            <p><a href="index.php">Go to Login</a></p>
        </div>');
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Test database connection
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        
        try {
            // First try to connect without database name
            $pdo = new PDO(
                "mysql:host=$db_host;charset=utf8mb4",
                $db_user,
                $db_pass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the specific database
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
            // Store database info in session
            $_SESSION['install_db_host'] = $db_host;
            $_SESSION['install_db_name'] = $db_name;
            $_SESSION['install_db_user'] = $db_user;
            $_SESSION['install_db_pass'] = $db_pass;
            
            header('Location: install.php?step=2');
            exit();
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step === 2) {
        // Import database schema
        try {
            $pdo = new PDO(
                "mysql:host={$_SESSION['install_db_host']};dbname={$_SESSION['install_db_name']};charset=utf8mb4",
                $_SESSION['install_db_user'],
                $_SESSION['install_db_pass'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
            // Read and execute the SQL file
            $sql = file_get_contents('db_complete.sql');
            
            // Split by semicolon but ignore semicolons within quotes
            $queries = preg_split('/;(?=([^\']*\'[^\']*\')*[^\']*$)/', $sql);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            
            $_SESSION['install_db_imported'] = true;
            header('Location: install.php?step=3');
            exit();
        } catch (Exception $e) {
            $error = 'Database import failed: ' . $e->getMessage();
        }
    } elseif ($step === 3) {
        // Create admin account
        $admin_name = $_POST['admin_name'];
        $admin_email = $_POST['admin_email'];
        $admin_password = $_POST['admin_password'];
        $admin_password_confirm = $_POST['admin_password_confirm'];
        $site_url = $_POST['site_url'];
        
        // Validation
        if (empty($admin_name) || empty($admin_email) || empty($admin_password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($admin_password !== $admin_password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host={$_SESSION['install_db_host']};dbname={$_SESSION['install_db_name']};charset=utf8mb4",
                    $_SESSION['install_db_user'],
                    $_SESSION['install_db_pass'],
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                );
                
                // Hash the password
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                
                // Get admin role ID
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Admin'");
                $stmt->execute();
                $admin_role = $stmt->fetch();
                $admin_role_id = $admin_role ? $admin_role['id'] : 1;
                
                // Insert admin user
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$admin_name, $admin_email, $password_hash, $admin_role_id]);
                
                // Create config.php
                $config_content = '<?php
/**
 * Project Monitoring System Configuration
 * Auto-generated during installation
 */

// Database Configuration
define(\'DB_HOST\', \'' . $_SESSION['install_db_host'] . '\');
define(\'DB_NAME\', \'' . $_SESSION['install_db_name'] . '\');
define(\'DB_USER\', \'' . $_SESSION['install_db_user'] . '\');
define(\'DB_PASS\', \'' . $_SESSION['install_db_pass'] . '\');

// System Configuration
define(\'INSTALLED\', true);
define(\'SITE_NAME\', \'Project Monitoring System\');
define(\'SITE_URL\', \'' . $site_url . '\');

// Email Configuration (update these later)
define(\'SMTP_HOST\', \'smtp.gmail.com\');
define(\'SMTP_PORT\', 587);
define(\'SMTP_USER\', \'\');
define(\'SMTP_PASS\', \'\');
define(\'SMTP_FROM_EMAIL\', \'noreply@' . parse_url($site_url, PHP_URL_HOST) . '\');
define(\'SMTP_FROM_NAME\', \'Project Monitor\');

// Security Configuration
define(\'SESSION_LIFETIME\', 3600);
define(\'PASSWORD_MIN_LENGTH\', 8);

// Monitoring Configuration
define(\'CHECK_INTERVAL\', 300);
define(\'TIMEOUT_SECONDS\', 10);
define(\'MAX_REDIRECTS\', 3);

// Development Mode
define(\'DEBUG_MODE\', false);
define(\'ERROR_REPORTING\', E_ALL);

// Timezone
date_default_timezone_set(\'UTC\');

?>';
                
                file_put_contents('config.php', $config_content);
                
                // Clear installation session data
                unset($_SESSION['install_db_host']);
                unset($_SESSION['install_db_name']);
                unset($_SESSION['install_db_user']);
                unset($_SESSION['install_db_pass']);
                unset($_SESSION['install_db_imported']);
                
                $_SESSION['install_complete'] = true;
                header('Location: install.php?step=4');
                exit();
            } catch (Exception $e) {
                $error = 'Failed to create admin account: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Project Monitoring System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        
        .progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 0 20px;
        }
        
        .progress-step {
            text-align: center;
            position: relative;
            flex: 1;
        }
        
        .progress-step::before {
            content: attr(data-step);
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            line-height: 30px;
            margin-bottom: 5px;
        }
        
        .progress-step.active::before {
            background: #3498db;
        }
        
        .progress-step.completed::before {
            background: #27ae60;
            content: '✓';
        }
        
        .progress-step span {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="url"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .success-box {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 60px;
            color: #27ae60;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            margin-bottom: 10px;
            color: #1976d2;
        }
        
        .code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .requirements {
            margin-bottom: 20px;
        }
        
        .requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .requirements li {
            padding: 5px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .requirements li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Project Monitoring System</h1>
        <p class="subtitle">Installation Wizard</p>
        
        <div class="progress">
            <div class="progress-step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>" data-step="1">
                <span>Database</span>
            </div>
            <div class="progress-step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>" data-step="2">
                <span>Import Schema</span>
            </div>
            <div class="progress-step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>" data-step="3">
                <span>Admin Account</span>
            </div>
            <div class="progress-step <?php echo $step >= 4 ? 'completed' : ''; ?>" data-step="4">
                <span>Complete</span>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <h2>Database Configuration</h2>
            <p style="margin-bottom: 20px;">Enter your MySQL database credentials below.</p>
            
            <div class="info-box">
                <h3>Requirements:</h3>
                <div class="requirements">
                    <ul>
                        <li>MySQL 5.7 or higher</li>
                        <li>PHP 7.4 or higher</li>
                        <li>PDO MySQL extension enabled</li>
                    </ul>
                </div>
            </div>
            
            <form method="POST" action="install.php?step=1">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                    <p class="help-text">Usually 'localhost' for most hosting providers</p>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" required>
                    <p class="help-text">The installer will create this database if it doesn't exist</p>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass">
                    <p class="help-text">Leave empty if no password is set</p>
                </div>
                
                <button type="submit" class="btn">Test Connection & Continue</button>
            </form>
            
        <?php elseif ($step === 2): ?>
            <h2>Import Database Schema</h2>
            <p style="margin-bottom: 20px;">The installer will now import the database schema.</p>
            
            <div class="info-box">
                <h3>Tables to be created:</h3>
                <ul style="margin-top: 10px;">
                    <li>• Users & Roles</li>
                    <li>• Projects & Project Limits</li>
                    <li>• Incidents & Monitoring Logs</li>
                    <li>• Notifications & Settings</li>
                    <li>• SSL Certificates & Response Times</li>
                </ul>
            </div>
            
            <form method="POST" action="install.php?step=2">
                <button type="submit" class="btn">Import Database Schema</button>
            </form>
            
        <?php elseif ($step === 3): ?>
            <h2>Create Administrator Account</h2>
            <p style="margin-bottom: 20px;">Create your first administrator account.</p>
            
            <form method="POST" action="install.php?step=3">
                <div class="form-group">
                    <label for="admin_name">Full Name</label>
                    <input type="text" id="admin_name" name="admin_name" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email Address</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                    <p class="help-text">You'll use this email to login</p>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input type="password" id="admin_password" name="admin_password" required minlength="8">
                    <p class="help-text">Minimum 8 characters</p>
                </div>
                
                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                </div>
                
                <div class="form-group">
                    <label for="site_url">Site URL</label>
                    <input type="url" id="site_url" name="site_url" value="<?php 
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $path = dirname($_SERVER['REQUEST_URI']);
                        echo $protocol . '://' . $host . $path;
                    ?>" required>
                    <p class="help-text">Full URL to your monitoring system</p>
                </div>
                
                <button type="submit" class="btn">Create Admin Account</button>
            </form>
            
        <?php elseif ($step === 4): ?>
            <div class="success-box">
                <div class="success-icon">✓</div>
                <h2>Installation Complete!</h2>
                <p style="margin-bottom: 30px;">Your Project Monitoring System has been successfully installed.</p>
                
                <div class="info-box" style="text-align: left;">
                    <h3>Next Steps:</h3>
                    <ol style="margin-top: 10px; padding-left: 20px;">
                        <li>Delete the <code>install.php</code> file for security</li>
                        <li>Set up cron job for monitoring (see documentation)</li>
                        <li>Configure email settings in <code>config.php</code></li>
                        <li>Start adding your projects to monitor</li>
                    </ol>
                </div>
                
                <div class="info-box" style="text-align: left; margin-top: 20px;">
                    <h3>Cron Job Setup:</h3>
                    <p style="margin-top: 10px;">Add this to your crontab to run monitoring every 5 minutes:</p>
                    <p style="margin-top: 10px; background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 14px;">
                        */5 * * * * /usr/bin/php <?php echo dirname(__FILE__); ?>/scripts/monitor_projects.php
                    </p>
                </div>
                
                <a href="login.php" class="btn" style="margin-top: 20px;">Go to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>