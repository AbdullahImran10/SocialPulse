<?php

require_once __DIR__ . '/../../includes/auth.php';
requireRole('Client');

require_once __DIR__ . '/../../config/database.php';

$userId = getUserId();

// Get the logged-in client's client_id
$stmt = $pdo->prepare("
    SELECT client_id
    FROM users
    WHERE user_id = ?
");

$stmt->execute([$userId]);
$client = $stmt->fetch();

if (!$client || !$client['client_id']) {
    die("Client account not found.");
}

$clientId = (int)$client['client_id'];

// Get authorized brands
$stmt = $pdo->prepare("
    SELECT
        b.brand_id,
        b.brand_name,
        COUNT(DISTINCT sa.account_id) AS account_count,
        COUNT(DISTINCT p.post_id) AS post_count
    FROM brands b
    LEFT JOIN social_accounts sa
        ON sa.brand_id = b.brand_id
    LEFT JOIN posts p
        ON p.account_id = sa.account_id
    WHERE b.client_id = ?
    GROUP BY b.brand_id, b.brand_name
    ORDER BY b.brand_name
");

$stmt->execute([$clientId]);
$brands = $stmt->fetchAll();

// Get connected social accounts
$stmt = $pdo->prepare("
    SELECT
        sa.account_id,
        sa.platform,
        sa.account_name,
        b.brand_name
    FROM social_accounts sa
    INNER JOIN brands b
        ON b.brand_id = sa.brand_id
    WHERE b.client_id = ?
    ORDER BY b.brand_name, sa.platform
");

$stmt->execute([$clientId]);
$accounts = $stmt->fetchAll();

// Get recent posts with latest available metrics
$stmt = $pdo->prepare("
    SELECT
        p.post_id,
        p.caption,
        p.post_type,
        p.published_at,
        p.post_url,
        sa.platform,
        sa.account_name,
        b.brand_name,
        COALESCE(pm.views, 0) AS views,
        COALESCE(pm.likes, 0) AS likes,
        COALESCE(pm.comments, 0) AS comments,
        COALESCE(pm.shares, 0) AS shares
    FROM posts p
    INNER JOIN social_accounts sa
        ON sa.account_id = p.account_id
    INNER JOIN brands b
        ON b.brand_id = sa.brand_id
    LEFT JOIN post_metrics pm
        ON pm.metric_id = (
            SELECT pm2.metric_id
            FROM post_metrics pm2
            WHERE pm2.post_id = p.post_id
            ORDER BY pm2.recorded_at DESC, pm2.metric_id DESC
            LIMIT 1
        )
    WHERE b.client_id = ?
    ORDER BY p.published_at DESC
    LIMIT 6
");

$stmt->execute([$clientId]);
$recentPosts = $stmt->fetchAll();

// Get available reports
$stmt = $pdo->prepare("
    SELECT
        r.report_id,
        r.report_name,
        r.start_date,
        r.end_date,
        r.platform,
        b.brand_name
    FROM reports r
    INNER JOIN brands b
        ON b.brand_id = r.brand_id
    WHERE b.client_id = ?
    ORDER BY r.report_id DESC
    LIMIT 6
");

$stmt->execute([$clientId]);
$reports = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Workspace | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/client.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

<div class="client-container">

    <!-- HEADER -->
    <header class="client-header">

        <div>
            <span class="eyebrow">SOCIALPULSE</span>
            <h1>My Workspace</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?>!
            </p>
        </div>

        <div class="header-actions">
            <a href="../dashboard.php" class="btn-secondary">
                Main Dashboard
            </a>

            <a href="../logout.php" class="btn-danger">
                Logout
            </a>
        </div>

    </header>


    <!-- BRANDS -->
    <section class="client-section">

        <div class="section-heading">
            <div>
                <h2>My Brands</h2>
                <p>Brands associated with your account.</p>
            </div>

            <span class="section-count">
                <?= count($brands) ?> Brands
            </span>
        </div>

        <?php if ($brands): ?>

            <div class="brand-grid">

                <?php foreach ($brands as $brand): ?>

                    <div class="brand-card">

                        <div class="brand-icon">
                            <?= strtoupper(substr($brand['brand_name'], 0, 1)) ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars($brand['brand_name']) ?>
                        </h3>

                        <div class="brand-details">

                            <span>
                                <?= (int)$brand['account_count'] ?>
                                Social Accounts
                            </span>

                            <span>
                                <?= (int)$brand['post_count'] ?>
                                Posts
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No brands have been assigned to your account yet.
            </div>

        <?php endif; ?>

    </section>


    <!-- SOCIAL ACCOUNTS -->
    <section class="client-section">

        <div class="section-heading">
            <div>
                <h2>Connected Accounts</h2>
                <p>Your brands' social media accounts.</p>
            </div>

            <span class="section-count">
                <?= count($accounts) ?> Accounts
            </span>
        </div>

        <?php if ($accounts): ?>

            <div class="account-grid">

                <?php foreach ($accounts as $account): ?>

                    <div class="account-card">

                        <div class="account-platform">
                            <?= htmlspecialchars($account['platform']) ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars($account['account_name']) ?>
                        </h3>

                        <p>
                            Brand:
                            <?= htmlspecialchars($account['brand_name']) ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No social accounts are connected yet.
            </div>

        <?php endif; ?>

    </section>


    <!-- RECENT POSTS -->
    <section class="client-section">

        <div class="section-heading">
            <div>
                <h2>Recent Posts</h2>
                <p>Recently published content from your brands.</p>
            </div>

            <a href="../posts.php" class="text-link">
                View Posts →
            </a>
        </div>

        <?php if ($recentPosts): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Brand</th>
                            <th>Platform</th>
                            <th>Published</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Comments</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentPosts as $post): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        substr($post['caption'] ?? '', 0, 45)
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($post['brand_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($post['platform']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $post['published_at'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td><?= number_format((int)$post['views']) ?></td>
                                <td><?= number_format((int)$post['likes']) ?></td>
                                <td><?= number_format((int)$post['comments']) ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No posts are available yet.
            </div>

        <?php endif; ?>

    </section>


    <!-- REPORTS -->
    <section class="client-section">

        <div class="section-heading">
            <div>
                <h2>My Reports</h2>
                <p>Reports generated for your brands.</p>
            </div>

            <a href="../reports.php" class="text-link">
                View Reports →
            </a>
        </div>

        <?php if ($reports): ?>

            <div class="report-list">

                <?php foreach ($reports as $report): ?>

                    <div class="report-card">

                        <div>
                            <h3>
                                <?= htmlspecialchars($report['report_name']) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars($report['brand_name']) ?>
                                ·
                                <?= htmlspecialchars($report['platform']) ?>
                            </p>

                            <small>
                                <?= htmlspecialchars($report['start_date']) ?>
                                to
                                <?= htmlspecialchars($report['end_date']) ?>
                            </small>
                        </div>

                        <a
                            href="../reports/view.php?id=<?= (int)$report['report_id'] ?>"
                            class="btn-secondary"
                        >
                            View Report
                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No reports are available yet.
            </div>

        <?php endif; ?>

    </section>


    <footer class="client-footer">
        SocialPulse &copy; <?= date('Y') ?>. All rights reserved.
    </footer>

</div>

</body>
</html>