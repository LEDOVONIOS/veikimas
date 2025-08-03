<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

$projectId = (int) getGet('id');
if (!$projectId) {
    redirect('dashboard.php');
}

// Get project details
if ($auth->isAdmin()) {
    $project = $db->fetchOne(
        "SELECT p.*, u.username, u.email as user_email 
         FROM " . DB_PREFIX . "projects p 
         LEFT JOIN " . DB_PREFIX . "users u ON p.user_id = u.id 
         WHERE p.id = ?",
        [$projectId]
    );
} else {
    $project = $db->fetchOne(
        "SELECT * FROM " . DB_PREFIX . "projects 
         WHERE id = ? AND user_id = ?",
        [$projectId, $auth->getUserId()]
    );
}

if (!$project) {
    redirect('dashboard.php');
}

$pageTitle = $project['name'];
$monitor = new Monitor();

// Get date range
$dateRange = getGet('range', 'last_24_hours');
$customStart = getGet('start');
$customEnd = getGet('end');

// Calculate date range
$ranges = getDateRangePresets();
if ($dateRange === 'custom' && $customStart && $customEnd) {
    $startDate = $customStart . ' 00:00:00';
    $endDate = $customEnd . ' 23:59:59';
} elseif (isset($ranges[$dateRange])) {
    $startDate = $ranges[$dateRange]['start'];
    $endDate = $ranges[$dateRange]['end'];
} else {
    // Default to last 24 hours
    $startDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $endDate = date('Y-m-d H:i:s');
}

// Get uptime statistics
$uptimeStats = [
    '24h' => $monitor->getUptimePercentage($projectId, 24),
    '7d' => $monitor->getUptimePercentage($projectId, 24 * 7),
    '30d' => $monitor->getUptimePercentage($projectId, 24 * 30),
    '365d' => $monitor->getUptimePercentage($projectId, 24 * 365)
];

// Get response time statistics
$responseStats = $monitor->getResponseTimeStats($projectId, $startDate);

// Get monitor logs for charts
$monitorLogs = $monitor->getMonitorLogs($projectId, $startDate, 
    (strtotime($endDate) - strtotime($startDate)) > 86400 ? 'day' : 'hour'
);

// Get SSL data
$sslData = $db->fetchOne(
    "SELECT * FROM " . DB_PREFIX . "ssl_data WHERE project_id = ?",
    [$projectId]
);

// Get domain data
$domainData = $db->fetchOne(
    "SELECT * FROM " . DB_PREFIX . "domain_data WHERE project_id = ?",
    [$projectId]
);

// Get recent incidents
$incidents = $db->fetchAllArray(
    "SELECT * FROM " . DB_PREFIX . "incident_logs 
     WHERE project_id = ? 
     ORDER BY started_at DESC 
     LIMIT " . MAX_INCIDENTS_DISPLAY,
    [$projectId]
);

// Calculate current uptime duration
$uptimeDuration = '';
if ($project['last_status_change']) {
    $duration = time() - strtotime($project['last_status_change']);
    $uptimeDuration = formatDuration($duration);
}

// Prepare chart data
$chartLabels = [];
$uptimeData = [];
$responseTimeData = [];

foreach ($monitorLogs as $log) {
    $chartLabels[] = date('M d H:i', strtotime($log['period']));
    $uptimeData[] = round($log['uptime_percentage'], 2);
    $responseTimeData[] = round($log['avg_response_time'], 2);
}

$additionalCSS = '
<style>
.stat-card {
    text-align: center;
    padding: 1.5rem;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 1rem;
}
.stat-card .value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: #000;
}
.stat-card .label {
    color: #666;
    font-size: 0.9rem;
}
.incident-timeline {
    position: relative;
    padding-left: 30px;
}
.incident-timeline::before {
    content: "";
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}
.incident-item {
    position: relative;
    margin-bottom: 1.5rem;
}
.incident-item::before {
    content: "";
    position: absolute;
    left: -24px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #dc3545;
}
.incident-item.resolved::before {
    background: #28a745;
}
</style>';

$additionalJS = '
<script>
// Response Time Chart
const ctxResponse = document.getElementById("responseTimeChart").getContext("2d");
const responseTimeChart = new Chart(ctxResponse, {
    type: "line",
    data: {
        labels: ' . json_encode($chartLabels) . ',
        datasets: [{
            label: "Response Time (ms)",
            data: ' . json_encode($responseTimeData) . ',
            borderColor: "rgb(75, 192, 192)",
            backgroundColor: "rgba(75, 192, 192, 0.1)",
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: "Response Time (ms)"
                }
            }
        }
    }
});

// Uptime Chart
const ctxUptime = document.getElementById("uptimeChart").getContext("2d");
const uptimeChart = new Chart(ctxUptime, {
    type: "line",
    data: {
        labels: ' . json_encode($chartLabels) . ',
        datasets: [{
            label: "Uptime %",
            data: ' . json_encode($uptimeData) . ',
            borderColor: "rgb(102, 126, 234)",
            backgroundColor: "rgba(102, 126, 234, 0.1)",
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: "Uptime %"
                }
            }
        }
    }
});

