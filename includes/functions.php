<?php
/**
 * Common helper functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get time ago string
 */
function timeAgo($timestamp) {
    // Create DateTime object from timestamp (assumes UTC)
    $utcDate = new DateTime($timestamp, new DateTimeZone('UTC'));
    
    // Convert to local timezone
    $localDate = clone $utcDate;
    $localDate->setTimezone(new DateTimeZone(TIMEZONE));
    
    // Calculate the difference from current time
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));
    $diff = $now->getTimestamp() - $localDate->getTimestamp();
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = round($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = round($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = round($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Format duration in seconds
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return round($seconds / 60) . 'm';
    } elseif ($seconds < 86400) {
        return round($seconds / 3600, 1) . 'h';
    } else {
        return round($seconds / 86400, 1) . 'd';
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'up':
            return '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Online</span>';
        case 'down':
            return '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Offline</span>';
        case 'unknown':
            return '<span class="badge badge-secondary"><i class="fas fa-question-circle"></i> Unknown</span>';
        case 'active':
            return '<span class="badge badge-success">Active</span>';
        case 'paused':
            return '<span class="badge badge-warning">Paused</span>';
        case 'inactive':
            return '<span class="badge badge-secondary">Inactive</span>';
        default:
            return '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }
}

/**
 * Get uptime color class
 */
function getUptimeColorClass($percentage) {
    if ($percentage >= 99.9) {
        return 'text-success';
    } elseif ($percentage >= 98) {
        return 'text-warning';
    } else {
        return 'text-danger';
    }
}

/**
 * Get response time color class
 */
function getResponseTimeColorClass($ms) {
    if ($ms < 200) {
        return 'text-success';
    } elseif ($ms < 1000) {
        return 'text-warning';
    } else {
        return 'text-danger';
    }
}

/**
 * Show alert message
 */
function showAlert($message, $type = 'info') {
    $icon = '';
    switch ($type) {
        case 'success':
            $icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'danger':
            $icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        case 'warning':
            $icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        case 'info':
            $icon = '<i class="fas fa-info-circle"></i>';
            break;
    }
    
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
        ' . $icon . ' ' . $message . '
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>';
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST data with default value
 */
function getPost($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Get GET data with default value
 */
function getGet($key, $default = null) {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Check if AJAX request
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Log error message
 */
function logError($message) {
    if (LOG_ERRORS) {
        error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }
}

/**
 * Get chart data format
 */
function formatChartData($data, $labelKey, $valueKey) {
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $row[$labelKey];
        $values[] = $row[$valueKey];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

/**
 * Parse check interval to human readable
 */
function parseInterval($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return round($seconds / 60) . ' minutes';
    } else {
        return round($seconds / 3600) . ' hours';
    }
}

/**
 * Get date range presets
 */
function getDateRangePresets() {
    // Get start and end of day in UTC
    $getUtcDayBounds = function($daysAgo = 0) {
        $date = new DateTime("midnight -{$daysAgo} days", new DateTimeZone(TIMEZONE));
        $start = clone $date;
        $start->setTimezone(new DateTimeZone('UTC'));
        
        $end = clone $date;
        $end->setTime(23, 59, 59);
        $end->setTimezone(new DateTimeZone('UTC'));
        
        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s')
        ];
    };
    
    $today = $getUtcDayBounds(0);
    $yesterday = $getUtcDayBounds(1);
    
    return [
        'today' => [
            'label' => 'Today',
            'start' => $today['start'],
            'end' => $today['end']
        ],
        'yesterday' => [
            'label' => 'Yesterday',
            'start' => $yesterday['start'],
            'end' => $yesterday['end']
        ],
        'last_7_days' => [
            'label' => 'Last 7 Days',
            'start' => $getUtcDayBounds(7)['start'],
            'end' => $today['end']
        ],
        'last_30_days' => [
            'label' => 'Last 30 Days',
            'start' => $getUtcDayBounds(30)['start'],
            'end' => $today['end']
        ],
        'last_90_days' => [
            'label' => 'Last 90 Days',
            'start' => $getUtcDayBounds(90)['start'],
            'end' => $today['end']
        ],
        'last_365_days' => [
            'label' => 'Last 365 Days',
            'start' => $getUtcDayBounds(365)['start'],
            'end' => $today['end']
        ]
    ];
}

/**
 * Calculate SLA percentage
 */
function calculateSLA($uptimePercentage) {
    if ($uptimePercentage >= 99.99) {
        return 'Four Nines';
    } elseif ($uptimePercentage >= 99.9) {
        return 'Three Nines';
    } elseif ($uptimePercentage >= 99.5) {
        return 'Two Nines';
    } elseif ($uptimePercentage >= 99) {
        return 'One Nine';
    }
    return 'Below SLA';
}

/**
 * Get current UTC timestamp for database storage
 */
function getUtcTimestamp() {
    $utc = new DateTime('now', new DateTimeZone('UTC'));
    return $utc->format('Y-m-d H:i:s');
}

/**
 * Convert local datetime to UTC for database storage
 */
function toUtcTimestamp($localDatetime) {
    $date = new DateTime($localDatetime, new DateTimeZone(TIMEZONE));
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

/**
 * Convert UTC datetime to local timezone for display
 */
function fromUtcTimestamp($utcDatetime, $format = 'Y-m-d H:i:s') {
    $date = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone(TIMEZONE));
    return $date->format($format);
}