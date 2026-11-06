<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

// Only Admin and Social Media Managers can view reports.

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Validate report ID.
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId <= 0) {
    http_response_code(400);
    exit('Invalid report ID.');
}

$userId = getUserId();
$userRole = getUserRole();
if (!in_array($userRole, ['Admin', 'Social Media Manager', 'Client'], true)) {
    http_response_code(403);
    exit('Access denied.');
}
try {

    // Get report and verify user access.
    if ($userRole === 'Admin') {

        $stmt = $pdo->prepare("
        SELECT r.*, b.brand_name
        FROM reports r
        JOIN brands b ON r.brand_id = b.brand_id
        WHERE r.report_id = ?
    ");

        $stmt->execute([$reportId]);
    } elseif ($userRole === 'Social Media Manager') {

        $stmt = $pdo->prepare("
        SELECT r.*, b.brand_name
        FROM reports r
        JOIN brands b ON r.brand_id = b.brand_id
        JOIN brand_members bm ON bm.brand_id = r.brand_id
        WHERE r.report_id = ?
        AND bm.user_id = ?
    ");

        $stmt->execute([$reportId, $userId]);
    } else {

        $stmt = $pdo->prepare("
        SELECT r.*, b.brand_name
        FROM reports r
        JOIN brands b ON r.brand_id = b.brand_id
        JOIN users u ON u.client_id = b.client_id
        WHERE r.report_id = ?
        AND u.user_id = ?
    ");

        $stmt->execute([$reportId, $userId]);
    }

    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        exit('Report not found or access denied.');
    }

    // Get the latest metric record for each post
    // within the report's selected date range.
    $sql = "
        SELECT
            p.post_id,
            p.caption,
            p.post_type,
            p.published_at,
            p.post_url,
            sa.platform,
            sa.account_name,
            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            pm.recorded_at

        FROM post_metrics pm

        JOIN posts p
            ON pm.post_id = p.post_id

        JOIN social_accounts sa
            ON p.account_id = sa.account_id

        WHERE sa.brand_id = :brand_id
        AND DATE(pm.recorded_at) BETWEEN :start_outer AND :end_outer

        AND (
            :platform_outer = 'All'
            OR sa.platform = :platform_filter
        )

        AND NOT EXISTS (
            SELECT 1
            FROM post_metrics newer

            WHERE newer.post_id = pm.post_id

            AND DATE(newer.recorded_at)
                BETWEEN :start_inner AND :end_inner

            AND (
                newer.recorded_at > pm.recorded_at
                OR (
                    newer.recorded_at = pm.recorded_at
                    AND newer.metric_id > pm.metric_id
                )
            )
        )

        ORDER BY pm.views DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':brand_id' => $report['brand_id'],
        ':start_outer' => $report['start_date'],
        ':end_outer' => $report['end_date'],
        ':platform_outer' => $report['platform'],
        ':platform_filter' => $report['platform'],
        ':start_inner' => $report['start_date'],
        ':end_inner' => $report['end_date']
    ]);

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals.
    $totalViews = 0;
    $totalLikes = 0;
    $totalComments = 0;
    $totalShares = 0;
    $totalReach = 0;

    foreach ($posts as $post) {
        $totalViews += (int)$post['views'];
        $totalLikes += (int)$post['likes'];
        $totalComments += (int)$post['comments'];
        $totalShares += (int)$post['shares'];
        $totalReach += (int)$post['reach'];
    }

    $totalEngagements = $totalLikes + $totalComments + $totalShares;

    $engagementRate = $totalReach > 0
        ? ($totalEngagements / $totalReach) * 100
        : 0;
} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to load report.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= escape($report['report_name']) ?> - SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

    <div class="reports-container">

        <div class="reports-header">

            <div>
                <h1><?= escape($report['report_name']) ?></h1>

                <p>
                    Brand: <?= escape($report['brand_name']) ?>
                </p>

                <p>
                    Period:
                    <?= escape($report['start_date']) ?>
                    to
                    <?= escape($report['end_date']) ?>
                </p>

                <p>
                    Platform: <?= escape($report['platform']) ?>
                </p>
            </div>

            <a href="../reports.php" class="back-btn">
                Back to Reports
            </a>

        </div>


        <!-- Summary Cards -->

        <div class="report-card">

            <h2>Performance Summary</h2>

            <div class="report-summary">

                <div class="summary-item">
                    <h3>Total Posts</h3>
                    <p><?= count($posts) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Views</h3>
                    <p><?= number_format($totalViews) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Likes</h3>
                    <p><?= number_format($totalLikes) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Comments</h3>
                    <p><?= number_format($totalComments) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Shares</h3>
                    <p><?= number_format($totalShares) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Reach</h3>
                    <p><?= number_format($totalReach) ?></p>
                </div>

                <div class="summary-item">
                    <h3>Engagement Rate</h3>
                    <p><?= number_format($engagementRate, 2) ?>%</p>
                </div>

            </div>

        </div>


        <!-- Individual Post Performance -->

        <div class="report-card">

            <h2>Post Performance</h2>

            <div class="reports-table-container">

                <table class="reports-table">

                    <thead>
                        <tr>
                            <th>Post ID</th>
                            <th>Platform</th>
                            <th>Account</th>
                            <th>Caption</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Comments</th>
                            <th>Shares</th>
                            <th>Reach</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($posts)): ?>

                            <tr>
                                <td colspan="9" class="empty-state">
                                    No post metrics found for this report.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($posts as $post): ?>

                                <tr>

                                    <td>
                                        #<?= escape($post['post_id']) ?>
                                    </td>

                                    <td>
                                        <?= escape($post['platform']) ?>
                                    </td>

                                    <td>
                                        <?= escape($post['account_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($post['caption']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($post['views']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($post['likes']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($post['comments']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($post['shares']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($post['reach']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</body>

</html>