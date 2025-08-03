<?php
// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix HTTP 500 Error - Project Monitoring System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .error { background: #fee; padding: 10px; border: 1px solid #fcc; margin: 10px 0; }
        .success { background: #efe; padding: 10px; border: 1px solid #cfc; margin: 10px 0; }
        .warning { background: #ffe; padding: 10px; border: 1px solid #ffc; margin: 10px 0; }
        .code { background: #f5f5f5; padding: 10px; font-family: monospace; border: 1px solid #ddd; }
        h2 { color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
        ol li { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Fix HTTP 500 Error After Login</h1>
    
    <h2>Quick Diagnosis</h2>
    <?php
    $issues = [];
    
    // Check database configuration
    if (file_exists('db.php')) {
        include_once 'db.php';
        if (DB_NAME === 'your_database_name' || DB_USER === 'your_database_user') {
            $issues[] = "Database credentials are still using placeholder values";
        }
    } else {
        $issues[] = "db.php file not found";
    }
    
    // Check PHP extensions
    $required_extensions = ['pdo', 'pdo_mysql', 'session'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $issues[] = "Required PHP extension '$ext' is not loaded";
        }
    }
    
    // Display issues
    if (!empty($issues)) {
        echo '<div class="error"><strong>Issues Found:</strong><ul>';
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo '</ul></div>';
    } else {
        echo '<div class="success">Basic configuration looks OK!</div>';
    }
    ?>
    
    <h2>Step-by-Step Solution</h2>
    
    <ol>
        <li>
            <strong>Update Database Credentials</strong>
            <div class="code">
                // Edit project-monitoring-system/db.php and replace:<br>
                define('DB_HOST', 'localhost');<br>
                define('DB_NAME', '<span style="color:red">your_actual_database_name</span>');<br>
                define('DB_USER', '<span style="color:red">your_actual_username</span>');<br>
                define('DB_PASS', '<span style="color:red">your_actual_password</span>');
            </div>
            <div class="warning">Get these credentials from your hosting control panel (cPanel, Hostinger Panel, etc.)</div>
        </li>
        
        <li>
            <strong>Import Database Tables</strong>
            <p>If you haven't already, import the SQL file to create the necessary tables:</p>
            <ul>
                <li>Go to phpMyAdmin in your hosting control panel</li>
                <li>Select your database</li>
                <li>Click "Import"</li>
                <li>Upload one of these files:
                    <ul>
                        <li><code>db.sql</code> (base tables)</li>
                        <li><code>db_update.sql</code> (if updating)</li>
                    </ul>
                </li>
            </ul>
        </li>
        
        <li>
            <strong>Check File Permissions</strong>
            <p>Ensure these permissions via FTP or File Manager:</p>
            <ul>
                <li>All .php files: 644</li>
                <li>All directories: 755</li>
                <li>The .htaccess file: 644</li>
            </ul>
        </li>
        
        <li>
            <strong>Test the Connection</strong>
            <p>After updating credentials, visit these test pages:</p>
            <ul>
                <li><a href="test_error.php" target="_blank">test_error.php</a> - Shows detailed errors</li>
                <li><a href="test_db_connection.php" target="_blank">test_db_connection.php</a> - Tests database</li>
                <li><a href="phpinfo.php" target="_blank">phpinfo.php</a> - Shows PHP configuration</li>
            </ul>
        </li>
    </ol>
    
    <h2>Common Hosting-Specific Issues</h2>
    
    <div class="warning">
        <strong>Hostinger:</strong> Make sure you're using the MySQL hostname provided in hPanel (might not be 'localhost')
    </div>
    
    <div class="warning">
        <strong>cPanel Hosts:</strong> Database name and username often have a prefix (e.g., 'username_dbname')
    </div>
    
    <h2>Still Having Issues?</h2>
    <p>Check the error logs:</p>
    <ul>
        <li>Look for <code>error_log</code> files in your directories</li>
        <li>Check your hosting control panel for error logs section</li>
        <li>Enable WordPress debug mode if using WordPress</li>
    </ul>
    
    <div class="code">
        // Add to the top of dashboard.php temporarily:<br>
        ini_set('display_errors', 1);<br>
        error_reporting(E_ALL);
    </div>
    
    <p><strong>Remember to remove debug files and disable error display once fixed!</strong></p>
</body>
</html>