// Date range picker
$(function() {
    $("#daterange").daterangepicker({
        startDate: moment("' . $startDate . '"),
        endDate: moment("' . $endDate . '"),
        ranges: {
            "Last 24 Hours": [moment().subtract(24, "hours"), moment()],
            "Last 7 Days": [moment().subtract(6, "days"), moment()],
            "Last 30 Days": [moment().subtract(29, "days"), moment()],
            "Last 365 Days": [moment().subtract(364, "days"), moment()],
            "This Month": [moment().startOf("month"), moment().endOf("month")],
            "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")]
        },
        locale: {
            format: "YYYY-MM-DD"
        }
    }, function(start, end, label) {
        window.location.href = "project.php?id=' . $projectId . '&range=custom&start=" + start.format("YYYY-MM-DD") + "&end=" + end.format("YYYY-MM-DD");
    });
});

// Refresh page every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>';

include 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0">
            <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($project['name']); ?>
            <?php echo getStatusBadge($project['current_status']); ?>
        </h1>
        <p class="text-muted mb-0">
            <i class="fas fa-link"></i> 
            <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank">
                <?php echo htmlspecialchars($project['url']); ?>
            </a>
        </p>
    </div>
    <div class="col-md-4 text-md-right">
        <div class="btn-group" role="group">
            <a href="project-edit.php?id=<?php echo $projectId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button type="button" class="btn btn-info" id="daterange">
                <i class="fas fa-calendar"></i> 
                <span><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Current Status -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="value <?php echo $project['current_status'] === 'up' ? 'text-success' : 'text-danger'; ?>">
                <?php echo $project['current_status'] === 'up' ? 'Online' : 'Offline'; ?>
            </div>
            <div class="label">Current Status</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="value"><?php echo $uptimeDuration ?: '-'; ?></div>
            <div class="label">Uptime Duration</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="value">
                <?php echo $project['last_check'] ? timeAgo($project['last_check']) : 'Never'; ?>
            </div>
            <div class="label">Last Checked</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="value"><?php echo parseInterval($project['check_interval']); ?></div>
            <div class="label">Check Interval</div>
        </div>
    </div>
</div>

<!-- Uptime Statistics -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-line"></i> Uptime Overview
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <h4 class="<?php echo getUptimeColorClass($uptimeStats['24h']); ?>">
                    <?php echo number_format($uptimeStats['24h'], 2); ?>%
                </h4>
                <p class="text-muted mb-0">Last 24 hours</p>
            </div>
            <div class="col-md-3">
                <h4 class="<?php echo getUptimeColorClass($uptimeStats['7d']); ?>">
                    <?php echo number_format($uptimeStats['7d'], 2); ?>%
                </h4>
                <p class="text-muted mb-0">Last 7 days</p>
            </div>
            <div class="col-md-3">
                <h4 class="<?php echo getUptimeColorClass($uptimeStats['30d']); ?>">
                    <?php echo number_format($uptimeStats['30d'], 2); ?>%
                </h4>
                <p class="text-muted mb-0">Last 30 days</p>
            </div>
            <div class="col-md-3">
                <h4 class="<?php echo getUptimeColorClass($uptimeStats['365d']); ?>">
                    <?php echo number_format($uptimeStats['365d'], 2); ?>%
                </h4>
                <p class="text-muted mb-0">Last 365 days</p>
            </div>
        </div>
        
        <hr>
        
        <div class="chart-container" style="height: 200px;">
            <canvas id="uptimeChart"></canvas>
        </div>
    </div>
</div>

<!-- Response Time -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-tachometer-alt"></i> Response Time
    </div>
    <div class="card-body">
        <?php if ($responseStats && $responseStats['avg_time']): ?>
        <div class="row text-center mb-3">
            <div class="col-md-4">
                <h4 class="<?php echo getResponseTimeColorClass($responseStats['avg_time']); ?>">
                    <?php echo round($responseStats['avg_time']); ?>ms
                </h4>
                <p class="text-muted mb-0">Average</p>
            </div>
            <div class="col-md-4">
                <h4 class="text-success">
                    <?php echo round($responseStats['min_time']); ?>ms
                </h4>
                <p class="text-muted mb-0">Minimum</p>
            </div>
            <div class="col-md-4">
                <h4 class="text-warning">
                    <?php echo round($responseStats['max_time']); ?>ms
                </h4>
                <p class="text-muted mb-0">Maximum</p>
            </div>
        </div>
        <?php else: ?>
        <p class="text-center text-muted">No response time data available for the selected period.</p>
        <?php endif; ?>
        
        <div class="chart-container" style="height: 200px;">
            <canvas id="responseTimeChart"></canvas>
        </div>
    </div>
</div>

