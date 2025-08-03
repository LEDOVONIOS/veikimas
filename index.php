<?php
/**
 * Index page - redirects to appropriate location
 */

// Check if config exists
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

require_once 'config/config.php';
require_once 'includes/init.php';

// If logged in, go to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;