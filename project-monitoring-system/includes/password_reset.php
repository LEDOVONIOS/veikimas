<?php
/**
 * Password Reset Helper Functions
 */

require_once dirname(__DIR__) . '/db.php';

/**
 * Generate a secure random token
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Create a password reset token for a user
 */
function createPasswordResetToken($email) {
    global $pdo;
    
    // Find user by email
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false; // User not found
    }
    
    // Invalidate any existing tokens for this user
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE user_id = ? AND used = FALSE");
    $stmt->execute([$user['id']]);
    
    // Generate new token
    $token = generateResetToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
    
    // Insert new token
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    return [
        'token' => $token,
        'user_id' => $user['id'],
        'email' => $email,
        'full_name' => $user['full_name']
    ];
}

/**
 * Validate a password reset token
 */
function validateResetToken($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT prt.*, u.email, u.full_name 
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? 
        AND prt.expires_at > NOW() 
        AND prt.used = FALSE
    ");
    $stmt->execute([$token]);
    
    return $stmt->fetch();
}

/**
 * Reset user password
 */
function resetUserPassword($token, $newPassword) {
    global $pdo;
    
    // Validate token first
    $tokenData = validateResetToken($token);
    if (!$tokenData) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update user password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $tokenData['user_id']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
        $stmt->execute([$token]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Send password reset email (placeholder - implement based on your email setup)
 */
function sendPasswordResetEmail($email, $token, $fullName) {
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
    
    // For now, we'll just return the reset link
    // In production, you would send this via email using mail(), PHPMailer, or another email service
    
    $subject = "Password Reset Request - Project Monitoring System";
    $message = "Hello {$fullName},\n\n";
    $message .= "You have requested to reset your password.\n\n";
    $message .= "Please click the link below to reset your password:\n";
    $message .= $resetLink . "\n\n";
    $message .= "This link will expire in 1 hour.\n\n";
    $message .= "If you did not request this password reset, please ignore this email.\n\n";
    $message .= "Best regards,\nProject Monitoring System";
    
    // For development/testing, we'll return the info
    return [
        'success' => true,
        'reset_link' => $resetLink,
        'message' => $message
    ];
    
    // In production, uncomment and configure:
    // $headers = "From: noreply@yourdomain.com\r\n";
    // $headers .= "Reply-To: support@yourdomain.com\r\n";
    // return mail($email, $subject, $message, $headers);
}

/**
 * Clean up expired tokens
 */
function cleanupExpiredTokens() {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = TRUE");
    return $stmt->execute();
}
?>