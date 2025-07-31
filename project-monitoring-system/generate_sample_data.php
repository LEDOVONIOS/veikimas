<?php
/**
 * Sample Data Generator
 * This script generates mock monitoring data for testing purposes
 * Run this script after creating a project to populate it with sample data
 * 
 * Usage: Navigate to this file in your browser after logging in
 * URL: your-domain.com/project-monitor/generate_sample_data.php?project_id=X
 */

require_once 'db.php';
require_once 'includes/monitoring_functions.php';
requireLogin();

// Get project ID from URL
$projectId = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$projectId) {
    die("Please provide a valid project_id in the URL (e.g., ?project_id=1)");
}

// Verify project belongs to user
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        die("Project not found or you don't have permission to access it.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Generate mock data
echo "<h1>Generating Sample Data for: " . htmlspecialchars($project['project_name']) . "</h1>";
echo "<p>This may take a moment...</p>";

// Flush output to show progress
ob_flush();
flush();

// Generate the data
$result = generateMockData($pdo, $projectId);

if ($result) {
    echo "<p style='color: green;'>✓ Sample data generated successfully!</p>";
    echo "<p>Generated:</p>";
    echo "<ul>";
    echo "<li>30 days of uptime logs (checked every 5 minutes)</li>";
    echo "<li>7 days of HTTP status code distribution</li>";
    echo "<li>SSL certificate information</li>";
    echo "<li>24 hours of response time data</li>";
    echo "</ul>";
    echo "<p><a href='project.php?id=" . $projectId . "'>View Project Monitoring Dashboard</a></p>";
} else {
    echo "<p style='color: red;'>✗ Failed to generate sample data. Please check error logs.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Sample Data - Project Monitoring System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
        }
        a {
            color: #4F46E5;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
</body>
</html>