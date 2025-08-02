<?php
/**
 * Test script to check real monitoring data
 */
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/monitoring_functions.php';

// Get project ID from command line or use default
$projectId = isset($argv[1]) ? intval($argv[1]) : null;

if (!$projectId) {
    echo "Usage: php test-real-monitoring.php <project_id>\n";
    echo "\nFinding available projects...\n";
    
    try {
        $stmt = $pdo->query("SELECT id, project_name, project_url FROM projects ORDER BY id");
        $projects = $stmt->fetchAll();
        
        if (empty($projects)) {
            echo "No projects found in database.\n";
            exit(1);
        }
        
        echo "\nAvailable projects:\n";
        echo str_pad("ID", 5) . str_pad("Project Name", 30) . "URL\n";
        echo str_repeat("-", 80) . "\n";
        
        foreach ($projects as $project) {
            echo str_pad($project['id'], 5) . 
                 str_pad(substr($project['project_name'], 0, 29), 30) . 
                 $project['project_url'] . "\n";
        }
        
        echo "\nRun again with: php test-real-monitoring.php <project_id>\n";
        exit(0);
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Get project details
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        echo "Project with ID $projectId not found.\n";
        exit(1);
    }
    
    echo "\n=== Project Details ===\n";
    echo "Name: " . $project['project_name'] . "\n";
    echo "URL: " . $project['project_url'] . "\n";
    echo "Created: " . $project['date_created'] . "\n";
    echo "\n";
    
    // Check HTTP status logs
    echo "=== HTTP Status Code Logs (Last 7 Days) ===\n";
    $statusData = getRealStatusCodeData($pdo, $projectId, 7);
    
    if ($statusData && $statusData['total'] > 0) {
        echo "Total requests logged: " . $statusData['total'] . "\n\n";
        echo "Status Code Distribution:\n";
        foreach ($statusData['distribution'] as $code => $data) {
            echo sprintf("  %s: %d requests (%.1f%%)\n", 
                $code, 
                $data['count'], 
                $data['percentage']
            );
        }
    } else {
        echo "No HTTP status logs found for the last 7 days.\n";
        echo "\nThis means either:\n";
        echo "1. The monitoring cron job is not running\n";
        echo "2. The project was recently added and hasn't been monitored yet\n";
    }
    
    // Check latest status logs
    echo "\n=== Latest HTTP Status Logs (Last 10 entries) ===\n";
    $stmt = $pdo->prepare("
        SELECT status_code, checked_at 
        FROM http_status_logs 
        WHERE project_id = ? 
        ORDER BY checked_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$projectId]);
    $logs = $stmt->fetchAll();
    
    if (!empty($logs)) {
        echo str_pad("Status Code", 15) . "Checked At\n";
        echo str_repeat("-", 50) . "\n";
        foreach ($logs as $log) {
            echo str_pad($log['status_code'], 15) . $log['checked_at'] . "\n";
        }
    } else {
        echo "No status logs found.\n";
    }
    
    // Check if monitoring is running
    echo "\n=== Monitoring Status ===\n";
    $stmt = $pdo->prepare("
        SELECT MAX(checked_at) as last_check 
        FROM http_status_logs 
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $lastCheck = $stmt->fetchColumn();
    
    if ($lastCheck) {
        $lastCheckTime = new DateTime($lastCheck);
        $now = new DateTime();
        $diff = $now->diff($lastCheckTime);
        
        echo "Last monitored: " . $lastCheck . "\n";
        echo "Time since last check: ";
        
        if ($diff->days > 0) {
            echo $diff->days . " days, ";
        }
        echo $diff->h . " hours, " . $diff->i . " minutes ago\n";
        
        if ($diff->days > 0 || $diff->h > 1) {
            echo "\n⚠️  WARNING: Monitoring appears to be inactive!\n";
            echo "The cron job may not be running properly.\n";
        } else {
            echo "\n✓ Monitoring appears to be active.\n";
        }
    } else {
        echo "No monitoring data found.\n";
        echo "\n⚠️  WARNING: This project has never been monitored!\n";
    }
    
    echo "\n=== How to Start Monitoring ===\n";
    echo "1. Set up the cron job to run every 5 minutes:\n";
    echo "   */5 * * * * /usr/bin/php " . dirname(__DIR__) . "/scripts/monitor_projects.php\n";
    echo "\n2. Or run manually for testing:\n";
    echo "   php " . dirname(__DIR__) . "/scripts/monitor_projects.php --project-id=$projectId --verbose\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}