<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

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

// Retrieve form data
$postId = $_POST['post_id'] ?? '';
$brandId = $_POST['brand_id'] ?? '';
$accountId = $_POST['account_id'] ?? '';

$caption = trim($_POST['caption'] ?? '');
$postType = $_POST['post_type'] ?? '';
$publishedAt = $_POST['published_at'] ?? '';
$postUrl = trim($_POST['post_url'] ?? '');

$userId = getUserId();
$userRole = getUserRole();

// Validate IDs
if (
    !ctype_digit((string)$postId) ||
    (int)$postId < 1 ||
    !ctype_digit((string)$brandId) ||
    (int)$brandId < 1 ||
    !ctype_digit((string)$accountId) ||
    (int)$accountId < 1
) {
    exit('Invalid post information.');
}

// Validate post type
$allowedTypes = [
    'Image',
    'Video',
    'Reel',
    'Story',
    'Carousel'
];

if (!in_array($postType, $allowedTypes, true)) {
    exit('Invalid post type.');
}

// Validate published date
$date = DateTime::createFromFormat('!Y-m-d\TH:i', $publishedAt);

if (
    !$date ||
    $date->format('Y-m-d\TH:i') !== $publishedAt
) {
    exit('Invalid published date.');
}

$publishedAt = $date->format('Y-m-d H:i:s');

// Validate URL
if (
    $postUrl !== '' &&
    (
        !filter_var($postUrl, FILTER_VALIDATE_URL) ||
        !in_array(
            strtolower((string)parse_url($postUrl, PHP_URL_SCHEME)),
            ['http', 'https'],
            true
        )
    )
) {
    exit('Invalid post URL.');
}

try {

    // Verify that the existing post belongs to this user
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

    // Verify that the new account belongs to the selected brand
    // and that the user is allowed to access it.
    if ($userRole === 'Admin') {

        $sql = "SELECT account_id
                FROM social_accounts
                WHERE account_id = ?
                AND brand_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$accountId, $brandId]);

    } else {

        $sql = "SELECT sa.account_id
                FROM social_accounts sa
                INNER JOIN brand_members bm
                    ON sa.brand_id = bm.brand_id
                WHERE sa.account_id = ?
                AND sa.brand_id = ?
                AND bm.user_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$accountId, $brandId, $userId]);
    }

    if (!$stmt->fetch()) {
        http_response_code(403);
        exit('You do not have permission to access this account.');
    }

    // Update post
    $sql = "UPDATE posts
            SET account_id = ?,
                caption = ?,
                post_type = ?,
                published_at = ?,
                post_url = ?
            WHERE post_id = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $accountId,
        $caption,
        $postType,
        $publishedAt,
        $postUrl !== '' ? $postUrl : null,
        $postId
    ]);

    // Generate a new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: ../posts.php?updated=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to update post. Please try again.');
}