<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Require login
$auth->requireLogin();

// Check if user is admin
if (!$auth->isAdmin()) {
    redirect('dashboard.php');
}

$userId = (int) getGet('id');
$action = getGet('action');

if (!$userId || !in_array($action, ['activate', 'deactivate'])) {
    redirect('users.php');
}

// Prevent actions on own account
if ($userId === $auth->getUserId()) {
    $_SESSION['error'] = 'You cannot modify your own account status.';
    redirect('users.php');
}

// Get user details
$user = $db->fetchOne(
    "SELECT * FROM " . DB_PREFIX . "users WHERE id = ?",
    [$userId]
);

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    redirect('users.php');
}

// Update user status
$newStatus = ($action === 'activate') ? 'active' : 'inactive';
$updated = $db->update(
    DB_PREFIX . 'users',
    [
        'status' => $newStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ],
    'id = ?',
    [$userId]
);

if ($updated !== false) {
    $_SESSION['success'] = 'User ' . $user['username'] . ' has been ' . ($action === 'activate' ? 'activated' : 'deactivated') . '.';
} else {
    $_SESSION['error'] = 'Failed to update user status. Please try again.';
}

redirect('users.php');