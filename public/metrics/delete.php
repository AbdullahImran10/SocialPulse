<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$role = getUserRole();
$userId = getUserId();

// Role authorization
if (!in_array($role, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// CSRF validation
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

// Verify access to the analytics record
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

// Delete analytics record
$stmt = $pdo->prepare("
    DELETE FROM post_metrics
    WHERE metric_id = ?
");

$stmt->execute([$metricId]);

// Redirect
header('Location: ../metrics.php?deleted=1');
exit;