<div class="row">
    <!-- SSL Information -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-lock"></i> SSL Certificate
            </div>
            <div class="card-body">
                <?php if ($sslData && $sslData['is_valid']): ?>
                    <div class="alert alert-<?php echo $sslData['days_remaining'] <= SSL_WARNING_DAYS ? 'warning' : 'success'; ?>">
                        <i class="fas fa-check-circle"></i> Valid SSL Certificate
                    </div>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Issuer:</strong></td>
                            <td><?php echo htmlspecialchars($sslData['issuer']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Subject:</strong></td>
                            <td><?php echo htmlspecialchars($sslData['subject']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Valid From:</strong></td>
                            <td><?php echo date('M d, Y', strtotime($sslData['valid_from'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Valid To:</strong></td>
                            <td><?php echo date('M d, Y', strtotime($sslData['valid_to'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Days Remaining:</strong></td>
                            <td>
                                <span class="<?php echo $sslData['days_remaining'] <= SSL_WARNING_DAYS ? 'text-warning font-weight-bold' : 'text-success'; ?>">
                                    <?php echo $sslData['days_remaining']; ?> days
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if ($sslData['days_remaining'] <= SSL_WARNING_DAYS): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        SSL certificate expires in <?php echo $sslData['days_remaining']; ?> days!
                    </div>
                    <?php endif; ?>
                <?php elseif ($sslData && $sslData['error_message']): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i> SSL Check Failed
                    </div>
                    <p class="text-muted"><?php echo htmlspecialchars($sslData['error_message']); ?></p>
                <?php elseif (strpos($project['url'], 'https://') !== 0): ?>
                    <p class="text-muted">This website does not use HTTPS.</p>
                <?php else: ?>
                    <p class="text-muted">No SSL data available yet.</p>
                <?php endif; ?>
                
                <?php if ($sslData): ?>
                <small class="text-muted">
                    Last checked: <?php echo timeAgo($sslData['last_check']); ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Domain Information -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-globe"></i> Domain Information
            </div>
            <div class="card-body">
                <?php if ($domainData && $domainData['expiry_date']): ?>
                    <div class="alert alert-<?php echo $domainData['days_remaining'] <= DOMAIN_WARNING_DAYS ? 'warning' : 'success'; ?>">
                        <i class="fas fa-check-circle"></i> Active Domain
                    </div>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Domain:</strong></td>
                            <td><?php echo htmlspecialchars($domainData['domain']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Registrar:</strong></td>
                            <td><?php echo htmlspecialchars($domainData['registrar'] ?: 'Unknown'); ?></td>
                        </tr>
                        <?php if ($domainData['created_date']): ?>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td><?php echo date('M d, Y', strtotime($domainData['created_date'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Expires:</strong></td>
                            <td><?php echo date('M d, Y', strtotime($domainData['expiry_date'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Days Remaining:</strong></td>
                            <td>
                                <span class="<?php echo $domainData['days_remaining'] <= DOMAIN_WARNING_DAYS ? 'text-warning font-weight-bold' : 'text-success'; ?>">
                                    <?php echo $domainData['days_remaining']; ?> days
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if ($domainData['days_remaining'] <= DOMAIN_WARNING_DAYS): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Domain expires in <?php echo $domainData['days_remaining']; ?> days!
                    </div>
                    <?php endif; ?>
                <?php elseif ($domainData && $domainData['error_message']): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i> Domain Check Failed
                    </div>
                    <p class="text-muted"><?php echo htmlspecialchars($domainData['error_message']); ?></p>
                <?php else: ?>
                    <p class="text-muted">No domain data available yet.</p>
                <?php endif; ?>
                
                <?php if ($domainData): ?>
                <small class="text-muted">
                    Last checked: <?php echo timeAgo($domainData['last_check']); ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Incident History -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-history"></i> Incident History
    </div>
    <div class="card-body">
        <?php if (empty($incidents)): ?>
            <p class="text-center text-muted my-4">
                <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                No incidents recorded. Great job!
            </p>
        <?php else: ?>
            <div class="incident-timeline">
                <?php foreach ($incidents as $incident): ?>
                <div class="incident-item <?php echo $incident['ended_at'] ? 'resolved' : ''; ?>">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>
                                <?php if ($incident['ended_at']): ?>
                                    Downtime: <?php echo formatDuration($incident['duration']); ?>
                                <?php else: ?>
                                    <span class="text-danger">Ongoing Incident</span>
                                <?php endif; ?>
                            </strong>
                            <br>
                            <small class="text-muted">
                                Started: <?php echo date('M d, Y H:i', strtotime($incident['started_at'])); ?>
                                <?php if ($incident['ended_at']): ?>
                                    | Ended: <?php echo date('M d, Y H:i', strtotime($incident['ended_at'])); ?>
                                <?php endif; ?>
                            </small>
                            <?php if ($incident['reason']): ?>
                            <br>
                            <small class="text-muted">
                                Reason: <?php echo htmlspecialchars($incident['reason']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($incident['ended_at']): ?>
                                <span class="badge badge-success">Resolved</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Ongoing</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>