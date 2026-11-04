<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Only Admin and Social Media Manager can delete posts
if (!hasRole('Admin') && !hasRole('Social Media Manager')) {
    http_response_code(403);
    exit('Access denied.');
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../posts.php');
    exit;
}

// Verify CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid request. Please try again.');
}

$postId = $_POST['post_id'] ?? '';

if (!ctype_digit((string)$postId) || (int)$postId < 1) {
    exit('Invalid post ID.');
}

$userId = getUserId();
$userRole = getUserRole();

try {

    // Verify post ownership and permissions
    if ($userRole === 'Admin') {

        $sql = "SELECT p.post_id
                FROM posts p
                WHERE p.post_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$postId]);

    } else {

        $sql = "SELECT p.post_id
                FROM posts p
                INNER JOIN social_accounts sa
                    ON p.account_id = sa.account_id
                INNER JOIN brand_members bm
                    ON sa.brand_id = bm.brand_id
                WHERE p.post_id = ?
                AND bm.user_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$postId, $userId]);
    }

    if (!$stmt->fetch()) {
        http_response_code(404);
        exit('Post not found or access denied.');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Delete associated analytics
    $sql = "DELETE FROM post_metrics
            WHERE post_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$postId]);

    // Delete post
    $sql = "DELETE FROM posts
            WHERE post_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$postId]);

    // Commit transaction
    $pdo->commit();

    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: ../posts.php?deleted=1');
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to delete post. Please try again.');
}