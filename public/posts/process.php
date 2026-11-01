<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if (
    !hasRole('Admin') &&
    !hasRole('Social Media Manager')
) {
    http_response_code(403);
    exit('Access denied.');
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add.php');
    exit;
}

// Verify CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    exit('Invalid request. Please try again.');
}

// Retrieve form data
$brandId = $_POST['brand_id'] ?? '';
$accountId = $_POST['account_id'] ?? '';

$caption = trim($_POST['caption'] ?? '');
$postType = $_POST['post_type'] ?? '';
$publishedAt = $_POST['published_at'] ?? '';
$postUrl = trim($_POST['post_url'] ?? '');

$views = $_POST['views'] ?? '';
$likes = $_POST['likes'] ?? '';
$comments = $_POST['comments'] ?? '';
$shares = $_POST['shares'] ?? '';
$reach = $_POST['reach'] ?? '';

$userId = getUserId();
$userRole = getUserRole();

// Validate required fields
if (
    !ctype_digit((string)$brandId) ||
    !ctype_digit((string)$accountId) ||
    empty($publishedAt)
) {
    exit('Please provide valid post information.');
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

// Validate metrics
$metrics = [
    'views' => $views,
    'likes' => $likes,
    'comments' => $comments,
    'shares' => $shares,
    'reach' => $reach
];

foreach ($metrics as $value) {

    if (
        !is_string($value) ||
        !ctype_digit($value) ||
        (float)$value > PHP_INT_MAX
    ) {
        exit('Invalid analytics values.');
    }
}

// Validate URL
if ($postUrl !== '' && !filter_var($postUrl, FILTER_VALIDATE_URL)) {
    exit('Invalid post URL.');
}

// Validate date
$date = DateTime::createFromFormat('!Y-m-d\TH:i', $publishedAt);

if (
    !$date ||
    $date->format('Y-m-d\TH:i') !== $publishedAt
) {
    exit('Invalid published date.');
}

$publishedAt = $date->format('Y-m-d H:i:s');

try {

    // Verify account, brand and user permissions
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
        exit('You do not have permission to access this account.');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert post
    $sql = "INSERT INTO posts
            (account_id, caption, post_type, published_at, post_url)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $accountId,
        $caption,
        $postType,
        $publishedAt,
        $postUrl !== '' ? $postUrl : null
    ]);

    // Get newly created post ID
    $postId = $pdo->lastInsertId();

    // Insert post analytics
    $sql = "INSERT INTO post_metrics
            (post_id, views, likes, comments, shares, reach)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $postId,
        $views,
        $likes,
        $comments,
        $shares,
        $reach
    ]);

    // Commit transaction
    $pdo->commit();

    // Generate a new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: add.php?success=1');
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to save post. Please try again.');

}