<?php
require_once 'config/config.php';
require_once 'includes/init.php';

// Logout the user
$auth->logout();

// Redirect to login page
redirect('login.php');