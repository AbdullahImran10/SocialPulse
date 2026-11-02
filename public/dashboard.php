
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$userId = getUserId();
$role = getUserRole();

/*
|--------------------------------------------------------------------------
| 1. MAIN ANALYTICS QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(DISTINCT p.post_id) AS total_posts,

        COALESCE(SUM(pm.views), 0) AS total_views,
        COALESCE(SUM(pm.likes), 0) AS total_likes,
        COALESCE(SUM(pm.comments), 0) AS total_comments,
        COALESCE(SUM(pm.shares), 0) AS total_shares,
        COALESCE(SUM(pm.reach), 0) AS total_reach

    FROM posts p

    JOIN social_accounts sa
        ON p.account_id = sa.account_id

    JOIN brands b
        ON sa.brand_id = b.brand_id

    LEFT JOIN post_metrics pm
        ON pm.metric_id = (
            SELECT pm2.metric_id
            FROM post_metrics pm2
            WHERE pm2.post_id = p.post_id
            ORDER BY pm2.recorded_at DESC, pm2.metric_id DESC
            LIMIT 1
        )
";

// Role-based filtering
$params = [];

if ($role === 'Social Media Manager') {

    $sql .= "
        WHERE EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
            AND bm.user_id = ?
        )
    ";

    $params[] = $userId;

} elseif ($role === 'Client') {

    $sql .= "
        WHERE b.client_id = (
            SELECT client_id
            FROM users
            WHERE user_id = ?
        )
    ";

    $params[] = $userId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$analytics = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| 2. DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$analytics = array_merge([
    'total_posts' => 0,
    'total_views' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
    'total_shares' => 0,
    'total_reach' => 0
], $analytics ?: []);

$totalEngagement =
    (int)$analytics['total_likes'] +
    (int)$analytics['total_comments'] +
    (int)$analytics['total_shares'];

/*
|--------------------------------------------------------------------------
| 3. PLATFORM COMPARISON QUERY
|--------------------------------------------------------------------------
*/

$platformSql = "
    SELECT
        sa.platform,
        COALESCE(SUM(pm.views), 0) AS total_views

    FROM social_accounts sa

    JOIN brands b
        ON sa.brand_id = b.brand_id

    JOIN posts p
        ON p.account_id = sa.account_id

    LEFT JOIN post_metrics pm
        ON pm.metric_id = (
            SELECT pm2.metric_id
            FROM post_metrics pm2
            WHERE pm2.post_id = p.post_id
            ORDER BY pm2.recorded_at DESC, pm2.metric_id DESC
            LIMIT 1
        )
";

// Apply the same role restrictions
if ($role === 'Social Media Manager') {

    $platformSql .= "
        WHERE EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
            AND bm.user_id = ?
        )
    ";

} elseif ($role === 'Client') {

    $platformSql .= "
        WHERE b.client_id = (
            SELECT client_id
            FROM users
            WHERE user_id = ?
        )
    ";
}

$platformSql .= "
    GROUP BY sa.platform
    ORDER BY sa.platform
";

$platformStmt = $pdo->prepare($platformSql);
$platformStmt->execute($params);

$platformData = $platformStmt->fetchAll(PDO::FETCH_ASSOC);

$platformLabels = [];
$platformViews = [];

foreach ($platformData as $row) {

    $platformLabels[] = $row['platform'];

    $platformViews[] = (int)$row['total_views'];
}

/*
|--------------------------------------------------------------------------
| 4. ESCAPE OUTPUT
|--------------------------------------------------------------------------
*/

function escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SocialPulse Dashboard</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">SocialPulse.</div>

        <h4>Menu</h4>

        <a href="dashboard.php" class="active">
            <span>Dashboard</span>
        </a>

        <?php if (hasRole('Admin')): ?>

            <a href="admin/index.php">
                <span>Admin Panel</span>
            </a>

        <?php elseif (hasRole('Social Media Manager')): ?>

            <a href="manager/index.php">
                <span>Manager Panel</span>
            </a>

        <?php elseif (hasRole('Client')): ?>

            <a href="client/index.php">
                <span>Client Panel</span>
            </a>

        <?php endif; ?>

        <?php if (hasRole('Admin') || hasRole('Social Media Manager')): ?>

            <h4>Management</h4>

            <a href="posts.php"><span>Manage Posts</span></a>

            <a href="metrics.php"><span>Manage Metrics</span></a>

            <a href="imports/import.php"><span>Import CSV</span></a>

        <?php endif; ?>

        <h4>Account</h4>

        <a href="logout.php">
            <span>Logout</span>
        </a>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <!-- HEADER -->

        <header class="topbar">

            <h1>Dashboard Overview</h1>

            <div class="user-info">

                Welcome,
                <?= escape($_SESSION['name'] ?? 'User') ?>

                |
                <?= escape($role) ?>

            </div>

        </header>


        <!-- ANALYTICS CARDS -->

        <section class="analytics-grid">

            <div class="analytics-card">
                <p>Total Posts</p>
                <h2><?= number_format((int)$analytics['total_posts']) ?></h2>
            </div>

            <div class="analytics-card">
                <p>Total Views</p>
                <h2><?= number_format((int)$analytics['total_views']) ?></h2>
            </div>

            <div class="analytics-card">
                <p>Total Reach</p>
                <h2><?= number_format((int)$analytics['total_reach']) ?></h2>
            </div>

            <div class="analytics-card">
                <p>Total Engagement</p>
                <h2><?= number_format($totalEngagement) ?></h2>
            </div>

        </section>


        <!-- CHARTS -->

        <section class="charts-grid">

            <div class="chart-card">

                <h3>Social Media Performance</h3>

                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>

            </div>

            <div class="chart-card">

                <h3>Instagram vs Facebook</h3>

                <div class="chart-container">
                    <canvas id="platformChart"></canvas>
                </div>

            </div>

        </section>


        <!-- ADDITIONAL METRICS -->

        <section class="analytics-grid">

            <div class="analytics-card">
                <p>Total Likes</p>
                <h2><?= number_format((int)$analytics['total_likes']) ?></h2>
            </div>

            <div class="analytics-card">
                <p>Total Comments</p>
                <h2><?= number_format((int)$analytics['total_comments']) ?></h2>
            </div>

            <div class="analytics-card">
                <p>Total Shares</p>
                <h2><?= number_format((int)$analytics['total_shares']) ?></h2>
            </div>

        </section>

    </main>

</div>


<!-- CHART.JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    // Performance Chart

    new Chart(document.getElementById('performanceChart'), {

        type: 'bar',

        data: {

            labels: ['Views', 'Likes', 'Comments', 'Shares'],

            datasets: [{

                label: 'Performance',

                data: [
                    <?= (int)$analytics['total_views'] ?>,
                    <?= (int)$analytics['total_likes'] ?>,
                    <?= (int)$analytics['total_comments'] ?>,
                    <?= (int)$analytics['total_shares'] ?>
                ],

                backgroundColor: [
                    '#6366f1',
                    '#ec4899',
                    '#14b8a6',
                    '#f59e0b'
                ],

                borderRadius: 6

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                }
            },

            scales: {
                y: {
                    beginAtZero: true
                }
            }

        }

    });


    // Platform Chart

    new Chart(document.getElementById('platformChart'), {

        type: 'doughnut',

        data: {

            labels: <?= json_encode($platformLabels) ?>,

            datasets: [{

                data: <?= json_encode($platformViews) ?>,

                backgroundColor: [
                    '#e1306c',
                    '#1877f2'
                ],

                borderWidth: 0

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            }

        }

    });

</script>

</body>
</html>