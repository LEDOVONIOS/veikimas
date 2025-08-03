<?php
// Configuration and Error Checker
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Configuration Check for Project Monitoring System</h1>";

// 1. Check PHP Version
echo "<h2>1. PHP Version Check</h2>";
$phpVersion = phpversion();
echo "Current PHP Version: " . $phpVersion . "<br>";
if (version_compare($phpVersion, '7.0.0', '<')) {
    echo "<span style='color: red;'>⚠️ PHP version is too old. Minimum required: 7.0</span><br>";
} else {
    echo "<span style='color: green;'>✅ PHP version is compatible</span><br>";
}

// 2. Check Required PHP Extensions
echo "<h2>2. Required PHP Extensions</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color: green;'>✅ $ext extension is loaded</span><br>";
    } else {
        echo "<span style='color: red;'>❌ $ext extension is NOT loaded</span><br>";
    }
}

// 3. Check Database Configuration
echo "<h2>3. Database Configuration</h2>";
$dbConfigFile = 'db.php';
if (file_exists($dbConfigFile)) {
    echo "<span style='color: green;'>✅ db.php file exists</span><br>";
    
    // Load the file content to check configuration
    $dbContent = file_get_contents($dbConfigFile);
    
    // Check if default values are still in place
    if (strpos($dbContent, 'your_database_name') !== false || 
        strpos($dbContent, 'your_database_user') !== false || 
        strpos($dbContent, 'your_database_password') !== false) {
        echo "<span style='color: red;'>❌ Database credentials have NOT been updated from default values!</span><br>";
        echo "<strong>Action Required:</strong> Edit db.php and update DB_NAME, DB_USER, and DB_PASS with your actual database credentials.<br>";
    } else {
        echo "<span style='color: green;'>✅ Database credentials appear to be configured</span><br>";
        
        // Try to connect
        try {
            require_once 'db.php';
            echo "<span style='color: green;'>✅ Database connection successful</span><br>";
            
            // Check tables
            echo "<h3>Database Tables:</h3>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($tables)) {
                echo "<span style='color: red;'>❌ No tables found in database. Run install.php first!</span><br>";
            } else {
                echo "Found tables: " . implode(', ', $tables) . "<br>";
                
                // Check required tables
                $requiredTables = ['users', 'projects', 'incidents', 'uptime_logs', 'roles'];
                foreach ($requiredTables as $table) {
                    if (in_array($table, $tables)) {
                        echo "<span style='color: green;'>✅ Table '$table' exists</span><br>";
                    } else {
                        echo "<span style='color: red;'>❌ Table '$table' is MISSING</span><br>";
                    }
                }
            }
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
        }
    }
} else {
    echo "<span style='color: red;'>❌ db.php file NOT found!</span><br>";
}

// 4. Check Session Configuration
echo "<h2>4. Session Configuration</h2>";
$sessionPath = ini_get('session.save_path');
echo "Session save path: " . ($sessionPath ?: 'Default system path') . "<br>";
if ($sessionPath && !is_writable($sessionPath)) {
    echo "<span style='color: red;'>❌ Session save path is NOT writable</span><br>";
} else {
    echo "<span style='color: green;'>✅ Session configuration appears OK</span><br>";
}

// 5. Check File Permissions
echo "<h2>5. File Permissions Check</h2>";
$requiredFiles = [
    'db.php',
    'login.php',
    'dashboard.php',
    'includes/roles.php',
    'includes/monitoring_functions.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "<span style='color: green;'>✅ $file is readable</span><br>";
        } else {
            echo "<span style='color: red;'>❌ $file is NOT readable</span><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ $file NOT found</span><br>";
    }
}

// 6. Check .htaccess issues
echo "<h2>6. .htaccess Configuration</h2>";
if (file_exists('.htaccess')) {
    echo "<span style='color: green;'>✅ .htaccess file exists</span><br>";
    
    // Check if mod_rewrite is enabled
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        if (in_array('mod_rewrite', $modules)) {
            echo "<span style='color: green;'>✅ mod_rewrite is enabled</span><br>";
        } else {
            echo "<span style='color: yellow;'>⚠️ mod_rewrite might not be enabled</span><br>";
        }
    }
} else {
    echo "<span style='color: yellow;'>⚠️ .htaccess file not found</span><br>";
}

echo "<h2>Summary and Recommendations</h2>";
echo "<ul>";
echo "<li>If you see database credential errors, edit <strong>db.php</strong> and update the database settings</li>";
echo "<li>If tables are missing, run <strong>install.php</strong> to create them</li>";
echo "<li>If you get permission errors, ensure all PHP files are readable by the web server</li>";
echo "<li>Check your web server error logs for more detailed error messages</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='install.php'>Run Installation Script</a> | <a href='login.php'>Go to Login</a></p>";
?>