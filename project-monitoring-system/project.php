<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
require_once 'includes/notification_handler.php';
requireLogin();

// Get project ID and time filter
$projectId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$timeFilter = $_GET['time'] ?? '7d';

if (!$projectId) {
    header("Location: dashboard.php");
    exit();
}

// Get project details
try {
    $stmt = $pdo->prepare("
        SELECT * FROM projects 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Check if monitoring data exists, if not, generate mock data
    if (!hasMonitoringData($pdo, $projectId)) {
        generateMockData($pdo, $projectId);
    }
    
    // Get response time range from request
    $responseTimeRange = isset($_GET['response_range']) ? intval($_GET['response_range']) : 24;
    
    // Get monitoring data
    $statusCodeData = getStatusCodeDistribution($pdo, $projectId, 7);
    $uptime7Days = calculateUptime($pdo, $projectId, 7);
    $uptime30Days = calculateUptime($pdo, $projectId, 30);
    $uptime365Days = calculateUptime($pdo, $projectId, 365);
    $sslInfo = getSSLInfo($pdo, $projectId);
    $responseTimeData = getResponseTimeStats($pdo, $projectId, $responseTimeRange);
    $cronJobs = getCronJobs($pdo, $projectId);
    $lastChecked = getLastChecked($pdo, $projectId);
    $unreadNotifications = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
    
    // Get incidents with time filter
    $incidentDays = match($timeFilter) {
        '24h' => 1,
        '7d' => 7,
        '30d' => 30,
        default => 7
    };
    
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify("-{$incidentDays} days");
    
    $stmt = $pdo->prepare("
        SELECT * FROM incidents 
        WHERE project_id = ? AND started_at BETWEEN ? AND ?
        ORDER BY started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$projectId, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
    $incidents = $stmt->fetchAll();
    
} catch (PDOException $e) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['project_name']); ?> - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Project Monitor</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Home</a></li>
                <li><a href="add_project.php">Add Project</a></li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <?php if ($unreadNotifications > 0): ?>
                        <a href="notifications.php" class="notification-badge">
                            🔔 <span class="badge-count"><?php echo $unreadNotifications; ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">← Back to Projects</a>
        </div>
        
        <div class="project-detail">
            <div class="project-detail-header">
                <h1><?php echo htmlspecialchars($project['project_name']); ?></h1>
                <?php
                $openIncidents = array_filter($incidents, function($inc) {
                    return $inc['status'] === 'Open';
                });
                ?>
                <?php if (count($openIncidents) > 0): ?>
                    <span class="status-badge status-critical large">
                        <?php echo count($openIncidents); ?> Open Incident<?php echo count($openIncidents) > 1 ? 's' : ''; ?>
                    </span>
                <?php else: ?>
                    <span class="status-badge status-operational large">
                        Up
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="project-info">
                <?php if (!empty($project['project_url'])): ?>
                    <div class="info-item">
                        <strong>URL:</strong> 
                        <a href="<?php echo htmlspecialchars($project['project_url']); ?>" 
                           target="_blank" rel="noopener">
                            <?php echo htmlspecialchars($project['project_url']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <strong>Status:</strong> 
                    <span class="status-text <?php echo count($openIncidents) > 0 ? 'status-down' : 'status-up'; ?>">
                        <?php echo count($openIncidents) > 0 ? 'Down' : 'Operational'; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <strong>Created:</strong> 
                    <?php echo date('F d, Y g:i A', strtotime($project['date_created'])); ?>
                </div>
                
                <div class="info-item">
                    <strong>Last Checked:</strong> 
                    <span class="last-checked">
                        <?php if ($lastChecked): ?>
                            <?php echo date('F d, Y g:i:s A', strtotime($lastChecked)); ?>
                        <?php else: ?>
                            Never
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <strong>Geographic Region:</strong> 
                    <span class="region-badge">
                        📍 <?php echo htmlspecialchars($project['monitoring_region'] ?? 'North America'); ?>
                    </span>
                </div>
                
                <?php if (!empty($project['server_location'])): ?>
                    <div class="info-item">
                        <strong>Server Location:</strong> 
                        <?php echo htmlspecialchars($project['server_location']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($project['description'])): ?>
                    <div class="info-item">
                        <strong>Description:</strong>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- HTTP Status Code Summary -->
        <div class="monitoring-section">
            <h2>HTTP Status Code Summary</h2>
            <?php if ($statusCodeData && $statusCodeData['total'] > 0): ?>
                <div class="status-code-grid">
                    <?php foreach ($statusCodeData['distribution'] as $code => $data): ?>
                        <div class="status-code-item <?php echo 'status-' . substr($code, 0, 1); ?>xx">
                            <div class="status-code-header">
                                <span class="code"><?php echo $code; ?></span>
                                <span class="percentage"><?php echo $data['percentage']; ?>%</span>
                            </div>
                            <div class="status-code-bar">
                                <div class="status-code-fill" style="width: <?php echo $data['percentage']; ?>%"></div>
                            </div>
                            <div class="status-code-label">
                                <?php
                                echo match($code) {
                                    '2xx' => 'Success',
                                    '3xx' => 'Redirects',
                                    '4xx' => 'Client errors',
                                    '5xx' => 'Server errors',
                                    default => ''
                                };
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="status-code-grid">
                    <div class="status-code-item status-2xx">
                        <div class="status-code-header">
                            <span class="code">2xx</span>
                            <span class="percentage">100%</span>
                        </div>
                        <div class="status-code-bar">
                            <div class="status-code-fill" style="width: 100%"></div>
                        </div>
                        <div class="status-code-label">Success</div>
                    </div>
                    <div class="status-code-item status-3xx">
                        <div class="status-code-header">
                            <span class="code">3xx</span>
                            <span class="percentage">0%</span>
                        </div>
                        <div class="status-code-bar">
                            <div class="status-code-fill" style="width: 0%"></div>
                        </div>
                        <div class="status-code-label">Redirects</div>
                    </div>
                    <div class="status-code-item status-4xx">
                        <div class="status-code-header">
                            <span class="code">4xx</span>
                            <span class="percentage">0%</span>
                        </div>
                        <div class="status-code-bar">
                            <div class="status-code-fill" style="width: 0%"></div>
                        </div>
                        <div class="status-code-label">Client errors</div>
                    </div>
                    <div class="status-code-item status-5xx">
                        <div class="status-code-header">
                            <span class="code">5xx</span>
                            <span class="percentage">0%</span>
                        </div>
                        <div class="status-code-bar">
                            <div class="status-code-fill" style="width: 0%"></div>
                        </div>
                        <div class="status-code-label">Server errors</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Uptime Statistics -->
        <div class="monitoring-section">
            <h2>Uptime Statistics</h2>
            <div class="uptime-grid">
                <div class="uptime-card">
                    <h3>Last 7 days</h3>
                    <?php if ($uptime7Days): ?>
                        <div class="uptime-percentage"><?php echo $uptime7Days['percentage']; ?>%</div>
                        <div class="uptime-details">
                            <span><?php echo $uptime7Days['downtime']; ?> down</span>
                            <span><?php echo $uptime7Days['incidents']; ?> incident<?php echo $uptime7Days['incidents'] !== 1 ? 's' : ''; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="uptime-percentage">100%</div>
                        <div class="uptime-details">
                            <span>0m down</span>
                            <span>0 incidents</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="uptime-card">
                    <h3>Last 30 days</h3>
                    <?php if ($uptime30Days): ?>
                        <div class="uptime-percentage"><?php echo $uptime30Days['percentage']; ?>%</div>
                        <div class="uptime-details">
                            <span><?php echo $uptime30Days['downtime']; ?> down</span>
                            <span><?php echo $uptime30Days['incidents']; ?> incident<?php echo $uptime30Days['incidents'] !== 1 ? 's' : ''; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="uptime-percentage">100%</div>
                        <div class="uptime-details">
                            <span>0m down</span>
                            <span>0 incidents</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="uptime-card">
                    <h3>Last 365 days</h3>
                    <?php if ($uptime365Days): ?>
                        <div class="uptime-percentage"><?php echo $uptime365Days['percentage']; ?>%</div>
                        <div class="uptime-details">
                            <span><?php echo $uptime365Days['downtime']; ?> down</span>
                            <span><?php echo $uptime365Days['incidents']; ?> incident<?php echo $uptime365Days['incidents'] !== 1 ? 's' : ''; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="uptime-percentage">100%</div>
                        <div class="uptime-details">
                            <span>0m down</span>
                            <span>0 incidents</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Domain & SSL -->
        <div class="monitoring-section">
            <h2>Domain & SSL Information</h2>
            <?php if ($sslInfo): ?>
                <div class="ssl-info-grid">
                    <div class="ssl-info-item">
                        <strong>SSL Certificate:</strong>
                        <div class="ssl-details">
                            <span class="ssl-issuer">Issued by <?php echo htmlspecialchars($sslInfo['issuer']); ?></span>
                            <span class="ssl-expiry">
                                Expires: <?php echo date('F d, Y', strtotime($sslInfo['expiry_date'])); ?>
                                <?php
                                $daysUntilSSLExpiry = (new DateTime($sslInfo['expiry_date']))->diff(new DateTime())->days;
                                if ($daysUntilSSLExpiry < 30): ?>
                                    <span class="status-badge status-warning">⚠️ Expires in <?php echo $daysUntilSSLExpiry; ?> days</span>
                                <?php else: ?>
                                    <span class="status-badge status-success">✓ Valid</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="ssl-info-item">
                        <strong>Domain Registration:</strong>
                        <div class="domain-details">
                            <span class="domain-expiry">
                                Expires: <?php echo date('F d, Y', strtotime($sslInfo['domain_expiry_date'])); ?>
                                <?php
                                $daysUntilDomainExpiry = (new DateTime($sslInfo['domain_expiry_date']))->diff(new DateTime())->days;
                                if ($daysUntilDomainExpiry < 60): ?>
                                    <span class="status-badge status-warning">⚠️ Expires in <?php echo $daysUntilDomainExpiry; ?> days</span>
                                <?php else: ?>
                                    <span class="status-badge status-success">✓ Active</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="ssl-info-grid">
                    <div class="ssl-info-item">
                        <strong>SSL Certificate:</strong>
                        <div class="ssl-details">
                            <span class="ssl-issuer">No SSL information available</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Website Response Time -->
        <div class="monitoring-section">
            <h2>Website Response Time</h2>
            <div class="response-time-controls">
                <select id="responseTimeRange" class="time-range-selector">
                    <option value="1">Last hour</option>
                    <option value="24" selected>Last 24 hours</option>
                    <option value="168">Last 7 days</option>
                    <option value="720">Last 30 days</option>
                </select>
            </div>
            <?php if ($responseTimeData && count($responseTimeData) > 0): ?>
                <div class="chart-container">
                    <canvas id="responseTimeChart"></canvas>
                </div>
                <div class="response-time-stats">
                    <?php
                    $allResponseTimes = array_column($responseTimeData, 'avg_time');
                    $avgResponseTime = count($allResponseTimes) > 0 ? round(array_sum($allResponseTimes) / count($allResponseTimes)) : 0;
                    $minResponseTime = count($allResponseTimes) > 0 ? round(min($allResponseTimes)) : 0;
                    $maxResponseTime = count($allResponseTimes) > 0 ? round(max($allResponseTimes)) : 0;
                    ?>
                    <div class="stat-item">
                        <div class="stat-icon">↻</div>
                        <span class="stat-label">Average</span>
                        <span class="stat-value"><?php echo $avgResponseTime; ?> ms</span>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon stat-icon-min">↓</div>
                        <span class="stat-label">Minimum</span>
                        <span class="stat-value"><?php echo $minResponseTime; ?> ms</span>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon stat-icon-max">↑</div>
                        <span class="stat-label">Maximum</span>
                        <span class="stat-value"><?php echo $maxResponseTime; ?> ms</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No response time data available yet.</p>
                    <p class="text-muted">Response time monitoring will begin shortly.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cron Job Monitoring -->
        <div class="monitoring-section">
            <h2>Cron Job Monitoring</h2>
            <?php if (!empty($cronJobs)): ?>
                <div class="cron-jobs-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Job Name</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Last Run</th>
                                <th>Next Run</th>
                                <th>Duration</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cronJobs as $job): ?>
                                <tr>
                                    <td class="job-name"><?php echo htmlspecialchars($job['job_name']); ?></td>
                                    <td class="job-schedule">
                                        <code><?php echo htmlspecialchars($job['schedule']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($job['status'] === 'success'): ?>
                                            <span class="status-badge status-success">✓ Success</span>
                                        <?php elseif ($job['status'] === 'failed'): ?>
                                            <span class="status-badge status-critical">✗ Failed</span>
                                        <?php elseif ($job['status'] === 'running'): ?>
                                            <span class="status-badge status-warning">⟳ Running</span>
                                        <?php else: ?>
                                            <span class="status-badge status-neutral">⏸ Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($job['last_run']): ?>
                                            <?php echo date('M d, g:i A', strtotime($job['last_run'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($job['next_run']): ?>
                                            <?php echo date('M d, g:i A', strtotime($job['next_run'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($job['last_duration']): ?>
                                            <?php echo $job['last_duration']; ?>s
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($job['error_message']): ?>
                                            <span class="error-message" title="<?php echo htmlspecialchars($job['error_message']); ?>">
                                                <?php echo htmlspecialchars(substr($job['error_message'], 0, 30) . '...'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No cron jobs configured for this project.</p>
                    <p class="text-muted">Cron jobs will appear here once configured.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Incidents -->
        <div class="incidents-section">
            <div class="section-header">
                <h2>Recent Incidents</h2>
                <div class="incident-controls">
                    <div class="time-filter">
                        <a href="?id=<?php echo $projectId; ?>&time=24h" class="<?php echo $timeFilter === '24h' ? 'active' : ''; ?>">24h</a>
                        <a href="?id=<?php echo $projectId; ?>&time=7d" class="<?php echo $timeFilter === '7d' ? 'active' : ''; ?>">7d</a>
                        <a href="?id=<?php echo $projectId; ?>&time=30d" class="<?php echo $timeFilter === '30d' ? 'active' : ''; ?>">30d</a>
                    </div>
                    <a href="add_incident.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                        <span class="btn-icon">+</span> Add Incident
                    </a>
                </div>
            </div>
            
            <?php if (empty($incidents)): ?>
                <div class="empty-state">
                    <p>No incidents in this period 🎉</p>
                </div>
            <?php else: ?>
                <div class="incidents-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Started At</th>
                                <th>Duration</th>
                                <th>Root Cause</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td>
                                        <?php if ($incident['status'] === 'Open'): ?>
                                            <span class="status-indicator status-open"></span>
                                            <span class="status-text">Open</span>
                                        <?php else: ?>
                                            <span class="status-indicator status-resolved"></span>
                                            <span class="status-text">Resolved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($incident['started_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($incident['duration'] ?: 'Ongoing'); ?></td>
                                    <td class="root-cause"><?php echo htmlspecialchars($incident['root_cause']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php if ($responseTimeData && count($responseTimeData) > 0): ?>
    <script>
        // Response Time Chart
        const ctx = document.getElementById('responseTimeChart').getContext('2d');
        const responseTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) use ($responseTimeRange) {
                    $date = new DateTime($d['hour']);
                    if ($responseTimeRange <= 24) {
                        return $date->format('H:i');
                    } elseif ($responseTimeRange <= 168) { // 7 days
                        return $date->format('M d, H:i');
                    } else {
                        return $date->format('M d');
                    }
                }, $responseTimeData)); ?>,
                datasets: [{
                    label: 'Response Time',
                    data: <?php echo json_encode(array_column($responseTimeData, 'avg_time')); ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#10B981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Response Time: ' + Math.round(context.parsed.y) + ' ms';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' ms';
                            },
                            stepSize: 450
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Handle time range selector
        document.getElementById('responseTimeRange').addEventListener('change', function() {
            const range = this.value;
            window.location.href = '?id=<?php echo $projectId; ?>&response_range=' + range;
        });
        
        // Set the current range in the selector
        <?php if (isset($_GET['response_range'])): ?>
        document.getElementById('responseTimeRange').value = '<?php echo $responseTimeRange; ?>';
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>