
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

</head>

<body>

    <h1>SocialPulse Dashboard</h1>

    <p>
        Welcome,
        <?= escape($_SESSION['name'] ?? 'User') ?>!
    </p>

    <p>
        Your Role:
        <?= escape($role) ?>
    </p>

    <hr>

    <!-- ANALYTICS OVERVIEW -->

    <h2>Analytics Overview</h2>

    <table border="1" cellpadding="15">

        <tr>
            <th>Total Posts</th>
            <th>Total Views</th>
            <th>Total Likes</th>
        </tr>

        <tr>

            <td>
                <?= number_format((int)$analytics['total_posts']) ?>
            </td>

            <td>
                <?= number_format((int)$analytics['total_views']) ?>
            </td>

            <td>
                <?= number_format((int)$analytics['total_likes']) ?>
            </td>

        </tr>

        <tr>
            <th>Total Comments</th>
            <th>Total Shares</th>
            <th>Total Reach</th>
        </tr>

        <tr>

            <td>
                <?= number_format((int)$analytics['total_comments']) ?>
            </td>

            <td>
                <?= number_format((int)$analytics['total_shares']) ?>
            </td>

            <td>
                <?= number_format((int)$analytics['total_reach']) ?>
            </td>

        </tr>

    </table>

    <!-- ENGAGEMENT -->

    <h2>Engagement Overview</h2>

    <p>
        Total Engagement:
        <?= number_format($totalEngagement) ?>
    </p>

    <hr>

    <!-- PERFORMANCE BAR CHART -->

    <h2>Social Media Performance</h2>

    <div style="width: 100%; max-width: 800px;">

        <canvas id="performanceChart"></canvas>

    </div>

    <hr>

    <!-- PLATFORM DOUGHNUT CHART -->

    <h2>Instagram vs Facebook Performance</h2>

    <div style="width: 100%; max-width: 500px;">

        <canvas id="platformChart"></canvas>

    </div>

    <hr>

    <!-- NAVIGATION -->

    <h2>Navigation</h2>

    <?php if (hasRole('Admin')): ?>

        <p>
            <a href="admin/index.php">Admin Panel</a>
        </p>

    <?php elseif (hasRole('Social Media Manager')): ?>

        <p>
            <a href="manager/index.php">Manager Panel</a>
        </p>

    <?php elseif (hasRole('Client')): ?>

        <p>
            <a href="client/index.php">Client Panel</a>
        </p>

    <?php endif; ?>

    <?php if (hasRole('Admin') || hasRole('Social Media Manager')): ?>

        <p>
            <a href="posts.php">Manage Posts</a>
        </p>

        <p>
            <a href="metrics.php">Manage Metrics</a>
        </p>

        <p>
            <a href="imports/import.php">Import CSV</a>
        </p>

    <?php endif; ?>

    <p>
        <a href="logout.php">Logout</a>
    </p>

    <!-- CHART.JS -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>

        // BAR CHART

        const performanceCtx =
            document.getElementById('performanceChart');

        new Chart(performanceCtx, {

            type: 'bar',

            data: {

                labels: [
                    'Views',
                    'Likes',
                    'Comments',
                    'Shares'
                ],

                datasets: [{

                    label: 'Social Media Performance',

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
                    ]

                }]

            },

            options: {

                responsive: true,

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


        // DOUGHNUT CHART

        const platformCtx =
            document.getElementById('platformChart');

        new Chart(platformCtx, {

            type: 'doughnut',

            data: {

                labels: <?= json_encode($platformLabels) ?>,

                datasets: [{

                    label: 'Total Views',

                    data: <?= json_encode($platformViews) ?>,

                    backgroundColor: [
                        '#e1306c',
                        '#1877f2'
                    ]

                }]

            },

            options: {

                responsive: true,

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
