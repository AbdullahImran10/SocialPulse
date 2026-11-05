<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();


// ==========================================
// 1. USER INFORMATION
// ==========================================

$userId = getUserId();
$userRole = getUserRole();


// ==========================================
// 2. DATE FILTER
// ==========================================

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$start = DateTime::createFromFormat('!Y-m-d', $startDate);
$end = DateTime::createFromFormat('!Y-m-d', $endDate);

if (
    !$start ||
    !$end ||
    $start->format('Y-m-d') !== $startDate ||
    $end->format('Y-m-d') !== $endDate ||
    $startDate > $endDate ||
    $endDate > date('Y-m-d')
) {
    $startDate = date('Y-m-d', strtotime('-29 days'));
    $endDate = date('Y-m-d');
}


// ==========================================
// 3. ROLE-BASED ACCESS CONDITIONS
// ==========================================

$accessCondition = "";
$accessParams = [];

if ($userRole === 'Social Media Manager') {

    $accessCondition = "
        AND EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
            AND bm.user_id = ?
        )
    ";

    $accessParams[] = $userId;
} elseif ($userRole === 'Client') {

    $accessCondition = "
        AND b.client_id = (
            SELECT client_id
            FROM users
            WHERE user_id = ?
        )
    ";

    $accessParams[] = $userId;
} elseif ($userRole !== 'Admin') {

    http_response_code(403);
    exit('Access denied.');
}


// ==========================================
// 4. MAIN ANALYTICS QUERY
// ==========================================

$sql = "
    SELECT

        COUNT(DISTINCT p.post_id) AS total_posts,

        COALESCE(SUM(pm.views), 0) AS total_views,

        COALESCE(SUM(pm.likes), 0) AS total_likes,

        COALESCE(SUM(pm.comments), 0) AS total_comments,

        COALESCE(SUM(pm.shares), 0) AS total_shares,

        COALESCE(SUM(pm.reach), 0) AS total_reach

    FROM brands b

    INNER JOIN social_accounts sa
        ON sa.brand_id = b.brand_id

    INNER JOIN posts p
        ON p.account_id = sa.account_id

    LEFT JOIN post_metrics pm
        ON pm.metric_id = (

            SELECT pm2.metric_id

            FROM post_metrics pm2

            WHERE pm2.post_id = p.post_id

            AND pm2.recorded_at >= ?

            AND pm2.recorded_at < DATE_ADD(?, INTERVAL 1 DAY)

            ORDER BY
                pm2.recorded_at DESC,
                pm2.metric_id DESC

            LIMIT 1
        )

    WHERE 1 = 1

    $accessCondition
";

$params = array_merge(
    [$startDate, $endDate],
    $accessParams
);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$analytics = $stmt->fetch(PDO::FETCH_ASSOC);


// ==========================================
// 5. DEFAULT ANALYTICS VALUES
// ==========================================

$analytics = array_merge([

    'total_posts' => 0,
    'total_views' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
    'total_shares' => 0,
    'total_reach' => 0

], $analytics ?: []);

$totalEngagement =
    $analytics['total_likes'] +
    $analytics['total_comments'] +
    $analytics['total_shares'];


// ==========================================
// 6. PLATFORM COMPARISON QUERY
// ==========================================

$platformSql = "

    SELECT

        sa.platform,

        COALESCE(SUM(pm.views), 0) AS total_views

    FROM brands b

    INNER JOIN social_accounts sa
        ON sa.brand_id = b.brand_id

    INNER JOIN posts p
        ON p.account_id = sa.account_id

    LEFT JOIN post_metrics pm
        ON pm.metric_id = (

            SELECT pm2.metric_id

            FROM post_metrics pm2

            WHERE pm2.post_id = p.post_id

            AND pm2.recorded_at >= ?

            AND pm2.recorded_at < DATE_ADD(?, INTERVAL 1 DAY)

            ORDER BY
                pm2.recorded_at DESC,
                pm2.metric_id DESC

            LIMIT 1
        )

    WHERE 1 = 1

    $accessCondition

    GROUP BY sa.platform

";

$platformParams = array_merge(
    [$startDate, $endDate],
    $accessParams
);

$platformStmt = $pdo->prepare($platformSql);
$platformStmt->execute($platformParams);

$platformData = $platformStmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 7. PREPARE CHART DATA
// ==========================================

$platformLabels = [];
$platformViews = [];

foreach ($platformData as $row) {

    $platformLabels[] = $row['platform'];

    $platformViews[] = (int) $row['total_views'];
}


// ==========================================
// 8. HELPER FUNCTION
// ==========================================

