<?php
/**
 * Cron job for monitoring checks
 * Can be called via browser with key parameter or directly via CLI
 */

require_once __DIR__ . '/config/config.php';

// Allow execution from both CLI and web
if (php_sapi_name() !== 'cli') {
    // Web access - check for cron key
    if (!isset($_GET['key']) || $_GET['key'] !== CRON_KEY) {
        die('Access denied. Invalid cron key.');
    }
}

require_once __DIR__ . '/includes/init.php';

// Set time limit
set_time_limit(0);

// Update last cron run
$db->update(
    DB_PREFIX . 'settings',
    ['setting_value' => date('Y-m-d H:i:s')],
    'setting_key = ?',
    ['cron_last_run']
);

// Get all active projects that need checking
$projects = $db->fetchAllArray(
    "SELECT * FROM " . DB_PREFIX . "projects 
     WHERE status = 'active' 
     AND (last_check IS NULL OR last_check < DATE_SUB(NOW(), INTERVAL check_interval SECOND))
     ORDER BY last_check ASC"
);

$monitor = new Monitor();
$checkedCount = 0;
$errors = [];

echo "Starting monitoring checks at " . date('Y-m-d H:i:s') . "\n";
echo "Found " . count($projects) . " projects to check\n\n";

foreach ($projects as $project) {
    echo "Checking: " . $project['name'] . " (" . $project['url'] . ")... ";
    
    try {
        $result = $monitor->checkProject($project['id']);
        
        if ($result['is_up']) {
            echo "UP (" . $result['response_time'] . "ms)\n";
        } else {
            echo "DOWN (Status: " . ($result['status_code'] ?: 'N/A') . ", Error: " . ($result['error_message'] ?: 'Unknown') . ")\n";
        }
        
        $checkedCount++;
    } catch (Exception $e) {
        $error = "Error checking project " . $project['name'] . ": " . $e->getMessage();
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors[] = $error;
        logError($error);
    }
}

echo "\n";
echo "Monitoring complete at " . date('Y-m-d H:i:s') . "\n";
echo "Checked: " . $checkedCount . " projects\n";

if (!empty($errors)) {
    echo "Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "  - " . $error . "\n";
    }
}

// Clean up old monitor logs (keep last 90 days)
$db->query(
    "DELETE FROM " . DB_PREFIX . "monitor_logs 
     WHERE checked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
);

// If called from web, provide JSON response
if (php_sapi_name() !== 'cli' && isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'checked' => $checkedCount,
        'errors' => count($errors),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}