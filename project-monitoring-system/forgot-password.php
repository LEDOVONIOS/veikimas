<?php
require_once 'db.php';
require_once 'includes/password_reset.php';

$errors = [];
$success = false;
$resetInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $errors[] = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($errors)) {
        // Create reset token
        $tokenData = createPasswordResetToken($email);
        
        if ($tokenData) {
            // Send reset email
            $emailResult = sendPasswordResetEmail($email, $tokenData['token'], $tokenData['full_name']);
            
            // For development, show the reset link
            if ($emailResult['success']) {
                $success = true;
                $resetInfo = $emailResult; // In production, remove this line
            } else {
                $errors[] = "Failed to send reset email. Please try again.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Uptime Monitoring System</title>
    
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
        }
        
        .btn-primary:hover {
            background-color: #5558e3;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.2);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: var(--bg-hover);
            transform: translateY(-1px);
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
        
        .reset-info {
            background-color: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: var(--warning);
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .reset-info h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .reset-link {
            word-break: break-all;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 12px;
            border-radius: 6px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            line-height: 1.5;
        }
        
        .back-icon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        
        .back-icon:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <a href="login.php" class="back-icon">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 12L6 8l4-4"></path>
            </svg>
            Back to login
        </a>
        
        <div class="auth-card">
            <div class="auth-logo">
                <img src="https://uptime.seorocket.lt/images/seorocket.png" alt="SEO Rocket" style="max-width: 200px; height: auto; margin-bottom: 24px;">
                <h1>Forgot Password?</h1>
            </div>
            <p class="auth-subtitle">No worries, we'll send you reset instructions</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>✅ If an account exists with that email address, password reset instructions have been sent.</p>
                    <p>Please check your email and follow the instructions to reset your password.</p>
                </div>
                
                <?php if ($resetInfo && isset($_GET['dev'])): // Show reset link in development mode ?>
                    <div class="reset-info">
                        <h3>🔧 Development Mode - Reset Link:</h3>
                        <div class="reset-link">
                            <?php echo htmlspecialchars($resetInfo['reset_link']); ?>
                        </div>
                        <p style="margin-top: 10px;"><small>In production, this link would be sent via email.</small></p>
                    </div>
                <?php endif; ?>
                
                <p style="margin-top: 24px; text-align: center;">
                    <a href="login.php" class="btn-secondary">Back to Login</a>
                </p>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="forgot-password.php<?php echo isset($_GET['dev']) ? '?dev=1' : ''; ?>" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="Enter your registered email"
                               required autofocus>
                    </div>
                    
                    <button type="submit" class="btn-primary">Send Reset Instructions</button>
                </form>
                
                <p class="auth-link">
                    Remember your password? <a href="login.php">Back to Login</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>