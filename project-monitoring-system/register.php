<?php
// Registration is disabled
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Disabled - Project Monitoring System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            text-align: center;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .icon {
            font-size: 60px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        p {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .info-box ol {
            margin-left: 20px;
            color: #555;
        }
        
        .info-box li {
            margin-bottom: 8px;
        }
        
        .code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>Registration Disabled</h1>
        <p>Public registration is currently disabled for security reasons.</p>
        <p>User accounts must be created by administrators.</p>
        
        <div class="info-box">
            <h3>For Administrators:</h3>
            <p>To create user accounts, you have two options:</p>
            <ol>
                <li><strong>Through the Admin Panel:</strong> Login as admin and navigate to User Management</li>
                <li><strong>Manually via Database:</strong> Use the following SQL command in phpMyAdmin:</li>
            </ol>
            <p style="margin-top: 10px; background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 14px;">
                INSERT INTO users (full_name, email, password_hash, role_id)<br>
                VALUES ('User Name', 'user@email.com', '$2y$10$...', 2);
            </p>
            <p style="margin-top: 10px; font-size: 14px;">
                Note: Use PHP's <code>password_hash('password', PASSWORD_DEFAULT)</code> to generate the password hash.
            </p>
        </div>
        
        <div class="info-box">
            <h3>First-Time Installation?</h3>
            <p>If you haven't created an admin account yet:</p>
            <ol>
                <li>Run the installation script at <code>/install.php</code></li>
                <li>Follow the wizard to create your first admin account</li>
                <li>Delete <code>install.php</code> after completion</li>
            </ol>
        </div>
        
        <a href="login.php" class="btn">Go to Login</a>
    </div>
</body>
</html>