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
    <title>Forgot Password - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reset-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .reset-info h3 {
            margin-top: 0;
        }
        .reset-link {
            word-break: break-all;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p class="auth-subtitle">Enter your email to receive password reset instructions</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>If an account exists with that email address, password reset instructions have been sent.</p>
                    <p>Please check your email and follow the instructions to reset your password.</p>
                </div>
                
                <?php if ($resetInfo && isset($_GET['dev'])): // Show reset link in development mode ?>
                    <div class="reset-info">
                        <h3>Development Mode - Reset Link:</h3>
                        <div class="reset-link">
                            <?php echo htmlspecialchars($resetInfo['reset_link']); ?>
                        </div>
                        <p style="margin-top: 10px;"><small>In production, this link would be sent via email.</small></p>
                    </div>
                <?php endif; ?>
                
                <p style="margin-top: 20px;">
                    <a href="login.php" class="btn btn-secondary">Back to Login</a>
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
                    
                    <button type="submit" class="btn btn-primary">Send Reset Instructions</button>
                </form>
                
                <p class="auth-link">
                    Remember your password? <a href="login.php">Back to Login</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>