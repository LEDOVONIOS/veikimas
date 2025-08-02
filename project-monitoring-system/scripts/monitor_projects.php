<?php
/**
 * Project Monitoring Script
 * This script checks all active projects and logs their status
 * Designed to be run via cron job
 * 
 * Usage: php monitor_projects.php [--verbose] [--project-id=X]
 * 
 * Options:
 *   --verbose       Show detailed output
 *   --project-id=X  Monitor only specific project (useful for testing)
 */

// Prevent running from browser
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Configuration
$scriptDir = dirname(__FILE__);
require_once $scriptDir . '/../db.php';
require_once $scriptDir . '/../includes/monitoring_functions.php';
require_once $scriptDir . '/../includes/notification_handler.php';

// Parse command line arguments
$options = getopt('', ['verbose', 'project-id::']);
$verbose = isset($options['verbose']);
$specificProjectId = $options['project-id'] ?? null;

// Initialize counters
$stats = [
    'total_checks' => 0,
    'successful_checks' => 0,
    'failed_checks' => 0,
    'errors' => 0,
    'start_time' => microtime(true)
];

/**
 * Log message with timestamp
 */
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$type}] {$message}\n";
}

/**
 * Check if URL is accessible and get response details
 */
function checkUrl($url, $timeout = 10) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Project Monitor Bot/1.0',
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false
    ]);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    // Parse SSL certificate info if HTTPS
    $sslInfo = null;
    if (strpos($url, 'https://') === 0) {
        $sslInfo = getSSLCertificateInfo($url);
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 400 && empty($error),
        'http_code' => $httpCode,
        'response_time' => $responseTime,
        'error' => $error,
        'ssl_info' => $sslInfo,
        'size' => $info['size_download'] ?? 0
    ];
}

/**
 * Get SSL certificate information
 */
function getSSLCertificateInfo($url) {
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'];
    $port = $parsedUrl['port'] ?? 443;
    
    $context = stream_context_create([
        "ssl" => [
            "capture_peer_cert" => true,
            "verify_peer" => false,
            "verify_peer_name" => false
        ]
    ]);
    
    $stream = @stream_socket_client(
        "ssl://{$host}:{$port}", 
        $errno, 
        $errstr, 
        30, 
        STREAM_CLIENT_CONNECT, 
        $context
    );
    
    if (!$stream) {
        return null;
    }
    
    $params = stream_context_get_params($stream);
    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    
    fclose($stream);
    
    if (!$cert) {
        return null;
    }
    
    return [
        'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
        'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
        'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
        'days_until_expiry' => floor(($cert['validTo_time_t'] - time()) / 86400)
    ];
}

/**
 * Monitor a single project
 */
