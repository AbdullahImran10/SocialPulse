<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$role = getUserRole();
$userId = getUserId();

if (!in_array($role, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate CSRF token
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid request. Please try again.');
}

// Validate metric ID
$metricId = filter_input(INPUT_POST, 'metric_id', FILTER_VALIDATE_INT);

if (!$metricId || $metricId < 1) {
    http_response_code(400);
    exit('Invalid metrics ID.');
}

// Validate metric values
$views = filter_input(INPUT_POST, 'views', FILTER_VALIDATE_INT);
$likes = filter_input(INPUT_POST, 'likes', FILTER_VALIDATE_INT);
$comments = filter_input(INPUT_POST, 'comments', FILTER_VALIDATE_INT);
$shares = filter_input(INPUT_POST, 'shares', FILTER_VALIDATE_INT);
$reach = filter_input(INPUT_POST, 'reach', FILTER_VALIDATE_INT);

$recordedAt = trim($_POST['recorded_at'] ?? '');

$values = [$views, $likes, $comments, $shares, $reach];

foreach ($values as $value) {
    if ($value === false || $value === null || $value < 0) {
        http_response_code(400);
        exit('All metrics must be valid non-negative numbers.');
    }
}

// Validate recording date
$date = DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);

if (!$date || $date->format('Y-m-d\TH:i') !== $recordedAt) {
    http_response_code(400);
    exit('Invalid recording date and time.');
}

$recordedAt = $date->format('Y-m-d H:i:s');

// Verify record access
$sql = "
    SELECT pm.metric_id
    FROM post_metrics pm
    JOIN posts p ON pm.post_id = p.post_id
    JOIN social_accounts sa ON p.account_id = sa.account_id
    JOIN brands b ON sa.brand_id = b.brand_id
    WHERE pm.metric_id = ?
";

$params = [$metricId];

if ($role === 'Social Media Manager') {
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
            AND bm.user_id = ?
        )
    ";

    $params[] = $userId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Metrics record not found or access denied.');
}

// Update metrics
$stmt = $pdo->prepare("
    UPDATE post_metrics
    SET
        recorded_at = ?,
        views = ?,
        likes = ?,
        comments = ?,
        shares = ?,
        reach = ?
    WHERE metric_id = ?
");

$stmt->execute([
    $recordedAt,
    $views,
    $likes,
    $comments,
    $shares,
    $reach,
    $metricId
]);

// Redirect
header('Location: ../metrics.php?updated=1');
exit;