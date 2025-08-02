<?php
/**
 * Test Monitoring Script
 * Verifies that the monitoring system is working correctly
 * Run this before setting up the cron job
 */

// Prevent running from browser
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

echo "=== Project Monitoring System Test ===\n\n";

// Check PHP version
echo "1. Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
    echo "✓ PHP " . PHP_VERSION . "\n";
} else {
    echo "✗ PHP " . PHP_VERSION . " (7.0+ required)\n";
    exit(1);
}

// Check required extensions
echo "2. Checking required extensions:\n";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'openssl'];
$missing = [];

foreach ($required_extensions as $ext) {
    echo "   - $ext: ";
    if (extension_loaded($ext)) {
        echo "✓\n";
    } else {
        echo "✗\n";
        $missing[] = $ext;
    }
}

if (!empty($missing)) {
    echo "\n✗ Missing extensions: " . implode(', ', $missing) . "\n";
    echo "Please install the missing extensions.\n";
    exit(1);
}

// Check database connection
echo "\n3. Checking database connection... ";
try {
    require_once dirname(__FILE__) . '/../db.php';
    echo "✓ Connected\n";
} catch (Exception $e) {
    echo "✗ Failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check required tables
echo "4. Checking database tables:\n";
$required_tables = [
    'users',
    'projects', 
    'incidents',
    'uptime_logs',
    'http_status_logs',
    'response_times',
    'ssl_certificates',
    'notifications'
];

foreach ($required_tables as $table) {
    echo "   - $table: ";
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✓\n";
    } catch (Exception $e) {
        echo "✗ (run db_update.sql)\n";
    }
}

// Check if monitoring functions exist
echo "\n5. Checking monitoring functions... ";
$functions_file = dirname(__FILE__) . '/../includes/monitoring_functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
    echo "✓\n";
} else {
    echo "✗ File not found\n";
    exit(1);
}

// Check if notification handler exists
echo "6. Checking notification handler... ";
$notification_file = dirname(__FILE__) . '/../includes/notification_handler.php';
if (file_exists($notification_file)) {
    echo "✓\n";
} else {
    echo "✗ File not found\n";
    exit(1);
}

// Check for projects
echo "\n7. Checking for projects... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    if ($count > 0) {
        echo "✓ Found $count project(s)\n";
        
        // List projects
        echo "\n   Projects:\n";
        $stmt = $pdo->query("SELECT id, project_name, project_url FROM projects LIMIT 5");
        while ($project = $stmt->fetch()) {
            echo "   - [{$project['id']}] {$project['project_name']}: {$project['project_url']}\n";
        }
        
        if ($count > 5) {
            echo "   ... and " . ($count - 5) . " more\n";
        }
    } else {
        echo "⚠ No projects found\n";
        echo "   Please create at least one project before running monitoring.\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test URL checking function
echo "\n8. Testing URL checking... ";
function testUrlCheck($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY => true
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 400,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

$testUrl = 'https://www.google.com';
$result = testUrlCheck($testUrl);

if ($result['success']) {
    echo "✓ Can reach external URLs (tested: $testUrl)\n";
} else {
    echo "✗ Cannot reach external URLs\n";
    echo "   Error: " . ($result['error'] ?: "HTTP {$result['http_code']}") . "\n";
    echo "   This may be due to firewall or proxy settings.\n";
}

// Check logs directory
echo "\n9. Checking logs directory... ";
$logs_dir = dirname(__FILE__) . '/../logs';
if (!file_exists($logs_dir)) {
    if (mkdir($logs_dir, 0755, true)) {
        echo "✓ Created logs directory\n";
    } else {
        echo "✗ Could not create logs directory\n";
    }
} else {
    echo "✓ Exists\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "If all checks passed, you can:\n";
echo "1. Run monitoring manually: php " . dirname(__FILE__) . "/monitor_projects.php --verbose\n";
echo "2. Set up cron job: " . dirname(__FILE__) . "/setup-cron.sh\n";
echo "\nFor detailed instructions, see CRON_SETUP.md\n";