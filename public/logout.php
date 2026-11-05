<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

// Get the current user ID before destroying the session
$userId = getUserId();

// Record logout activity
if ($userId !== null) {
    logActivity(
        $pdo,
        $userId,
        'Logout',
        'User logged out successfully.'
    );
}

// Clear session data
$_SESSION = [];

// Remove session cookie
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

header('Location: login.php');
exit;