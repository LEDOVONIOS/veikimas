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
}