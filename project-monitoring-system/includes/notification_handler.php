<?php
/**
 * Notification Handler
 * Handles monitoring event notifications and alerts
 */

require_once dirname(__DIR__) . '/db.php';
require_once 'monitoring_functions.php';

/**
 * Check project status and send notifications if needed
 */
function checkProjectStatus($pdo, $projectId) {
    try {
        // Get project details
        $stmt = $pdo->prepare("
            SELECT p.*, u.email, u.name as user_name 
            FROM projects p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        
        if (!$project) {
            return false;
        }
        
        $userId = $project['user_id'];
        
        // Check if project is down
        $stmt = $pdo->prepare("
            SELECT is_up, response_time 
            FROM uptime_logs 
            WHERE project_id = ? 
            ORDER BY checked_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$projectId]);
        $lastCheck = $stmt->fetch();
        
        if ($lastCheck) {
            // Check previous status to detect state change
            $stmt = $pdo->prepare("
                SELECT is_up 
                FROM uptime_logs 
                WHERE project_id = ? 
                ORDER BY checked_at DESC 
                LIMIT 1 OFFSET 1
            ");
            $stmt->execute([$projectId]);
            $previousCheck = $stmt->fetch();
            
            // Project went down
            if ($previousCheck && $previousCheck['is_up'] && !$lastCheck['is_up']) {
                $title = "Svetainės statusas pasikeitė {$project['project_name']}";
                $message = "Your project {$project['project_name']} is not responding. URL: {$project['project_url']}";
                sendNotification($pdo, $userId, $projectId, 'down', $title, $message);
                
                // Send email notification
                sendEmailNotification($pdo, $project, 'status_change', $title, $message);
                
                // Create incident
                $stmt = $pdo->prepare("
                    INSERT INTO incidents (project_id, status, root_cause, started_at)
                    VALUES (?, 'Open', 'Service unavailable', NOW())
                ");
                $stmt->execute([$projectId]);
            }
            
            // Project came back up
            if ($previousCheck && !$previousCheck['is_up'] && $lastCheck['is_up']) {
                $title = "✅ {$project['project_name']} is UP";
                $message = "Your project {$project['project_name']} is back online. Response time: {$lastCheck['response_time']}ms";
                sendNotification($pdo, $userId, $projectId, 'up', $title, $message);
                
                // Close open incidents
                $stmt = $pdo->prepare("
                    UPDATE incidents 
                    SET status = 'Resolved', 
                        resolved_at = NOW(),
                        duration = TIMESTAMPDIFF(MINUTE, started_at, NOW())
                    WHERE project_id = ? AND status = 'Open'
                ");
                $stmt->execute([$projectId]);
            }
        }
        
        // Check SSL certificate expiry
        $sslInfo = getSSLInfo($pdo, $projectId);
        if ($sslInfo) {
            $expiryDate = new DateTime($sslInfo['expiry_date']);
            $now = new DateTime();
            $daysUntilExpiry = $now->diff($expiryDate)->days;
            
            // Notify if SSL expires in exactly 30 days
            if ($daysUntilExpiry == 30) {
                // Check if we already sent a notification for this exact date
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE project_id = ? 
                    AND type = 'ssl_expiry_30' 
                    AND DATE(sent_at) = CURDATE()
                ");
                $stmt->execute([$projectId]);
                $recentNotification = $stmt->fetch();
                
                if ($recentNotification['count'] == 0) {
                    $title = "SSL galiojimas eina į pabaigą {$project['project_name']}";
                    $message = "SSL certificate for {$project['project_name']} expires in {$daysUntilExpiry} days ({$expiryDate->format('M d, Y')})";
                    sendNotification($pdo, $userId, $projectId, 'ssl_expiry_30', $title, $message);
                    
                    // Send email notification
                    sendEmailNotification($pdo, $project, 'ssl_expiry', $title, $message);
                }
            }
            
            // Check domain expiry
            $domainExpiryDate = new DateTime($sslInfo['domain_expiry_date']);
            $daysUntilDomainExpiry = $now->diff($domainExpiryDate)->days;
            
            if ($daysUntilDomainExpiry == 30) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE project_id = ? 
                    AND type = 'domain_expiry_30' 
                    AND DATE(sent_at) = CURDATE()
                ");
                $stmt->execute([$projectId]);
                $recentNotification = $stmt->fetch();
                
                if ($recentNotification['count'] == 0) {
                    $title = "Domeno galiojimas eina į pabaigą {$project['project_name']}";
                    $message = "Domain for {$project['project_name']} expires in {$daysUntilDomainExpiry} days ({$domainExpiryDate->format('M d, Y')})";
                    sendNotification($pdo, $userId, $projectId, 'domain_expiry_30', $title, $message);
                    
                    // Send email notification
                    sendEmailNotification($pdo, $project, 'domain_expiry', $title, $message);
                }
            }
        }
        
        // Check cron job failures
        $cronJobs = getCronJobs($pdo, $projectId);
        foreach ($cronJobs as $job) {
            if ($job['status'] === 'failed') {
                // Check if we already notified about this failure
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE project_id = ? 
                    AND type = 'cron_failed' 
                    AND message LIKE ?
                    AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute([$projectId, "%{$job['job_name']}%"]);
                $recentNotification = $stmt->fetch();
                
                if ($recentNotification['count'] == 0) {
                    $title = "❌ Cron Job Failed";
                    $message = "Cron job '{$job['job_name']}' failed for {$project['project_name']}. Error: {$job['error_message']}";
                    sendNotification($pdo, $userId, $projectId, 'cron_failed', $title, $message);
                }
            }
        }
        
        // Update last checked timestamp
        updateLastChecked($pdo, $projectId);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Notification handler error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process all active projects for monitoring
 */
function processAllProjects($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE status = 'active'");
        $stmt->execute();
        $projects = $stmt->fetchAll();
        
        foreach ($projects as $project) {
            checkProjectStatus($pdo, $project['id']);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Process all projects error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark notification as read
 */
function markNotificationRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get user notifications
 */
function getUserNotifications($pdo, $userId, $limit = 10, $unreadOnly = false) {
    try {
        $sql = "
            SELECT n.*, p.project_name 
            FROM notifications n
            JOIN projects p ON n.project_id = p.id
            WHERE n.user_id = ?
        ";
        
        if ($unreadOnly) {
            $sql .= " AND n.is_read = FALSE";
        }
        
        $sql .= " ORDER BY n.sent_at DESC LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Send email notification
 */
function sendEmailNotification($pdo, $project, $type, $subject, $message) {
    try {
        // Check if email is configured for this project
        $stmt = $pdo->prepare("
            SELECT email FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$project['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        $to = $user['email'];
        $headers = "From: noreply@projectmonitor.com\r\n";
        $headers .= "Reply-To: noreply@projectmonitor.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Create HTML email body
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #1a202c; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f7fafc; padding: 20px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .alert { background-color: #fed7d7; border-left: 4px solid #f56565; padding: 10px; margin: 10px 0; }
                .info { background-color: #bee3f8; border-left: 4px solid #4299e1; padding: 10px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Project Monitoring System</h1>
                </div>
                <div class='content'>
                    <h2>{$subject}</h2>
                    <p>{$message}</p>
                    <p><strong>Project:</strong> {$project['project_name']}</p>
                    <p><strong>URL:</strong> {$project['project_url']}</p>
                    <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Project Monitoring System.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        $sent = mail($to, $subject, $htmlBody, $headers);
        
        if ($sent) {
            // Log email sent
            error_log("Email sent to {$to} for project {$project['project_name']} - Type: {$type}");
        }
        
        return $sent;
        
    } catch (Exception $e) {
        error_log("Failed to send email notification: " . $e->getMessage());
        return false;
    }
}

// This file can be called via cron job to check all projects
if (php_sapi_name() === 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === 'notification_handler.php') {
    processAllProjects($pdo);
}

?>