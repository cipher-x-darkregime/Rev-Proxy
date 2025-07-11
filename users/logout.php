<?php
require_once __DIR__ . '/../core/config.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: user_login.php');
exit();
?> 