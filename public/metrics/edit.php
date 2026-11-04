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

// Validate metric ID
$metricId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$metricId || $metricId < 1) {
    http_response_code(400);
    exit('Invalid metrics ID.');
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch metric with authorization
$sql = "
    SELECT
        pm.metric_id,
        pm.post_id,
        pm.recorded_at,
        pm.views,
        pm.likes,
        pm.comments,
        pm.shares,
        pm.reach,
        p.caption,
        b.brand_name,
        sa.platform,
        sa.account_name
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

$metric = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$metric) {
    http_response_code(404);
    exit('Metrics record not found or access denied.');
}

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$recordedAt = date(
    'Y-m-d\TH:i',
    strtotime($metric['recorded_at'])
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Metrics | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/metrics.css">
</head>

<body>

<div class="edit-metrics-container">

    <a href="../metrics.php" class="back-btn">
        ← Back to Analytics
    </a>

    <h2>Edit Analytics Record</h2>
    <p>Update the performance metrics for this post.</p>

    <!-- Post Information Card -->
    <div class="metric-info">
        <h3><?= escape($metric['brand_name']) ?> - <?= escape($metric['platform']) ?></h3>
        
        <p><strong>Account:</strong> <span><?= escape($metric['account_name']) ?></span></p>
        <p><strong>Post ID:</strong> <span>#<?= escape($metric['post_id']) ?></span></p>
        <p><strong>Caption:</strong> <span><?= escape($metric['caption']) ?></span></p>
    </div>

    <!-- Edit Metrics Form -->
    <form method="POST" action="update.php" class="edit-metrics-form">

        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="metric_id" value="<?= escape($metric['metric_id']) ?>">

        <div class="form-group">
            <label for="views">Views</label>
            <input type="number" name="views" id="views" min="0" value="<?= escape($metric['views']) ?>" required>
        </div>

        <div class="form-group">
            <label for="likes">Likes</label>
            <input type="number" name="likes" id="likes" min="0" value="<?= escape($metric['likes']) ?>" required>
        </div>

        <div class="form-group">
            <label for="comments">Comments</label>
            <input type="number" name="comments" id="comments" min="0" value="<?= escape($metric['comments']) ?>" required>
        </div>

        <div class="form-group">
            <label for="shares">Shares</label>
            <input type="number" name="shares" id="shares" min="0" value="<?= escape($metric['shares']) ?>" required>
        </div>

        <div class="form-group">
            <label for="reach">Reach</label>
            <input type="number" name="reach" id="reach" min="0" value="<?= escape($metric['reach']) ?>" required>
        </div>

        <div class="form-group">
            <label for="recorded_at">Recording Date and Time</label>
            <input type="datetime-local" name="recorded_at" id="recorded_at" value="<?= escape($recordedAt) ?>" required>
        </div>

        <div class="edit-form-actions">
            <button type="submit" class="save-metrics-btn">Update Metrics</button>
            <a href="../metrics.php" class="cancel-btn">Cancel</a>
        </div>

    </form>

</div>

</body>
</html>