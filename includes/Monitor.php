<?php
/**
 * Monitor class for website monitoring functionality
 */
class Monitor {
    private $db;
    private $mailer;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new Mailer();
    }
    
    /**
     * Check a single project
     */
    public function checkProject($projectId) {
        $project = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "projects WHERE id = ? AND status = 'active'",
            [$projectId]
        );
        
        if (!$project) {
            return false;
        }
        
        // Perform HTTP check
        $checkResult = $this->performHttpCheck($project);
        
        // Log the result
        $this->logMonitorResult($project['id'], $checkResult);
        
        // Update project status
        $this->updateProjectStatus($project, $checkResult);
        
        // Check SSL if enabled
        if (ENABLE_SSL_CHECK && strpos($project['url'], 'https://') === 0) {
            $this->checkSSL($project);
        }
        
        // Check domain expiration if enabled
        if (ENABLE_WHOIS) {
            $this->checkDomainExpiration($project);
        }
        
        return $checkResult;
    }
    
    /**
     * Perform HTTP check on a URL
     */
    private function performHttpCheck($project) {
        $startTime = microtime(true);
        $result = [
            'is_up' => false,
            'status_code' => null,
            'response_time' => null,
            'error_message' => null
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $project['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $project['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Website Monitor/1.0');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, $project['method'] === 'HEAD');
        
        if ($project['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        $response = curl_exec($ch);
        $endTime = microtime(true);
        
        if (curl_errno($ch)) {
            $result['error_message'] = curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['status_code'] = $httpCode;
            $result['response_time'] = round(($endTime - $startTime) * 1000, 3);
            
            // Check if status code matches expected
            $result['is_up'] = ($httpCode == $project['expected_status']);
            
            // If search string is specified, check for it
            if ($result['is_up'] && !empty($project['search_string']) && $project['method'] !== 'HEAD') {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $body = substr($response, $headerSize);
                $result['is_up'] = strpos($body, $project['search_string']) !== false;
                
                if (!$result['is_up']) {
                    $result['error_message'] = 'Search string not found';
                }
            }
        }
        
        curl_close($ch);
        return $result;
    }
    
    /**
     * Log monitor result to database
     */
    private function logMonitorResult($projectId, $result) {
        $this->db->insert(DB_PREFIX . 'monitor_logs', [
            'project_id' => $projectId,
            'status_code' => $result['status_code'],
            'response_time' => $result['response_time'],
            'is_up' => $result['is_up'] ? 1 : 0,
            'error_message' => $result['error_message']
        ]);
    }
    
    /**
     * Update project status and handle incidents
     */
    private function updateProjectStatus($project, $checkResult) {
        $wasUp = $project['current_status'] === 'up';
        $isUp = $checkResult['is_up'];
        
        // Update last check time
        $updateData = [
            'last_check' => getUtcTimestamp(),
            'current_status' => $isUp ? 'up' : 'down'
        ];
        
        // Status changed
        if ($wasUp !== $isUp) {
            $updateData['last_status_change'] = getUtcTimestamp();
            
            if (!$isUp) {
                // Site went down - create incident
                $this->createIncident($project['id'], $checkResult['error_message']);
                
                // Send notification
                if ($project['notify_down']) {
                    $this->sendDownNotification($project, $checkResult);
                }
            } else {
                // Site came back up - close incident
                $this->closeIncident($project['id']);
                
                // Send notification
                if ($project['notify_up']) {
                    $this->sendUpNotification($project);
                }
            }
        }
        
        $this->db->update(
            DB_PREFIX . 'projects',
            $updateData,
            'id = ?',
            [$project['id']]
        );
    }
    
    /**
     * Create a new incident
     */
    private function createIncident($projectId, $reason = null) {
        // Check if there's already an open incident
        $openIncident = $this->db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "incident_logs 
             WHERE project_id = ? AND ended_at IS NULL",
            [$projectId]
        );
        
        if (!$openIncident) {
            $this->db->insert(DB_PREFIX . 'incident_logs', [
                'project_id' => $projectId,
                'reason' => $reason
            ]);
        }
    }
    
    /**
     * Close an open incident
     */
    private function closeIncident($projectId) {
        $incident = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "incident_logs 
             WHERE project_id = ? AND ended_at IS NULL",
            [$projectId]
        );
        
        if ($incident) {
            $duration = time() - strtotime($incident['started_at']);
            
            $this->db->update(
                DB_PREFIX . 'incident_logs',
                [
                    'ended_at' => getUtcTimestamp(),
                    'duration' => $duration
                ],
                'id = ?',
                [$incident['id']]
            );
        }
    }
    
    /**
     * Check SSL certificate
     */
    private function checkSSL($project) {
        $urlParts = parse_url($project['url']);
        if (!isset($urlParts['host'])) {
            return;
        }
        
        $host = $urlParts['host'];
        $port = isset($urlParts['port']) ? $urlParts['port'] : 443;
        
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
            $this->updateSSLData($project['id'], null, "SSL connection failed: $errstr");
            return;
        }
        
        $params = stream_context_get_params($stream);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        fclose($stream);
        
        if ($cert) {
            $validFrom = date('Y-m-d H:i:s', $cert['validFrom_time_t']);
            $validTo = date('Y-m-d H:i:s', $cert['validTo_time_t']);
            $daysRemaining = max(0, floor(($cert['validTo_time_t'] - time()) / 86400));
            
            $sslData = [
                'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
                'subject' => $cert['subject']['CN'] ?? $host,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'days_remaining' => $daysRemaining,
                'is_valid' => $daysRemaining > 0 ? 1 : 0
            ];
            
            $this->updateSSLData($project['id'], $sslData);
            
            // Send notification if expiring soon
            if ($project['notify_ssl'] && $daysRemaining <= SSL_WARNING_DAYS && $daysRemaining > 0) {
                $this->sendSSLExpiryNotification($project, $daysRemaining);
            }
        }
    }
    
    /**
     * Update SSL data in database
     */
    private function updateSSLData($projectId, $data, $error = null) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "ssl_data WHERE project_id = ?",
            [$projectId]
        );
        
        if ($error) {
            $data = [
                'error_message' => $error,
                'last_check' => getUtcTimestamp()
            ];
        } else {
            $data['error_message'] = null;
            $data['last_check'] = getUtcTimestamp();
        }
        
        if ($existing) {
            $this->db->update(
                DB_PREFIX . 'ssl_data',
                $data,
                'project_id = ?',
                [$projectId]
            );
        } else {
            $data['project_id'] = $projectId;
            $this->db->insert(DB_PREFIX . 'ssl_data', $data);
        }
    }
    
    /**
     * Check domain expiration via WHOIS
     */
    private function checkDomainExpiration($project) {
        $urlParts = parse_url($project['url']);
        if (!isset($urlParts['host'])) {
            return;
        }
        
        $domain = $this->extractDomain($urlParts['host']);
        if (!$domain) {
            return;
        }
        
        // Simple WHOIS implementation
        $whoisData = $this->getWhoisData($domain);
        if ($whoisData) {
            $this->updateDomainData($project['id'], $domain, $whoisData);
            
            // Send notification if expiring soon
            if ($project['notify_domain'] && 
                isset($whoisData['days_remaining']) && 
                $whoisData['days_remaining'] <= DOMAIN_WARNING_DAYS && 
                $whoisData['days_remaining'] > 0) {
                $this->sendDomainExpiryNotification($project, $whoisData['days_remaining']);
            }
        }
    }
    
    /**
     * Extract domain from hostname
     */
    private function extractDomain($host) {
        // Remove www. prefix
        $host = preg_replace('/^www\./i', '', $host);
        
        // Basic domain extraction (can be improved)
        return $host;
    }
    
    /**
     * Get WHOIS data for a domain
     */
    private function getWhoisData($domain) {
        // This is a simplified implementation
        // In production, you might want to use a WHOIS API or library
        
        $whoisServers = [
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'info' => 'whois.afilias.net',
            'lt' => 'whois.domreg.lt'
        ];
        
        $parts = explode('.', $domain);
        $tld = end($parts);
        
        if (!isset($whoisServers[$tld])) {
            return null;
        }
        
        $whoisServer = $whoisServers[$tld];
        
        $fp = @fsockopen($whoisServer, 43, $errno, $errstr, 10);
        if (!$fp) {
            return null;
        }
        
        fputs($fp, $domain . "\r\n");
        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp, 128);
        }
        fclose($fp);
        
        // Parse expiry date from response
        $expiryDate = null;
        if (preg_match('/Expir.*?Date:\s*(.+)/i', $response, $matches)) {
            $expiryDate = date('Y-m-d', strtotime($matches[1]));
        } elseif (preg_match('/Expires.*?:\s*(.+)/i', $response, $matches)) {
            $expiryDate = date('Y-m-d', strtotime($matches[1]));
        }
        
        if ($expiryDate) {
            $daysRemaining = max(0, floor((strtotime($expiryDate) - time()) / 86400));
            
            return [
                'expiry_date' => $expiryDate,
                'days_remaining' => $daysRemaining,
                'registrar' => $this->parseRegistrar($response)
            ];
        }
        
        return null;
    }
    
    /**
     * Parse registrar from WHOIS response
     */
    private function parseRegistrar($whoisResponse) {
        if (preg_match('/Registrar:\s*(.+)/i', $whoisResponse, $matches)) {
            return trim($matches[1]);
        }
        return 'Unknown';
    }
    
    /**
     * Update domain data in database
     */
    private function updateDomainData($projectId, $domain, $data) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "domain_data WHERE project_id = ?",
            [$projectId]
        );
        
        $updateData = [
            'domain' => $domain,
            'registrar' => $data['registrar'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'days_remaining' => $data['days_remaining'] ?? null,
            'last_check' => getUtcTimestamp()
        ];
        
        if ($existing) {
            $this->db->update(
                DB_PREFIX . 'domain_data',
                $updateData,
                'project_id = ?',
                [$projectId]
            );
        } else {
            $updateData['project_id'] = $projectId;
            $this->db->insert(DB_PREFIX . 'domain_data', $updateData);
        }
    }
    
    /**
     * Send down notification
     */
    private function sendDownNotification($project, $checkResult) {
        $user = $this->db->fetchOne(
            "SELECT email, username FROM " . DB_PREFIX . "users WHERE id = ?",
            [$project['user_id']]
        );
        
        $recipient = $project['notify_email'] ?: $user['email'];
        $subject = "🔴 {$project['name']} is DOWN";
        
        // Format root cause
        $rootCause = $checkResult['error_message'] ?: 'Unknown Error';
        if ($checkResult['status_code']) {
            if ($checkResult['status_code'] >= 500) {
                $rootCause = "HTTP {$checkResult['status_code']} - Server Error";
            } elseif ($checkResult['status_code'] >= 400) {
                $rootCause = "HTTP {$checkResult['status_code']} - Client Error";
            } elseif ($checkResult['status_code'] >= 300) {
                $rootCause = "HTTP {$checkResult['status_code']} - Redirect Error";
            }
        } elseif (strpos($rootCause, 'timed out') !== false) {
            $rootCause = 'Connection Timeout';
        } elseif (strpos($rootCause, 'DNS') !== false || strpos($rootCause, 'resolve') !== false) {
            $rootCause = 'DNS Error';
        }
        
        // Use HTML template
        $html = $this->mailer->getIncidentStartedTemplate([
            'user_name' => $user['username'],
            'monitor_name' => $project['name'],
            'checked_url' => $project['url'],
            'root_cause' => $rootCause,
            'incident_start' => fromUtcTimestamp(getUtcTimestamp()),
            'status_code' => $checkResult['status_code'],
            'project_id' => $project['id']
        ]);
        
        if ($this->mailer->send($recipient, $subject, $html, true)) {
            $this->logNotification($project['id'], 'down', $recipient, $subject);
        }
    }
    
    /**
     * Send up notification
     */
    private function sendUpNotification($project) {
        $user = $this->db->fetchOne(
            "SELECT email, username FROM " . DB_PREFIX . "users WHERE id = ?",
            [$project['user_id']]
        );
        
        // Get incident duration
        $incident = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "incident_logs 
             WHERE project_id = ? ORDER BY id DESC LIMIT 1",
            [$project['id']]
        );
        
        $recipient = $project['notify_email'] ?: $user['email'];
        $subject = "🟢 {$project['name']} is UP";
        
        // Format duration for email
        $durationText = 'Unknown';
        if ($incident && $incident['duration']) {
            $seconds = $incident['duration'];
            if ($seconds < 60) {
                $durationText = $seconds . ' seconds';
            } elseif ($seconds < 3600) {
                $minutes = floor($seconds / 60);
                $remainingSeconds = $seconds % 60;
                $durationText = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                if ($remainingSeconds > 0) {
                    $durationText .= ' ' . $remainingSeconds . ' second' . ($remainingSeconds > 1 ? 's' : '');
                }
            } else {
                $hours = floor($seconds / 3600);
                $remainingMinutes = floor(($seconds % 3600) / 60);
                $durationText = $hours . ' hour' . ($hours > 1 ? 's' : '');
                if ($remainingMinutes > 0) {
                    $durationText .= ' ' . $remainingMinutes . ' minute' . ($remainingMinutes > 1 ? 's' : '');
                }
            }
        }
        
        // Use HTML template
        $html = $this->mailer->getIncidentResolvedTemplate([
            'user_name' => $user['username'],
            'monitor_name' => $project['name'],
            'checked_url' => $project['url'],
            'root_cause' => $incident['reason'] ?: 'Connection Failed',
            'incident_start' => $incident['started_at'] ? fromUtcTimestamp($incident['started_at']) : 'Unknown',
            'incident_resolved' => fromUtcTimestamp(getUtcTimestamp()),
            'incident_duration' => $durationText,
            'project_id' => $project['id']
        ]);
        
        if ($this->mailer->send($recipient, $subject, $html, true)) {
            $this->logNotification($project['id'], 'up', $recipient, $subject);
        }
    }
    
    /**
     * Send SSL expiry notification
     */
    private function sendSSLExpiryNotification($project, $daysRemaining) {
        // Check if we already sent a notification recently
        $recentNotification = $this->db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "notification_logs 
             WHERE project_id = ? AND type = 'ssl_expiry' 
             AND sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$project['id']]
        );
        
        if ($recentNotification) {
            return;
        }
        
        $user = $this->db->fetchOne(
            "SELECT email FROM " . DB_PREFIX . "users WHERE id = ?",
            [$project['user_id']]
        );
        
        $recipient = $project['notify_email'] ?: $user['email'];
        $subject = "⚠️ SSL Certificate Expiring Soon - {$project['name']}";
        
        $body = "The SSL certificate for your monitored website is expiring soon.\n\n";
        $body .= "Project: {$project['name']}\n";
        $body .= "URL: {$project['url']}\n";
        $body .= "Days Remaining: {$daysRemaining}\n";
        $body .= "Time: " . fromUtcTimestamp(getUtcTimestamp()) . "\n\n";
        $body .= "Please renew your SSL certificate to avoid security warnings.";
        
        if ($this->mailer->send($recipient, $subject, $body)) {
            $this->logNotification($project['id'], 'ssl_expiry', $recipient, $subject);
        }
    }
    
    /**
     * Send domain expiry notification
     */
    private function sendDomainExpiryNotification($project, $daysRemaining) {
        // Check if we already sent a notification recently
        $recentNotification = $this->db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "notification_logs 
             WHERE project_id = ? AND type = 'domain_expiry' 
             AND sent_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$project['id']]
        );
        
        if ($recentNotification) {
            return;
        }
        
        $user = $this->db->fetchOne(
            "SELECT email FROM " . DB_PREFIX . "users WHERE id = ?",
            [$project['user_id']]
        );
        
        $recipient = $project['notify_email'] ?: $user['email'];
        $subject = "⚠️ Domain Expiring Soon - {$project['name']}";
        
        $body = "The domain for your monitored website is expiring soon.\n\n";
        $body .= "Project: {$project['name']}\n";
        $body .= "URL: {$project['url']}\n";
        $body .= "Days Remaining: {$daysRemaining}\n";
        $body .= "Time: " . fromUtcTimestamp(getUtcTimestamp()) . "\n\n";
        $body .= "Please renew your domain to avoid losing your website.";
        
        if ($this->mailer->send($recipient, $subject, $body)) {
            $this->logNotification($project['id'], 'domain_expiry', $recipient, $subject);
        }
    }
    
    /**
     * Log notification to database
     */
    private function logNotification($projectId, $type, $recipient, $subject) {
        $this->db->insert(DB_PREFIX . 'notification_logs', [
            'project_id' => $projectId,
            'type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => 'sent'
        ]);
    }
    
    /**
     * Format duration in seconds to human readable
     */
    private function formatDuration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' hours';
        } else {
            return round($seconds / 86400, 1) . ' days';
        }
    }
    
    /**
     * Get uptime percentage for a project
     */
    public function getUptimePercentage($projectId, $hours = 24) {
        $utc = new DateTime("-{$hours} hours", new DateTimeZone('UTC'));
        $since = $utc->format('Y-m-d H:i:s');
        
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_checks,
                SUM(is_up) as up_checks
             FROM " . DB_PREFIX . "monitor_logs 
             WHERE project_id = ? AND checked_at >= ?",
            [$projectId, $since]
        );
        
        if ($stats && $stats['total_checks'] > 0) {
            return round(($stats['up_checks'] / $stats['total_checks']) * 100, 2);
        }
        
        return 100; // No data, assume 100%
    }
    
    /**
     * Get response time statistics
     */
    public function getResponseTimeStats($projectId, $since) {
        return $this->db->fetchOne(
            "SELECT 
                AVG(response_time) as avg_time,
                MIN(response_time) as min_time,
                MAX(response_time) as max_time
             FROM " . DB_PREFIX . "monitor_logs 
             WHERE project_id = ? AND checked_at >= ? AND is_up = 1",
            [$projectId, $since]
        );
    }
    
    /**
     * Get monitor logs for charts
     */
    public function getMonitorLogs($projectId, $since, $groupBy = 'hour') {
        $dateFormat = '%Y-%m-%d %H:00:00'; // Default hourly
        
        if ($groupBy === 'day') {
            $dateFormat = '%Y-%m-%d';
        } elseif ($groupBy === 'month') {
            $dateFormat = '%Y-%m';
        }
        
        return $this->db->fetchAllArray(
            "SELECT 
                DATE_FORMAT(checked_at, '{$dateFormat}') as period,
                AVG(response_time) as avg_response_time,
                AVG(is_up) * 100 as uptime_percentage,
                COUNT(*) as check_count
             FROM " . DB_PREFIX . "monitor_logs 
             WHERE project_id = ? AND checked_at >= ?
             GROUP BY period
             ORDER BY period ASC",
            [$projectId, $since]
        );
    }
}