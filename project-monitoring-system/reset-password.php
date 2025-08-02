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
    <title>Reset Password - Project Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .password-requirements {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        .password-requirements ul {
            margin: 5px 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Reset Password</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Your password has been successfully reset!</p>
                    <p>You can now login with your new password.</p>
                </div>
                <p style="margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </p>
            <?php elseif ($tokenValid): ?>
                <p class="auth-subtitle">Enter your new password for <?php echo htmlspecialchars($tokenData['email']); ?></p>
                
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
                        <li>Use a mix of letters, numbers, and symbols for better security</li>
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
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
                
                <p class="auth-link">
                    <a href="login.php">Back to Login</a>
                </p>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <p>To reset your password, please request a new reset link.</p>
                <p style="margin-top: 20px;">
                    <a href="forgot-password.php" class="btn btn-primary">Request Password Reset</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>