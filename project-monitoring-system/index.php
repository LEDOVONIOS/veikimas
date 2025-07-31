<?php
/**
 * Project Monitoring System
 * Index page - Redirects to appropriate page based on authentication status
 */

session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header("Location: dashboard.php");
} else {
    // User is not logged in, redirect to login page
    header("Location: login.php");
}
exit();
?>