<?php
/**
 * Test script to preview email templates
 * Access this file directly to see the email templates
 */

// Temporary config for testing
define('SITE_NAME', 'Website Monitor');
define('SITE_URL', 'https://monitor.example.com');

// Include the Mailer class
require_once 'includes/Mailer.php';

$mailer = new Mailer();

// Get template type from URL parameter
$template = isset($_GET['template']) ? $_GET['template'] : 'started';

if ($template === 'started') {
    // Sample data for incident started email
    $data = [
        'user_name' => 'John Doe',
        'monitor_name' => 'My E-commerce Website',
        'checked_url' => 'https://shop.example.com',
        'root_cause' => 'HTTP 500 - Server Error',
        'incident_start' => date('Y-m-d H:i:s'),
        'status_code' => 500,
        'project_id' => 123
    ];
    
    echo $mailer->getIncidentStartedTemplate($data);
    
} elseif ($template === 'resolved') {
    // Sample data for incident resolved email
    $data = [
        'user_name' => 'John Doe',
        'monitor_name' => 'My E-commerce Website',
        'checked_url' => 'https://shop.example.com',
        'root_cause' => 'HTTP 500 - Server Error',
        'incident_start' => date('Y-m-d H:i:s', strtotime('-4 minutes -23 seconds')),
        'incident_resolved' => date('Y-m-d H:i:s'),
        'incident_duration' => '4 minutes 23 seconds',
        'project_id' => 123
    ];
    
    echo $mailer->getIncidentResolvedTemplate($data);
    
} else {
    // Show links to both templates
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Email Template Preview</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; text-align: center; }
            .button { 
                display: inline-block; 
                padding: 15px 30px; 
                margin: 10px; 
                background-color: #007bff; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                font-size: 18px;
            }
            .button:hover { background-color: #0056b3; }
            .button.danger { background-color: #dc3545; }
            .button.danger:hover { background-color: #c82333; }
            .button.success { background-color: #28a745; }
            .button.success:hover { background-color: #218838; }
        </style>
    </head>
    <body>
        <h1>Email Template Preview</h1>
        <p>Click on a button below to preview the email template:</p>
        
        <a href="?template=started" class="button danger">🔴 Incident Started Email</a>
        <a href="?template=resolved" class="button success">🟢 Incident Resolved Email</a>
        
        <p style="margin-top: 40px; color: #666;">
            <small>Note: This is a test file for development. Delete this file in production.</small>
        </p>
    </body>
    </html>
    <?php
}