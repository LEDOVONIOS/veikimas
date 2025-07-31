<?php
/**
 * Monitoring Functions
 * Helper functions for calculating uptime, generating mock data, etc.
 */

/**
 * Calculate uptime percentage for a given period
 */
function calculateUptime($pdo, $projectId, $days) {
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify("-{$days} days");
    
    try {
        // Get total checks and down checks
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_checks,
                SUM(CASE WHEN is_up = 0 THEN 1 ELSE 0 END) as down_checks
            FROM uptime_logs
            WHERE project_id = ? 
            AND checked_at BETWEEN ? AND ?
        ");
        $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        $data = $stmt->fetch();
        
        if ($data['total_checks'] == 0) {
            return null;
        }
        
        $uptimePercentage = (($data['total_checks'] - $data['down_checks']) / $data['total_checks']) * 100;
        
        // Calculate downtime duration (assuming 5-minute check intervals)
        $downtimeMinutes = $data['down_checks'] * 5;
        $downtimeFormatted = formatDowntime($downtimeMinutes);
        
        // Get incident count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as incident_count
            FROM incidents
            WHERE project_id = ? 
            AND started_at BETWEEN ? AND ?
        ");
        $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        $incidents = $stmt->fetch();
        
        return [
            'percentage' => round($uptimePercentage, 3),
            'downtime' => $downtimeFormatted,
            'incidents' => $incidents['incident_count'],
            'checks' => $data['total_checks']
        ];
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Format downtime minutes into human-readable format
 */
function formatDowntime($minutes) {
    if ($minutes < 60) {
        return $minutes . 'm';
    } elseif ($minutes < 1440) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours . 'h ' . ($mins > 0 ? $mins . 'm' : '');
    } else {
        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        return $days . 'd ' . ($hours > 0 ? $hours . 'h' : '');
    }
}

/**
 * Get HTTP status code distribution
 */
function getStatusCodeDistribution($pdo, $projectId, $days = 7) {
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify("-{$days} days");
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                status_code,
                SUM(count) as total_count
            FROM http_status_logs
            WHERE project_id = ? 
            AND checked_at BETWEEN ? AND ?
            GROUP BY status_code
            ORDER BY status_code
        ");
        $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        
        $distribution = [
            '2xx' => 0,
            '3xx' => 0,
            '4xx' => 0,
            '5xx' => 0
        ];
        
        $total = 0;
        while ($row = $stmt->fetch()) {
            $total += $row['total_count'];
            $category = floor($row['status_code'] / 100) . 'xx';
            if (isset($distribution[$category])) {
                $distribution[$category] += $row['total_count'];
            }
        }
        
        // Convert to percentages
        if ($total > 0) {
            foreach ($distribution as $key => $value) {
                $distribution[$key] = [
                    'count' => $value,
                    'percentage' => round(($value / $total) * 100, 1)
                ];
            }
        }
        
        return ['distribution' => $distribution, 'total' => $total];
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get SSL certificate information
 */
function getSSLInfo($pdo, $projectId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM ssl_certificates 
            WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get response time statistics
 */
function getResponseTimeStats($pdo, $projectId, $hours = 24) {
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify("-{$hours} hours");
    
    try {
        // Get hourly averages
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(measured_at, '%Y-%m-%d %H:00:00') as hour,
                AVG(response_time) as avg_time,
                MIN(response_time) as min_time,
                MAX(response_time) as max_time
            FROM response_times
            WHERE project_id = ? 
            AND measured_at BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(measured_at, '%Y-%m-%d %H:00:00')
            ORDER BY hour
        ");
        $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Generate mock monitoring data for a project
 */
function generateMockData($pdo, $projectId) {
    try {
        $now = new DateTime();
        
        // Generate uptime logs for the last 30 days (every 5 minutes)
        for ($days = 30; $days >= 0; $days--) {
            for ($hours = 0; $hours < 24; $hours++) {
                for ($mins = 0; $mins < 60; $mins += 5) {
                    $checkTime = clone $now;
                    $checkTime->modify("-{$days} days -{$hours} hours -{$mins} minutes");
                    
                    // 99.5% uptime simulation
                    $isUp = rand(1, 1000) > 5;
                    $responseTime = $isUp ? rand(50, 300) : null;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO uptime_logs (project_id, is_up, response_time, checked_at)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$projectId, $isUp, $responseTime, $checkTime->format('Y-m-d H:i:s')]);
                }
            }
        }
        
        // Generate HTTP status codes for the last 7 days
        $statusCodes = [200, 201, 301, 302, 400, 401, 403, 404, 500, 502, 503];
        $weights = [70, 5, 3, 2, 2, 1, 1, 5, 2, 1, 1]; // Weighted distribution
        
        for ($days = 7; $days >= 0; $days--) {
            $checkTime = clone $now;
            $checkTime->modify("-{$days} days");
            
            foreach ($statusCodes as $index => $code) {
                $count = rand(0, $weights[$index] * 10);
                if ($count > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO http_status_logs (project_id, status_code, count, checked_at)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$projectId, $code, $count, $checkTime->format('Y-m-d H:i:s')]);
                }
            }
        }
        
        // Generate SSL certificate info
        $sslExpiry = clone $now;
        $sslExpiry->modify('+' . rand(30, 365) . ' days');
        $domainExpiry = clone $now;
        $domainExpiry->modify('+' . rand(180, 730) . ' days');
        
        $issuers = ['Let\'s Encrypt', 'Google Trust Services', 'DigiCert', 'Sectigo'];
        $issuer = $issuers[array_rand($issuers)];
        
        $stmt = $pdo->prepare("
            INSERT INTO ssl_certificates (project_id, issuer, expiry_date, domain_expiry_date)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                issuer = VALUES(issuer),
                expiry_date = VALUES(expiry_date),
                domain_expiry_date = VALUES(domain_expiry_date)
        ");
        $stmt->execute([$projectId, $issuer, $sslExpiry->format('Y-m-d'), $domainExpiry->format('Y-m-d')]);
        
        // Generate response time data for the last 24 hours
        for ($hours = 24; $hours >= 0; $hours--) {
            for ($mins = 0; $mins < 60; $mins += 10) {
                $measureTime = clone $now;
                $measureTime->modify("-{$hours} hours -{$mins} minutes");
                
                // Simulate varying response times
                $baseTime = 100;
                if ($hours >= 9 && $hours <= 17) {
                    $baseTime = 150; // Higher during business hours
                }
                $responseTime = $baseTime + rand(-50, 100);
                
                $stmt = $pdo->prepare("
                    INSERT INTO response_times (project_id, response_time, measured_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$projectId, max(20, $responseTime), $measureTime->format('Y-m-d H:i:s')]);
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if project has monitoring data
 */
function hasMonitoringData($pdo, $projectId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM uptime_logs WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}
?>