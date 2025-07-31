<?php
require_once 'db.php';
requireLogin();

// Get project ID
$projectId = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$projectId) {
    header("Location: dashboard.php");
    exit();
}

// Verify user owns this project
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get all incidents for the project
    $stmt = $pdo->prepare("
        SELECT 
            status,
            root_cause,
            started_at,
            resolved_at,
            duration,
            CASE 
                WHEN status = 'Open' THEN 'Ongoing'
                ELSE CONCAT(duration, ' minutes')
            END as duration_formatted
        FROM incidents 
        WHERE project_id = ?
        ORDER BY started_at DESC
    ");
    $stmt->execute([$projectId]);
    $incidents = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $project['project_name'] . '_incidents_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header information
    fputcsv($output, ['Project Incident Log Report']);
    fputcsv($output, ['Project:', $project['project_name']]);
    fputcsv($output, ['URL:', $project['project_url']]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Write column headers
    fputcsv($output, [
        'Status',
        'Root Cause',
        'Started At',
        'Resolved At',
        'Duration',
        'Time Zone'
    ]);
    
    // Write incident data
    foreach ($incidents as $incident) {
        fputcsv($output, [
            $incident['status'],
            $incident['root_cause'],
            $incident['started_at'],
            $incident['resolved_at'] ?: 'N/A',
            $incident['duration_formatted'],
            'UTC'
        ]);
    }
    
    // Add summary
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Incidents:', count($incidents)]);
    fputcsv($output, ['Open Incidents:', count(array_filter($incidents, function($i) { return $i['status'] === 'Open'; }))]);
    fputcsv($output, ['Resolved Incidents:', count(array_filter($incidents, function($i) { return $i['status'] === 'Resolved'; }))]);
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    header("Location: dashboard.php");
    exit();
}
?>