<?php
require_once 'db.php';
require_once 'includes/password_reset.php';

$errors = [];
$success = false;
$tokenValid = false;
$tokenData = null;

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $errors[] = "Invalid or missing reset token.";
} else {
    // Validate token
    $tokenData = validateResetToken($token);
    if ($tokenData) {
        $tokenValid = true;
    } else {
        $errors[] = "This password reset link is invalid or has expired.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($newPassword)) {
        $errors[] = "Please enter a new password.";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        // Reset password
        if (resetUserPassword($token, $newPassword)) {
            $success = true;
        } else {
            $errors[] = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Uptime Monitoring System</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Dark Theme CSS -->
    <link rel="stylesheet" href="assets/css/dark-theme.css">
    
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-primary);
            position: relative;
            overflow: hidden;
        }
        
        /* Background gradient effect */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(99, 102, 241, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-20px, -20px) rotate(180deg); }
        }
        
        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .auth-card {
            background-color: var(--bg-secondary);
            border-radius: 16px;
            padding: 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .auth-logo h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .auth-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 32px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px 24px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 24px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background-color: #5558e3;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.2);
            color: white;
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert p {
            margin: 0;
        }
        
        .alert p + p {
            margin-top: 8px;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        
        .auth-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .auth-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .auth-link a:hover {
            color: #5558e3;
        }
        
        .password-requirements {
            background-color: var(--bg-tertiary);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .password-requirements strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            list-style: none;
        }
        
        .password-requirements li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 4px;
        }
        
        .password-requirements li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
        }
        
        .email-display {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 4px;
        }
        
        .success-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <?php if ($success): ?>
                <div class="success-icon">✅</div>
                <div class="auth-logo">
                    <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket" style="max-width: 200px; height: auto; margin-bottom: 24px;">
                    <h1>Password Reset!</h1>
                </div>
                <div class="alert alert-success">
                    <p>Your password has been successfully reset!</p>
                    <p>You can now login with your new password.</p>
                </div>
                <p style="margin-top: 24px;">
                    <a href="login.php" class="btn-primary">Go to Login</a>
                </p>
            <?php elseif ($tokenValid): ?>
                <div class="auth-logo">
                    <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket" style="max-width: 200px; height: auto; margin-bottom: 24px;">
                    <h1>Reset Password</h1>
                </div>
                <p class="auth-subtitle">
                    Enter your new password for<br>
                    <span class="email-display"><?php echo htmlspecialchars($tokenData['email']); ?></span>
                </p>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Use a mix of letters, numbers, and symbols</li>
                    </ul>
                </div>
                
                <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" class="auth-form">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter new password"
                               required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm new password"
                               required>
                    </div>
                    
                    <button type="submit" class="btn-primary">Reset Password</button>
                </form>
                
                <p class="auth-link">
                    <a href="login.php">Back to Login</a>
                </p>
            <?php else: ?>
                <div class="auth-logo">
                    <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket" style="max-width: 200px; height: auto; margin-bottom: 24px;">
                    <h1>Invalid Link</h1>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <p class="auth-subtitle">This password reset link is invalid or has expired.</p>
                <p class="auth-subtitle">To reset your password, please request a new reset link.</p>
                <p style="margin-top: 24px;">
                    <a href="forgot-password.php" class="btn-primary">Request Password Reset</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>