function escape($value)
{
    return htmlspecialchars(
        (string) $value,
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>


    <!-- ======================================
     SIDEBAR
======================================= -->

    <div class="dashboard-container">

        <aside class="sidebar">

            <h2>SocialPulse</h2>

            <nav>

                <a href="dashboard.php" class="active">
                    Dashboard
                </a>

                <?php if ($userRole === 'Admin' || $userRole === 'Social Media Manager'): ?>

                    <a href="posts.php">
                        Posts
                    </a>

                    <a href="metrics.php">
                        Analytics
                    </a>

                <?php endif; ?>


                <?php if ($userRole === 'Admin'): ?>

                    <a href="admin/index.php">
                        Admin Panel
                    </a>

                    <a href="admin/users.php">
                        Users
                    </a>

                    <a href="admin/brands.php">
                        Brands
                    </a>

                    <a href="admin/clients.php">
                        Clients
                    </a>

                    <a href="admin/social_accounts.php">
                        Social Accounts
                    </a>

                    <a href="admin/brand_members.php">
                        Brand Members
                    </a>

                    <a href="reports.php">
                        Reports
                    </a>
                    <a href="admin/audit_logs.php">Audit Logs</a>

                <?php elseif ($userRole === 'Social Media Manager'): ?>

                    <a href="manager/index.php">
                        Manager Panel
                    </a>

                    <a href="reports.php">
                        Reports
                    </a>

                <?php elseif ($userRole === 'Client'): ?>

                    <a href="client/index.php">
                        My Account
                    </a>

                    <a href="reports.php">
                        My Reports
                    </a>

                <?php endif; ?>


                <a href="logout.php">
                    Logout
                </a>

            </nav>

        </aside>

        <!-- ======================================
         MAIN CONTENT
    ======================================= -->

        <main class="main-content">


            <!-- HEADER -->

            <header class="topbar">

                <div>

                    <h1>Dashboard</h1>

                    <p>
                        Welcome to your SocialPulse dashboard.
                    </p>

                </div>

                <div class="user-info">

                    <span>
                        <?= escape($userRole) ?>
                    </span>

                </div>

            </header>


            <!-- ======================================
             DATE FILTER
        ======================================= -->

            <section class="filter-section">

                <form method="GET" class="date-filter">

                    <div>

                        <label for="start_date">
                            Start Date
                        </label>

                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value="<?= escape($startDate) ?>"
                            required>

                    </div>


                    <div>

                        <label for="end_date">
                            End Date
                        </label>

                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            value="<?= escape($endDate) ?>"
                            required>

                    </div>


                    <button type="submit">
                        Apply Filter
                    </button>

                    <a href="dashboard.php">
                        Reset
                    </a>

                </form>

            </section>


            <!-- ======================================
             ANALYTICS CARDS
        ======================================= -->

            <section class="analytics-grid">


                <div class="analytics-card">

                    <h3>Total Posts</h3>

                    <h2>
                        <?= number_format($analytics['total_posts']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Views</h3>

                    <h2>
                        <?= number_format($analytics['total_views']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Reach</h3>

                    <h2>
                        <?= number_format($analytics['total_reach']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Likes</h3>

                    <h2>
                        <?= number_format($analytics['total_likes']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Comments</h3>

                    <h2>
                        <?= number_format($analytics['total_comments']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Shares</h3>

                    <h2>
                        <?= number_format($analytics['total_shares']) ?>
                    </h2>

                </div>


                <div class="analytics-card">

                    <h3>Total Engagement</h3>

                    <h2>
                        <?= number_format($totalEngagement) ?>
                    </h2>

                </div>


            </section>


            <!-- ======================================
             CHARTS
        ======================================= -->

            <section class="charts-section">


                <!-- PERFORMANCE CHART -->

                <div class="chart-card">

                    <h3>Social Media Performance</h3>

                    <div class="chart-container">

                        <canvas id="performanceChart"></canvas>

                    </div>

                </div>


                <!-- PLATFORM CHART -->

                <div class="chart-card">

                    <h3>Platform Comparison</h3>

                    <div class="chart-container">

                        <canvas id="platformChart"></canvas>

                    </div>

                </div>


            </section>


        </main>

    </div>


    <!-- ======================================
     CHART.JS
====================================== -->

    <script>
        // ==========================================
        // PERFORMANCE BAR CHART
        // ==========================================

        const performanceCanvas =
            document.getElementById('performanceChart');

        new Chart(performanceCanvas, {

            type: 'bar',

            data: {

                labels: [
                    'Views',
                    'Likes',
                    'Comments',
                    'Shares',
                    'Reach'
                ],

                datasets: [{

                    label: 'Social Media Metrics',

                    data: [

                        <?= (int) $analytics['total_views'] ?>,

                        <?= (int) $analytics['total_likes'] ?>,

                        <?= (int) $analytics['total_comments'] ?>,

                        <?= (int) $analytics['total_shares'] ?>,

                        <?= (int) $analytics['total_reach'] ?>

                    ],

                    backgroundColor: [
                        '#00e5ff',
                        '#6366f1',
                        '#a855f7',
                        '#f59e0b',
                        '#10b981'
                    ],

                    borderRadius: 8

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


        // ==========================================
        // PLATFORM DOUGHNUT CHART
        // ==========================================

        const platformCanvas =
            document.getElementById('platformChart');

        new Chart(platformCanvas, {

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