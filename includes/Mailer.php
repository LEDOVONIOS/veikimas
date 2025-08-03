<?php
/**
 * Mailer class for sending email notifications
 */
class Mailer {
    
    /**
     * Send an email
     */
    public function send($to, $subject, $body, $isHtml = false) {
        if (MAIL_METHOD === 'smtp') {
            return $this->sendSMTP($to, $subject, $body, $isHtml);
        } else {
            return $this->sendMail($to, $subject, $body, $isHtml);
        }
    }
    
    /**
     * Send email using PHP mail() function
     */
    private function sendMail($to, $subject, $body, $isHtml = false) {
        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Send email using SMTP
     */
    private function sendSMTP($to, $subject, $body, $isHtml = false) {
        // For SMTP, we'll use a simple implementation
        // In production, you might want to use PHPMailer library
        
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
        if (!$smtp) {
            return false;
        }
        
        // Read server response
        $this->getSmtpResponse($smtp);
        
        // Send EHLO
        fputs($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->getSmtpResponse($smtp);
        
        // Start TLS if required
        if (SMTP_SECURE === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $this->getSmtpResponse($smtp);
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after STARTTLS
            fputs($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $this->getSmtpResponse($smtp);
        }
        
        // Authentication
        fputs($smtp, "AUTH LOGIN\r\n");
        $this->getSmtpResponse($smtp);
        
        fputs($smtp, base64_encode(SMTP_USER) . "\r\n");
        $this->getSmtpResponse($smtp);
        
        fputs($smtp, base64_encode(SMTP_PASS) . "\r\n");
        $this->getSmtpResponse($smtp);
        
        // Set sender
        fputs($smtp, "MAIL FROM: <" . MAIL_FROM . ">\r\n");
        $this->getSmtpResponse($smtp);
        
        // Set recipient
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $this->getSmtpResponse($smtp);
        
        // Start data
        fputs($smtp, "DATA\r\n");
        $this->getSmtpResponse($smtp);
        
        // Send headers and body
        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . uniqid() . "@" . $_SERVER['SERVER_NAME'] . ">\r\n";
        
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        fputs($smtp, $headers . "\r\n");
        fputs($smtp, $body . "\r\n");
        fputs($smtp, ".\r\n");
        $this->getSmtpResponse($smtp);
        
        // Quit
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
    }
    
    /**
     * Get SMTP server response
     */
    private function getSmtpResponse($smtp) {
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Send HTML email with nice formatting
     */
    public function sendHtmlNotification($to, $subject, $data) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #007bff;
            color: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
            color: #666;
        }
        .status-up {
            color: #28a745;
            font-weight: bold;
        }
        .status-down {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . SITE_NAME . '</h1>
        </div>
        <div class="content">
            ' . $this->buildHtmlContent($data) . '
        </div>
        <div class="footer">
            <p>This is an automated notification from ' . SITE_NAME . '</p>
            <p>' . SITE_URL . '</p>
        </div>
    </div>
</body>
</html>';
        
        return $this->send($to, $subject, $html, true);
    }
    
    /**
     * Build HTML content based on notification type
     */
    private function buildHtmlContent($data) {
        $html = '';
        
        switch ($data['type']) {
            case 'down':
                $html .= '<h2 style="color: #dc3545;">🔴 Website is DOWN</h2>';
                $html .= '<p>We detected that your monitored website is currently unavailable.</p>';
                $html .= '<table class="info-table">';
                $html .= '<tr><td>Project</td><td>' . htmlspecialchars($data['project_name']) . '</td></tr>';
                $html .= '<tr><td>URL</td><td><a href="' . htmlspecialchars($data['url']) . '">' . htmlspecialchars($data['url']) . '</a></td></tr>';
                $html .= '<tr><td>Status Code</td><td>' . ($data['status_code'] ?: 'N/A') . '</td></tr>';
                $html .= '<tr><td>Error</td><td>' . htmlspecialchars($data['error_message'] ?: 'Unknown') . '</td></tr>';
                $html .= '<tr><td>Time</td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
                $html .= '</table>';
                $html .= '<p>We will continue monitoring and notify you when the website comes back online.</p>';
                break;
                
            case 'up':
                $html .= '<h2 style="color: #28a745;">✅ Website is UP</h2>';
                $html .= '<p>Good news! Your monitored website is back online.</p>';
                $html .= '<table class="info-table">';
                $html .= '<tr><td>Project</td><td>' . htmlspecialchars($data['project_name']) . '</td></tr>';
                $html .= '<tr><td>URL</td><td><a href="' . htmlspecialchars($data['url']) . '">' . htmlspecialchars($data['url']) . '</a></td></tr>';
                if (isset($data['duration'])) {
                    $html .= '<tr><td>Downtime Duration</td><td>' . htmlspecialchars($data['duration']) . '</td></tr>';
                }
                $html .= '<tr><td>Time</td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
                $html .= '</table>';
                break;
                
            case 'ssl_expiry':
                $html .= '<h2 style="color: #ffc107;">⚠️ SSL Certificate Expiring Soon</h2>';
                $html .= '<div class="warning">';
                $html .= '<p>The SSL certificate for your monitored website will expire in <strong>' . $data['days_remaining'] . ' days</strong>.</p>';
                $html .= '</div>';
                $html .= '<table class="info-table">';
                $html .= '<tr><td>Project</td><td>' . htmlspecialchars($data['project_name']) . '</td></tr>';
                $html .= '<tr><td>URL</td><td><a href="' . htmlspecialchars($data['url']) . '">' . htmlspecialchars($data['url']) . '</a></td></tr>';
                $html .= '<tr><td>Days Remaining</td><td>' . $data['days_remaining'] . '</td></tr>';
                $html .= '</table>';
                $html .= '<p>Please renew your SSL certificate to avoid security warnings for your visitors.</p>';
                break;
                
            case 'domain_expiry':
                $html .= '<h2 style="color: #ffc107;">⚠️ Domain Expiring Soon</h2>';
                $html .= '<div class="warning">';
                $html .= '<p>The domain for your monitored website will expire in <strong>' . $data['days_remaining'] . ' days</strong>.</p>';
                $html .= '</div>';
                $html .= '<table class="info-table">';
                $html .= '<tr><td>Project</td><td>' . htmlspecialchars($data['project_name']) . '</td></tr>';
                $html .= '<tr><td>URL</td><td><a href="' . htmlspecialchars($data['url']) . '">' . htmlspecialchars($data['url']) . '</a></td></tr>';
                $html .= '<tr><td>Days Remaining</td><td>' . $data['days_remaining'] . '</td></tr>';
                $html .= '</table>';
                $html .= '<p>Please renew your domain registration to avoid losing your website.</p>';
                break;
        }
        
        $html .= '<div style="text-align: center;">';
        $html .= '<a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '" class="button">View Project Details</a>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get incident started email template
     */
    public function getIncidentStartedTemplate($data) {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Down Alert</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        
        /* Email styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .status-message {
            font-size: 24px;
            font-weight: 600;
            color: #ff6b6b;
            margin: 0;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .alert-box {
            background-color: #fff5f5;
            border-left: 4px solid #ff6b6b;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .alert-box p {
            margin: 0;
            color: #c53030;
        }
        
        .info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-label {
            display: table-cell;
            width: 40%;
            padding-right: 20px;
            color: #6c757d;
            font-weight: 600;
            vertical-align: top;
        }
        
        .info-value {
            display: table-cell;
            width: 60%;
            color: #333333;
            word-break: break-word;
        }
        
        .info-value a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        
        .info-value a:hover {
            text-decoration: underline;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .button {
            display: inline-block;
            padding: 14px 30px;
            background-color: #ff6b6b;
            color: #ffffff !important;
            text-decoration: none !important;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .button:hover {
            background-color: #ff5252;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
        
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        
        /* Responsive styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            .header, .content, .footer {
                padding: 20px !important;
            }
            .info-row {
                display: block !important;
            }
            .info-label, .info-value {
                display: block !important;
                width: 100% !important;
                padding-right: 0 !important;
            }
            .info-label {
                margin-bottom: 5px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">' . SITE_NAME . '</div>
            <h1 class="status-message">' . htmlspecialchars($data['monitor_name']) . ' is down.</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="alert-box">
                <p><strong>Alert:</strong> We detected that your monitored website is currently experiencing downtime.</p>
            </div>
            
            <p>Hi ' . htmlspecialchars($data['user_name']) . ',</p>
            
            <p>We\'ve detected an issue with your monitored website. Our system was unable to reach your site successfully. Please check your website to ensure everything is functioning correctly.</p>
            
            <!-- Key Information -->
            <div class="info-section">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #333;">Incident Details</h3>
                
                <div class="info-row">
                    <div class="info-label">Monitor Name:</div>
                    <div class="info-value">
                        <a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '">' . htmlspecialchars($data['monitor_name']) . '</a>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Checked URL:</div>
                    <div class="info-value">' . htmlspecialchars($data['checked_url']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Root Cause:</div>
                    <div class="info-value" style="color: #c53030; font-weight: 600;">' . htmlspecialchars($data['root_cause']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Incident Started:</div>
                    <div class="info-value">' . htmlspecialchars($data['incident_start']) . '</div>
                </div>
                
                ' . (isset($data['status_code']) && $data['status_code'] ? '
                <div class="info-row">
                    <div class="info-label">HTTP Status:</div>
                    <div class="info-value">' . htmlspecialchars($data['status_code']) . '</div>
                </div>' : '') . '
            </div>
            
            <p>We will continue monitoring your website and notify you once it\'s back online. In the meantime, you may want to:</p>
            
            <ul style="color: #555;">
                <li>Check your server status</li>
                <li>Verify your DNS settings</li>
                <li>Contact your hosting provider</li>
                <li>Review recent changes to your website</li>
            </ul>
            
            <!-- CTA Button -->
            <div class="button-container">
                <a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '" class="button">View Incident Details</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is an automated notification from ' . SITE_NAME . '</p>
            <p>
                <a href="' . SITE_URL . '">Visit Dashboard</a> | 
                <a href="' . SITE_URL . '/profile.php">Notification Settings</a>
            </p>
            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                © ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get incident resolved email template
     */
    public function getIncidentResolvedTemplate($data) {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Back Online</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        
        /* Email styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .status-message {
            font-size: 24px;
            font-weight: 600;
            color: #51cf66;
            margin: 0;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .alert-box {
            background-color: #f0fff4;
            border-left: 4px solid #51cf66;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .alert-box p {
            margin: 0;
            color: #22543d;
        }
        
        .info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-label {
            display: table-cell;
            width: 40%;
            padding-right: 20px;
            color: #6c757d;
            font-weight: 600;
            vertical-align: top;
        }
        
        .info-value {
            display: table-cell;
            width: 60%;
            color: #333333;
            word-break: break-word;
        }
        
        .info-value a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        
        .info-value a:hover {
            text-decoration: underline;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .button {
            display: inline-block;
            padding: 14px 30px;
            margin: 0 10px;
            text-decoration: none !important;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .button-primary {
            background-color: #51cf66;
            color: #ffffff !important;
        }
        
        .button-primary:hover {
            background-color: #40c057;
        }
        
        .button-secondary {
            background-color: transparent;
            color: #007bff !important;
            border: 2px solid #007bff;
        }
        
        .button-secondary:hover {
            background-color: #007bff;
            color: #ffffff !important;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
        
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        
        .duration-highlight {
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        /* Responsive styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            .header, .content, .footer {
                padding: 20px !important;
            }
            .info-row {
                display: block !important;
            }
            .info-label, .info-value {
                display: block !important;
                width: 100% !important;
                padding-right: 0 !important;
            }
            .info-label {
                margin-bottom: 5px !important;
            }
            .button {
                display: block !important;
                margin: 10px 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">' . SITE_NAME . '</div>
            <h1 class="status-message">' . htmlspecialchars($data['monitor_name']) . ' is up.</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="alert-box">
                <p><strong>Good news!</strong> Your monitored website is back online and responding normally.</p>
            </div>
            
            <p>Hi ' . htmlspecialchars($data['user_name']) . ',</p>
            
            <p>We\'re happy to report that your website is now accessible again. The incident has been resolved and your site is responding as expected.</p>
            
            <!-- Key Information -->
            <div class="info-section">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #333;">Incident Summary</h3>
                
                <div class="info-row">
                    <div class="info-label">Monitor Name:</div>
                    <div class="info-value">
                        <a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '">' . htmlspecialchars($data['monitor_name']) . '</a>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Checked URL:</div>
                    <div class="info-value">' . htmlspecialchars($data['checked_url']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Root Cause:</div>
                    <div class="info-value">' . htmlspecialchars($data['root_cause']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Incident Started:</div>
                    <div class="info-value">' . htmlspecialchars($data['incident_start']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Incident Resolved:</div>
                    <div class="info-value" style="color: #22543d; font-weight: 600;">' . htmlspecialchars($data['incident_resolved']) . '</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Total Downtime:</div>
                    <div class="info-value">
                        <span class="duration-highlight">' . htmlspecialchars($data['incident_duration']) . '</span>
                    </div>
                </div>
            </div>
            
            <p>We recommend reviewing your logs to understand what caused this incident and prevent future occurrences. Consider:</p>
            
            <ul style="color: #555;">
                <li>Checking server logs for errors during the downtime period</li>
                <li>Reviewing any recent deployments or configuration changes</li>
                <li>Ensuring your hosting resources are sufficient</li>
                <li>Setting up additional monitoring alerts if needed</li>
            </ul>
            
            <!-- CTA Buttons -->
            <div class="button-container">
                <a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '" class="button button-primary">View Incident Details</a>
                <a href="' . SITE_URL . '/project.php?id=' . $data['project_id'] . '#comment" class="button button-secondary">Comment Incident</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is an automated notification from ' . SITE_NAME . '</p>
            <p>
                <a href="' . SITE_URL . '">Visit Dashboard</a> | 
                <a href="' . SITE_URL . '/profile.php">Notification Settings</a>
            </p>
            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                © ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}