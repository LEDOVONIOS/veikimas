<?php
require_once '../db.php';
require_once '../includes/monitoring_functions.php';
requireLogin();

header('Content-Type: application/json');

// Get parameters
$projectId = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT);
$timeRange = $_GET['time_range'] ?? '24h';

// Validate user has access to this project
$stmt = $pdo->prepare("
    SELECT id FROM projects 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$projectId, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    echo json_encode(['success' => false, 'error' => 'Invalid project']);
    exit;
}

// Get response time data
$data = getResponseTimeData($pdo, $projectId, $timeRange);

if ($data !== null) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch data'
    ]);
}
?>