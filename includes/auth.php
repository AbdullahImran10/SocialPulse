<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect users who are not logged in
function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /socialpulse/public/login.php');
        exit;
    }
}

// Check the user's role
function requireRole($requiredRole)
{
    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== $requiredRole
    ) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Return the logged-in user's ID
function getUserId()
{
    return $_SESSION['user_id'] ?? null;
}

// Return the logged-in user's role
function getUserRole()
{
    return $_SESSION['role'] ?? null;
}
// Check whether the logged-in user has a specific role
function hasRole($requiredRole)
{
    return isset($_SESSION['role']) &&
           $_SESSION['role'] === $requiredRole;
}