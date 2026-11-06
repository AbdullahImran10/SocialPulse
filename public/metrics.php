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

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

// --------------------------------------------------
// ADD METRICS
// --------------------------------------------------

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

            // Verify post access
            if ($role === 'Admin') {
                $stmt = $pdo->prepare("
                    SELECT post_id
                    FROM posts
                    WHERE post_id = ?
                ");
                $stmt->execute([$postId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.post_id
                    FROM posts p
                    JOIN social_accounts sa
                        ON p.account_id = sa.account_id
                    JOIN brands b
                        ON sa.brand_id = b.brand_id
                    JOIN brand_members bm
                        ON bm.brand_id = b.brand_id
                    WHERE p.post_id = ?
                    AND bm.user_id = ?
                ");
                $stmt->execute([$postId, $userId]);
            }

            if (!$stmt->fetch()) {
                $error = 'You do not have permission to add metrics for this post.';
            } else {

                $date = DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $recordedAt
                );

                if (
                    !$date ||
                    $date->format('Y-m-d\TH:i') !== $recordedAt
                ) {
                    $error = 'Please enter a valid recording date and time.';
                } else {

                    $recordedAt = $date->format('Y-m-d H:i:s');

                    $stmt = $pdo->prepare("
                        INSERT INTO post_metrics
                        (
                            post_id,
                            recorded_at,
                            views,
                            likes,
                            comments,
                            shares,
                            reach
                        )
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

if (isset($_GET['updated'])) {
    $message = 'Metrics updated successfully.';
}

if (isset($_GET['deleted'])) {
    $message = 'Metrics deleted successfully.';
}

// --------------------------------------------------
// GET ACCESSIBLE POSTS
// --------------------------------------------------

if ($role === 'Admin') {

    $stmt = $pdo->query("
        SELECT
            p.post_id,
            p.caption,
            p.published_at,
            sa.account_name,
            sa.platform,
            b.brand_name
        FROM posts p
        JOIN social_accounts sa
            ON p.account_id = sa.account_id
        JOIN brands b
            ON sa.brand_id = b.brand_id
        ORDER BY p.published_at DESC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT
            p.post_id,
            p.caption,
            p.published_at,
            sa.account_name,
            sa.platform,
            b.brand_name
        FROM posts p
        JOIN social_accounts sa
            ON p.account_id = sa.account_id
        JOIN brands b
            ON sa.brand_id = b.brand_id
        WHERE EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
            AND bm.user_id = ?
        )
        ORDER BY p.published_at DESC
    ");

    $stmt->execute([$userId]);
}

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// SEARCH AND FILTERS
// --------------------------------------------------

$search = trim($_GET['search'] ?? '');
$platform = $_GET['platform'] ?? '';

$allowedPlatforms = ['Instagram', 'Facebook'];

if (!in_array($platform, $allowedPlatforms, true)) {
    $platform = '';
}

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "(
        p.caption LIKE ?
        OR b.brand_name LIKE ?
        OR sa.account_name LIKE ?
        OR CAST(p.post_id AS CHAR) LIKE ?
    )";

    $searchValue = "%$search%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($platform !== '') {
    $where[] = "sa.platform = ?";
    $params[] = $platform;
}

// Restrict manager to assigned brands
if ($role === 'Social Media Manager') {

    $where[] = "EXISTS (
        SELECT 1
        FROM brand_members bm
        WHERE bm.brand_id = b.brand_id
        AND bm.user_id = ?
    )";

    $params[] = $userId;
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

// --------------------------------------------------
// PAGINATION
// --------------------------------------------------

$recordsPerPage = 10;

$currentPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (!$currentPage || $currentPage < 1) {
    $currentPage = 1;
}

// Total records
$countQuery = "
    SELECT COUNT(*)
    FROM post_metrics pm
    JOIN posts p ON pm.post_id = p.post_id
    JOIN social_accounts sa ON p.account_id = sa.account_id
    JOIN brands b ON sa.brand_id = b.brand_id
    $whereSQL
";

$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);

$totalRecords = (int)$stmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil($totalRecords / $recordsPerPage)
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $recordsPerPage;

// --------------------------------------------------
// GET METRICS HISTORY
// --------------------------------------------------

$metricsQuery = "
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
    $whereSQL
    ORDER BY pm.recorded_at DESC
    LIMIT $recordsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($metricsQuery);
$stmt->execute($params);

$metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination URL helper
function pageUrl($page)
{
    $query = $_GET;
    $query['page'] = $page;

    return '?' . http_build_query($query);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Analytics Management | SocialPulse</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/metrics.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>

<body>

<div class="metrics-container">

    <header class="metrics-header">

        <div>
            <h1>Analytics Management</h1>
            <p>Manage and monitor your social media performance.</p>
        </div>

        <a href="dashboard.php" class="back-btn">
            Back to Dashboard
        </a>

    </header>

    <?php if ($message): ?>
        <div class="success-message">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>


    <!-- ADD METRICS -->

    <section class="metrics-card">

        <h2>Add Post Metrics</h2>

        <?php if (empty($posts)): ?>

            <p>No accessible posts found. Add a post first or assign a brand to your account.</p>

        <?php else: ?>

            <form method="POST" class="metrics-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <div class="form-group full-width">

                    <label for="post_id">Select Post</label>

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

                </div>

                <div class="form-group">
                    <label for="views">Views</label>
                    <input type="number" name="views" id="views" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="likes">Likes</label>
                    <input type="number" name="likes" id="likes" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="comments">Comments</label>
                    <input type="number" name="comments" id="comments" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="shares">Shares</label>
                    <input type="number" name="shares" id="shares" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="reach">Reach</label>
                    <input type="number" name="reach" id="reach" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="recorded_at">Recording Date and Time</label>

                    <input
                        type="datetime-local"
                        name="recorded_at"
                        id="recorded_at"
                        value="<?= date('Y-m-d\TH:i') ?>"
                        required
                    >
                </div>

                <div class="form-group full-width">
                    <button type="submit" class="primary-btn">
                        Save Metrics
                    </button>
                </div>

            </form>

        <?php endif; ?>

    </section>


    <!-- METRICS HISTORY -->

    <section class="metrics-card">

        <div class="section-heading">
            <div>
                <h2>Metrics History</h2>
                <p><?= $totalRecords ?> records found</p>
            </div>
        </div>

        <!-- SEARCH AND FILTER -->

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                placeholder="Search post, brand or account..."
                value="<?= escape($search) ?>"
            >

            <select name="platform">

                <option value="">All Platforms</option>

                <option value="Instagram"
                    <?= $platform === 'Instagram' ? 'selected' : '' ?>>
                    Instagram
                </option>

                <option value="Facebook"
                    <?= $platform === 'Facebook' ? 'selected' : '' ?>>
                    Facebook
                </option>

            </select>

            <button type="submit" class="primary-btn">
                Search
            </button>

            <a href="metrics.php" class="reset-btn">
                Reset
            </a>

        </form>


        <?php if (empty($metrics)): ?>

            <div class="empty-state">
                <h3>No metrics found</h3>
                <p>Try changing your search or adding a metrics record.</p>
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="metrics-table">

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
                            <th>Engagement</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($metrics as $metric): ?>

                            <?php

                            $engagementRate = 0;

                            if ($metric['reach'] > 0) {
                                $engagementRate = (
                                    $metric['likes'] +
                                    $metric['comments'] +
                                    $metric['shares']
                                ) / $metric['reach'] * 100;
                            }

                            ?>

                            <tr>

                                <td>#<?= escape($metric['post_id']) ?></td>

                                <td><?= escape($metric['brand_name']) ?></td>

                                <td>
                                    <span class="platform-badge <?= strtolower(escape($metric['platform'])) ?>">
                                        <?= escape($metric['platform']) ?>
                                    </span>
                                </td>

                                <td><?= escape($metric['account_name']) ?></td>

                                <td><?= escape($metric['recorded_at']) ?></td>

                                <td><?= number_format((int)$metric['views']) ?></td>

                                <td><?= number_format((int)$metric['likes']) ?></td>

                                <td><?= number_format((int)$metric['comments']) ?></td>

                                <td><?= number_format((int)$metric['shares']) ?></td>

                                <td><?= number_format((int)$metric['reach']) ?></td>

                                <td>
                                    <?= number_format($engagementRate, 2) ?>%
                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="metrics/edit.php?id=<?= escape($metric['metric_id']) ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="metrics/delete.php"
                                        onsubmit="return confirm('Are you sure you want to delete this metrics record?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escape($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="metric_id"
                                            value="<?= escape($metric['metric_id']) ?>"
                                        >

                                        <button type="submit" class="delete-btn">
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php if ($currentPage > 1): ?>

                        <a href="<?= escape(pageUrl($currentPage - 1)) ?>">
                            Previous
                        </a>

                    <?php endif; ?>

                    <span>
                        Page <?= $currentPage ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($currentPage < $totalPages): ?>

                        <a href="<?= escape(pageUrl($currentPage + 1)) ?>">
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</div>

</body>
</html>