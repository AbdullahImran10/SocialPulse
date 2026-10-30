<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$role = getUserRole();
$userId = getUserId();

if (!in_array($role, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Build the post query based on the user's role
if ($role === 'Admin') {

    $postQuery = "
        SELECT
            p.post_id,
            p.caption,
            p.published_at,
            sa.account_name,
            sa.platform,
            b.brand_name,
            c.client_name
        FROM posts p
        JOIN social_accounts sa ON p.account_id = sa.account_id
        JOIN brands b ON sa.brand_id = b.brand_id
        JOIN clients c ON b.client_id = c.client_id
        ORDER BY p.published_at DESC
    ";

    $stmt = $pdo->query($postQuery);

} else {

    $postQuery = "
        SELECT
            p.post_id,
            p.caption,
            p.published_at,
            sa.account_name,
            sa.platform,
            b.brand_name,
            c.client_name
        FROM posts p
        JOIN social_accounts sa ON p.account_id = sa.account_id
        JOIN brands b ON sa.brand_id = b.brand_id
        JOIN clients c ON b.client_id = c.client_id
        JOIN brand_members bm ON bm.brand_id = b.brand_id
        WHERE bm.user_id = ?
        ORDER BY p.published_at DESC
    ";

    $stmt = $pdo->prepare($postQuery);
    $stmt->execute([$userId]);
}

$posts = $stmt->fetchAll();

// Handle metric submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please try again.';
    } else {

        $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

        $views = filter_input(INPUT_POST, 'views', FILTER_VALIDATE_INT);
        $likes = filter_input(INPUT_POST, 'likes', FILTER_VALIDATE_INT);
        $comments = filter_input(INPUT_POST, 'comments', FILTER_VALIDATE_INT);
        $shares = filter_input(INPUT_POST, 'shares', FILTER_VALIDATE_INT);
        $reach = filter_input(INPUT_POST, 'reach', FILTER_VALIDATE_INT);

        $recordedAt = trim($_POST['recorded_at'] ?? '');

        if (!$postId) {
            $error = 'Please select a valid post.';
        } elseif (
            $views === false || $views === null || $views < 0 ||
            $likes === false || $likes === null || $likes < 0 ||
            $comments === false || $comments === null || $comments < 0 ||
            $shares === false || $shares === null || $shares < 0 ||
            $reach === false || $reach === null || $reach < 0
        ) {
            $error = 'All metrics must be valid non-negative numbers.';
        } else {

            // Check that the selected post is accessible to this user
            if ($role === 'Admin') {

                $stmt = $pdo->prepare("
                    SELECT p.post_id
                    FROM posts p
                    WHERE p.post_id = ?
                ");

                $stmt->execute([$postId]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT p.post_id
                    FROM posts p
                    JOIN social_accounts sa ON p.account_id = sa.account_id
                    JOIN brands b ON sa.brand_id = b.brand_id
                    JOIN brand_members bm ON bm.brand_id = b.brand_id
                    WHERE p.post_id = ?
                      AND bm.user_id = ?
                ");

                $stmt->execute([$postId, $userId]);
            }

            if (!$stmt->fetch()) {
                $error = 'You do not have permission to add metrics for this post.';
            } else {

                // Validate recording date
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $recordedAt);

                if (!$date || $date->format('Y-m-d\TH:i') !== $recordedAt) {
                    $error = 'Please enter a valid recording date and time.';
                } else {

                    $recordedAt = $date->format('Y-m-d H:i:s');

                    $stmt = $pdo->prepare("
                        INSERT INTO post_metrics
                        (post_id, recorded_at, views, likes, comments, shares, reach)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $postId,
                        $recordedAt,
                        $views,
                        $likes,
                        $comments,
                        $shares,
                        $reach
                    ]);

                    $message = 'Post metrics saved successfully.';

                    // Refresh the posts list
                    header('Location: metrics.php?success=1');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Post metrics saved successfully.';
}

// Get metrics history
if ($role === 'Admin') {

    $stmt = $pdo->query("
        SELECT
            pm.metric_id,
            pm.recorded_at,
            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            p.post_id,
            p.caption,
            b.brand_name,
            sa.account_name,
            sa.platform
        FROM post_metrics pm
        JOIN posts p ON pm.post_id = p.post_id
        JOIN social_accounts sa ON p.account_id = sa.account_id
        JOIN brands b ON sa.brand_id = b.brand_id
        ORDER BY pm.recorded_at DESC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT
            pm.metric_id,
            pm.recorded_at,
            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            p.post_id,
            p.caption,
            b.brand_name,
            sa.account_name,
            sa.platform
        FROM post_metrics pm
        JOIN posts p ON pm.post_id = p.post_id
        JOIN social_accounts sa ON p.account_id = sa.account_id
        JOIN brands b ON sa.brand_id = b.brand_id
        JOIN brand_members bm ON bm.brand_id = b.brand_id
        WHERE bm.user_id = ?
        ORDER BY pm.recorded_at DESC
    ");

    $stmt->execute([$userId]);
}

$metrics = $stmt->fetchAll();

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Metrics</title>
</head>

<body>

    <h1>Post Metrics</h1>

    <p><a href="dashboard.php">Back to Dashboard</a></p>

    <?php if ($message): ?>
        <p><?= escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p><?= escape($error) ?></p>
    <?php endif; ?>

    <h2>Add Post Metrics</h2>

    <?php if (empty($posts)): ?>

        <p>No accessible posts found. Add a post first or assign a brand to your account.</p>

    <?php else: ?>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= escape($_SESSION['csrf_token']) ?>">

            <label for="post_id">Select Post:</label>

            <select name="post_id" id="post_id" required>

                <option value="">Select Post</option>

                <?php foreach ($posts as $post): ?>

                    <option value="<?= escape($post['post_id']) ?>">
                        <?= escape($post['brand_name']) ?>
                        -
                        <?= escape($post['platform']) ?>
                        -
                        <?= escape($post['account_name']) ?>
                        -
                        Post #<?= escape($post['post_id']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <br><br>

            <label for="views">Views:</label>
            <input type="number" name="views" id="views" min="0" value="0" required>

            <br><br>

            <label for="likes">Likes:</label>
            <input type="number" name="likes" id="likes" min="0" value="0" required>

            <br><br>

            <label for="comments">Comments:</label>
            <input type="number" name="comments" id="comments" min="0" value="0" required>

            <br><br>

            <label for="shares">Shares:</label>
            <input type="number" name="shares" id="shares" min="0" value="0" required>

            <br><br>

            <label for="reach">Reach:</label>
            <input type="number" name="reach" id="reach" min="0" value="0" required>

            <br><br>

            <label for="recorded_at">Recording Date and Time:</label>

            <input
                type="datetime-local"
                name="recorded_at"
                id="recorded_at"
                value="<?= date('Y-m-d\TH:i') ?>"
                required
            >

            <br><br>

            <button type="submit">Save Metrics</button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>Metrics History</h2>

    <?php if (empty($metrics)): ?>

        <p>No metrics have been recorded yet.</p>

    <?php else: ?>

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>Post ID</th>
                    <th>Brand</th>
                    <th>Platform</th>
                    <th>Account</th>
                    <th>Recorded At</th>
                    <th>Views</th>
                    <th>Likes</th>
                    <th>Comments</th>
                    <th>Shares</th>
                    <th>Reach</th>
                    <th>Engagement Rate</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($metrics as $metric): ?>

                    <?php
                    $engagementRate = 0;

                    if ($metric['reach'] > 0) {
                        $engagementRate =
                            (
                                $metric['likes'] +
                                $metric['comments'] +
                                $metric['shares']
                            ) / $metric['reach'] * 100;
                    }
                    ?>

                    <tr>

                        <td><?= escape($metric['post_id']) ?></td>

                        <td><?= escape($metric['brand_name']) ?></td>

                        <td><?= escape($metric['platform']) ?></td>

                        <td><?= escape($metric['account_name']) ?></td>

                        <td><?= escape($metric['recorded_at']) ?></td>

                        <td><?= escape($metric['views']) ?></td>

                        <td><?= escape($metric['likes']) ?></td>

                        <td><?= escape($metric['comments']) ?></td>

                        <td><?= escape($metric['shares']) ?></td>

                        <td><?= escape($metric['reach']) ?></td>

                        <td><?= number_format($engagementRate, 2) ?>%</td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</body>
</html>