function monitorProject($pdo, $project, $verbose = false) {
    global $stats;
    
    if ($verbose) {
        logMessage("Checking project: {$project['project_name']} ({$project['project_url']})");
    }
    
    $stats['total_checks']++;
    
    // Check URL
    $result = checkUrl($project['project_url']);
    
    // Prepare base data for logging
    $logData = [
        'project_id' => $project['id'],
        'is_up' => $result['success'],
        'http_code' => $result['http_code'],
        'response_time' => $result['response_time'],
        'checked_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Log uptime status
        $stmt = $pdo->prepare("
            INSERT INTO uptime_logs (project_id, is_up, response_time, checked_at)
            VALUES (:project_id, :is_up, :response_time, :checked_at)
        ");
        $stmt->execute([
            ':project_id' => $logData['project_id'],
            ':is_up' => $logData['is_up'] ? 1 : 0,
            ':response_time' => $logData['response_time'],
            ':checked_at' => $logData['checked_at']
        ]);
        
        // Log HTTP status code
        if ($result['http_code'] > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO http_status_logs (project_id, status_code, checked_at)
                VALUES (:project_id, :status_code, :checked_at)
            ");
            $stmt->execute([
                ':project_id' => $logData['project_id'],
                ':status_code' => $result['http_code'],
                ':checked_at' => $logData['checked_at']
            ]);
        }
        
        // Log response time
        $stmt = $pdo->prepare("
            INSERT INTO response_times (project_id, response_time, measured_at)
            VALUES (:project_id, :response_time, :measured_at)
        ");
        $stmt->execute([
            ':project_id' => $logData['project_id'],
            ':response_time' => $logData['response_time'],
            ':measured_at' => $logData['checked_at']
        ]);
        
        // Update SSL certificate info if available
        if ($result['ssl_info']) {
            $stmt = $pdo->prepare("
                INSERT INTO ssl_certificates (project_id, issuer, expiry_date, last_checked)
                VALUES (:project_id, :issuer, :expiry_date, :last_checked)
                ON DUPLICATE KEY UPDATE
                    issuer = VALUES(issuer),
                    expiry_date = VALUES(expiry_date),
                    last_checked = VALUES(last_checked)
            ");
            $stmt->execute([
                ':project_id' => $logData['project_id'],
                ':issuer' => $result['ssl_info']['issuer'],
                ':expiry_date' => $result['ssl_info']['valid_to'],
                ':last_checked' => $logData['checked_at']
            ]);
            
            // Check for SSL expiry warning (30 days)
            if ($result['ssl_info']['days_until_expiry'] <= 30) {
                logMessage(
                    "WARNING: SSL certificate for {$project['project_name']} expires in {$result['ssl_info']['days_until_expiry']} days",
                    'WARN'
                );
            }
        }
        
        // Handle incident management
        handleIncident($pdo, $project, $result, $verbose);
        
        if ($result['success']) {
            $stats['successful_checks']++;
            if ($verbose) {
                logMessage("✓ {$project['project_name']} is UP (HTTP {$result['http_code']}, {$result['response_time']}ms)", 'SUCCESS');
            }
        } else {
            $stats['failed_checks']++;
            $errorMsg = $result['error'] ?: "HTTP {$result['http_code']}";
            logMessage("✗ {$project['project_name']} is DOWN ({$errorMsg})", 'ERROR');
        }
        
    } catch (Exception $e) {
        $stats['errors']++;
        logMessage("Database error for project {$project['project_name']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Handle incident creation and resolution
 */
function handleIncident($pdo, $project, $result, $verbose) {
    // Check for existing open incident
    $stmt = $pdo->prepare("
        SELECT id, started_at 
        FROM incidents 
        WHERE project_id = :project_id 
        AND status = 'open' 
        ORDER BY started_at DESC 
        LIMIT 1
    ");
    $stmt->execute([':project_id' => $project['id']]);
    $openIncident = $stmt->fetch();
    
    if (!$result['success'] && !$openIncident) {
        // Create new incident
        $rootCause = $result['error'] ?: "HTTP {$result['http_code']}";
        
        $stmt = $pdo->prepare("
            INSERT INTO incidents (project_id, description, root_cause, status, started_at)
            VALUES (:project_id, :description, :root_cause, 'open', :started_at)
        ");
        $stmt->execute([
            ':project_id' => $project['id'],
            ':description' => "Service is down - {$rootCause}",
            ':root_cause' => $rootCause,
            ':started_at' => date('Y-m-d H:i:s')
        ]);
        
        $incidentId = $pdo->lastInsertId();
        
        if ($verbose) {
            logMessage("Created incident #{$incidentId} for {$project['project_name']}", 'INCIDENT');
        }
        
        // Send notification
        $notificationHandler = new NotificationHandler($pdo);
        $notificationHandler->sendIncidentNotification($project['id'], 'down', [
            'incident_id' => $incidentId,
            'root_cause' => $rootCause
        ]);
        
    } elseif ($result['success'] && $openIncident) {
        // Resolve existing incident
        $stmt = $pdo->prepare("
            UPDATE incidents 
            SET status = 'resolved', 
                resolved_at = :resolved_at,
                resolution_notes = 'Automatically resolved - service is back online'
            WHERE id = :id
        ");
        $stmt->execute([
            ':resolved_at' => date('Y-m-d H:i:s'),
            ':id' => $openIncident['id']
        ]);
        
        if ($verbose) {
            logMessage("Resolved incident #{$openIncident['id']} for {$project['project_name']}", 'INCIDENT');
        }
        
        // Calculate downtime
        $startTime = new DateTime($openIncident['started_at']);
        $endTime = new DateTime();
        $downtime = $startTime->diff($endTime);
        
        // Send recovery notification
        $notificationHandler = new NotificationHandler($pdo);
        $notificationHandler->sendIncidentNotification($project['id'], 'up', [
            'incident_id' => $openIncident['id'],
            'downtime' => $downtime->format('%h hours %i minutes')
        ]);
    }
}

// Main execution
try {
    logMessage("Starting project monitoring...", 'START');
    
    // Build query
    $query = "SELECT * FROM projects WHERE 1=1";
    $params = [];
    
    if ($specificProjectId) {
        $query .= " AND id = :project_id";
        $params[':project_id'] = $specificProjectId;
    }
    
    $query .= " ORDER BY id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($projects)) {
        logMessage("No projects found to monitor", 'WARN');
        exit(0);
    }
    
    logMessage("Found " . count($projects) . " project(s) to monitor");
    
    // Monitor each project
    foreach ($projects as $project) {
        monitorProject($pdo, $project, $verbose);
        
        // Small delay between checks to avoid overwhelming servers
        usleep(500000); // 0.5 second
    }
    
    // Clean up old logs (keep last 90 days)
    $cleanupDate = date('Y-m-d H:i:s', strtotime('-90 days'));
    
    $tables = ['uptime_logs', 'http_status_logs', 'response_times'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE checked_at < :cleanup_date");
        $stmt->execute([':cleanup_date' => $cleanupDate]);
        if ($verbose && $stmt->rowCount() > 0) {
            logMessage("Cleaned up {$stmt->rowCount()} old records from {$table}");
        }
    }
    
    // Summary
    $executionTime = round(microtime(true) - $stats['start_time'], 2);
    logMessage("Monitoring completed in {$executionTime}s", 'COMPLETE');
    logMessage("Summary: {$stats['total_checks']} checks, {$stats['successful_checks']} up, {$stats['failed_checks']} down, {$stats['errors']} errors");
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'FATAL');
    exit(1);
}

exit(0);