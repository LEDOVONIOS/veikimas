<?php
require_once 'db.php';
require_once 'includes/monitoring_functions.php';
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
    
    // Get monitoring data
    $statusCodeData = getStatusCodeDistribution($pdo, $projectId, 7);
    $uptime7Days = calculateUptime($pdo, $projectId, 7);
    $uptime30Days = calculateUptime($pdo, $projectId, 30);
    $uptime365Days = calculateUptime($pdo, $projectId, 365);
    $sslInfo = getSSLInfo($pdo, $projectId);
    $responseTimeData = getResponseTimeStats($pdo, $projectId, 24);
    
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
                        Operational
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
                
                <?php if (!empty($project['server_location'])): ?>
                    <div class="info-item">
                        <strong>Location:</strong> 
                        <?php echo htmlspecialchars($project['server_location']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <strong>Created:</strong> 
                    <?php echo date('F d, Y g:i A', strtotime($project['date_created'])); ?>
                </div>
                
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
                <div class="no-data">No data available</div>
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
                        <div class="no-data">No data available</div>
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
                        <div class="no-data">No data available</div>
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
                        <div class="no-data">No data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Domain & SSL -->
        <div class="monitoring-section">
            <h2>Domain & SSL</h2>
            <?php if ($sslInfo): ?>
                <div class="ssl-info-grid">
                    <div class="ssl-info-item">
                        <strong>SSL Certificate Expiry:</strong>
                        <span><?php echo date('F d, Y', strtotime($sslInfo['expiry_date'])); ?></span>
                        <?php
                        $daysUntilSSLExpiry = (new DateTime($sslInfo['expiry_date']))->diff(new DateTime())->days;
                        if ($daysUntilSSLExpiry < 30): ?>
                            <span class="status-badge status-critical">Expires soon</span>
                        <?php endif; ?>
                    </div>
                    <div class="ssl-info-item">
                        <strong>Domain Expiry:</strong>
                        <span><?php echo date('F d, Y', strtotime($sslInfo['domain_expiry_date'])); ?></span>
                    </div>
                    <div class="ssl-info-item">
                        <strong>Issuer:</strong>
                        <span><?php echo htmlspecialchars($sslInfo['issuer']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data">Loading...</div>
            <?php endif; ?>
        </div>
        
        <!-- Response Time Chart -->
        <div class="monitoring-section">
            <h2>Response Time (Last 24 Hours)</h2>
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
                        <span class="stat-label">Average:</span>
                        <span class="stat-value"><?php echo $avgResponseTime; ?>ms</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Minimum:</span>
                        <span class="stat-value"><?php echo $minResponseTime; ?>ms</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Maximum:</span>
                        <span class="stat-value"><?php echo $maxResponseTime; ?>ms</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data">Failed to load chart</div>
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
                labels: <?php echo json_encode(array_map(function($d) {
                    return date('H:i', strtotime($d['hour']));
                }, $responseTimeData)); ?>,
                datasets: [{
                    label: 'Average Response Time',
                    data: <?php echo json_encode(array_column($responseTimeData, 'avg_time')); ?>,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Max Response Time',
                    data: <?php echo json_encode(array_column($responseTimeData, 'max_time')); ?>,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3,
                    fill: false,
                    borderDash: [5, 5]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Math.round(context.parsed.y) + 'ms';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + 'ms';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>