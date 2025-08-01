<?php
/**
 * Monitoring Handler Script
 * This script is designed to be run via cron job to collect website response times
 * and update monitoring data for all active projects.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/monitoring_functions.php';

// Prevent web access
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    die('This script can only be run from the command line.');
}

try {
    // Get all active projects
    $stmt = $pdo->prepare("SELECT id, project_name, project_url FROM projects WHERE status = 'active'");
    $stmt->execute();
    $projects = $stmt->fetchAll();
    
    foreach ($projects as $project) {
        echo "Checking project: {$project['project_name']} ({$project['project_url']})\n";
        
        // Measure response time
        $startTime = microtime(true);
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $project['project_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $error = curl_error($ch);
        curl_close($ch);
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds
        
        // Store response time
        if ($httpCode > 0) {
            $stmt = $pdo->prepare("INSERT INTO response_times (project_id, response_time, measured_at) VALUES (?, ?, NOW())");
            $stmt->execute([$project['id'], $responseTime]);
            echo "  - Response time: {$responseTime}ms (HTTP {$httpCode})\n";
        } else {
            echo "  - Error: {$error}\n";
        }
        
        // Update HTTP status log
        if ($httpCode > 0) {
            $stmt = $pdo->prepare("INSERT INTO http_status_logs (project_id, status_code, response_time, checked_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$project['id'], $httpCode, $responseTime]);
        }
        
        // Update last checked timestamp
        updateLastChecked($pdo, $project['id']);
        
        // Check if status changed and create notifications
        $lastStatus = getLastStatus($pdo, $project['id']);
        $currentStatus = ($httpCode >= 200 && $httpCode < 400) ? 'up' : 'down';
        
        if ($lastStatus !== $currentStatus) {
            // Status changed, create notification
            if ($currentStatus === 'down') {
                createNotification($pdo, $project['id'], 'down', 
                    "Project {$project['project_name']} is down", 
                    "Your project {$project['project_name']} is not responding. HTTP status: {$httpCode}");
            } else {
                createNotification($pdo, $project['id'], 'up', 
                    "Project {$project['project_name']} is back online", 
                    "Your project {$project['project_name']} is back online. Response time: {$responseTime}ms");
            }
        }
        
        // Update project status
        $stmt = $pdo->prepare("UPDATE projects SET current_status = ? WHERE id = ?");
        $stmt->execute([$currentStatus, $project['id']]);
        
        // Clean up old response time data (keep last 30 days)
        $stmt = $pdo->prepare("DELETE FROM response_times WHERE project_id = ? AND measured_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$project['id']]);
        
        // Small delay between checks
        sleep(1);
    }
    
    echo "\nMonitoring check completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Monitoring handler error: " . $e->getMessage());
}

/**
 * Helper function to get last status
 */
function getLastStatus($pdo, $projectId) {
    try {
        $stmt = $pdo->prepare("SELECT current_status FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $result = $stmt->fetch();
        return $result ? $result['current_status'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Helper function to create notification
 */
function createNotification($pdo, $projectId, $type, $title, $message) {
    try {
        // Get project owner
        $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        
        if ($project) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, project_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project['user_id'], $projectId, $type, $title, $message]);
        }
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
}