<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$role = getUserRole();
$userId = getUserId();

// Only Admin and Social Media Manager
if (!in_array($role, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// GET ACCESSIBLE POSTS WITH LATEST METRICS
// --------------------------------------------------

if ($role === 'Admin') {

    $stmt = $pdo->query("
        SELECT
            p.post_id,
            p.caption,
            p.post_type,
            p.published_at,
            sa.platform,
            sa.account_name,
            b.brand_name,

            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            pm.recorded_at

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

        ORDER BY p.published_at DESC
    ");
} else {

    $stmt = $pdo->prepare("
        SELECT
            p.post_id,
            p.caption,
            p.post_type,
            p.published_at,
            sa.platform,
            sa.account_name,
            b.brand_name,

            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            pm.recorded_at

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

// Index posts by ID
$postsById = [];

foreach ($posts as $post) {
    $postsById[$post['post_id']] = $post;
}

// --------------------------------------------------
// SELECT POSTS FOR COMPARISON
// --------------------------------------------------

$postAId = filter_input(INPUT_GET, 'post_a', FILTER_VALIDATE_INT);
$postBId = filter_input(INPUT_GET, 'post_b', FILTER_VALIDATE_INT);

$postA = null;
$postB = null;

$error = '';

if (
    $postAId !== null && $postAId !== false &&
    $postBId !== null && $postBId !== false
) {

    if ($postAId === $postBId) {

        $error = 'Please select two different posts.';
    } elseif (
        !isset($postsById[$postAId]) ||
        !isset($postsById[$postBId])
    ) {

        $error = 'Invalid post selection or access denied.';
    } else {

        $postA = $postsById[$postAId];
        $postB = $postsById[$postBId];
    }
}

// --------------------------------------------------
// CALCULATE ENGAGEMENT
// --------------------------------------------------

function getEngagementRate($post)
{
    $reach = (int)($post['reach'] ?? 0);

    if ($reach <= 0) {
        return 0;
    }

    $interactions =
        (int)($post['likes'] ?? 0) +
        (int)($post['comments'] ?? 0) +
        (int)($post['shares'] ?? 0);

    return ($interactions / $reach) * 100;
}

// --------------------------------------------------
// COMPARISON METRICS
// --------------------------------------------------

$comparisonMetrics = [
    'views' => 'Views',
    'likes' => 'Likes',
    'comments' => 'Comments',
    'shares' => 'Shares',
    'reach' => 'Reach'
];

// --------------------------------------------------
// CHART DATA
// --------------------------------------------------
$winsA = 0;
$winsB = 0;
$ties = 0;
$chartData = null;

if ($postA && $postB) {

    $chartData = [
        'labels' => array_values($comparisonMetrics),

        'postA' => array_map(
            fn($key) => (int)($postA[$key] ?? 0),
            array_keys($comparisonMetrics)
        ),

        'postB' => array_map(
            fn($key) => (int)($postB[$key] ?? 0),
            array_keys($comparisonMetrics)
        ),

        'engagementA' => round(getEngagementRate($postA), 2),
        'engagementB' => round(getEngagementRate($postB), 2)
    ];

    $winsA = 0;
    $winsB = 0;
    $ties = 0;

    foreach ($comparisonMetrics as $key => $label) {

        $valueA = (int)($postA[$key] ?? 0);
        $valueB = (int)($postB[$key] ?? 0);

        if ($valueA > $valueB) {
            $winsA++;
        } elseif ($valueB > $valueA) {
            $winsB++;
        } else {
            $ties++;
        }
    }

    if ($chartData['engagementA'] > $chartData['engagementB']) {
        $winsA++;
    } elseif ($chartData['engagementB'] > $chartData['engagementA']) {
        $winsB++;
    } else {
        $ties++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post Comparison | SocialPulse</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <link rel="stylesheet" href="../assets/css/post_comparison.css">

</head>

<body>

    <div class="comparison-container">

        <!-- HEADER -->

        <header class="comparison-header">

            <div>
                <h1>Post Performance Comparison</h1>

                <p>Compare the performance of two social media posts.</p>
            </div>

            <a href="dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </header>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= escape($error) ?>
            </div>

        <?php endif; ?>


        <!-- POST SELECTION -->

        <section class="comparison-card">

            <h2>Select Posts</h2>

            <?php if (count($posts) < 2): ?>

                <div class="empty-state">
                    <h3>Not enough posts</h3>
                    <p>You need at least two accessible posts to compare.</p>
                </div>

            <?php else: ?>

                <form method="GET" class="comparison-form">

                    <div class="form-group">

                        <label for="post_a">First Post</label>

                        <select name="post_a" id="post_a" required>

                            <option value="">Select First Post</option>

                            <?php foreach ($posts as $post): ?>

                                <option
                                    value="<?= escape($post['post_id']) ?>"
                                    <?= $postAId == $post['post_id'] ? 'selected' : '' ?>>

                                    <?= escape($post['brand_name']) ?>
                                    -
                                    <?= escape($post['platform']) ?>
                                    -
                                    Post #<?= escape($post['post_id']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="post_b">Second Post</label>

                        <select name="post_b" id="post_b" required>

                            <option value="">Select Second Post</option>

                            <?php foreach ($posts as $post): ?>

                                <option
                                    value="<?= escape($post['post_id']) ?>"
                                    <?= $postBId == $post['post_id'] ? 'selected' : '' ?>>

                                    <?= escape($post['brand_name']) ?>
                                    -
                                    <?= escape($post['platform']) ?>
                                    -
                                    Post #<?= escape($post['post_id']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group comparison-submit">

                        <button type="submit" class="primary-btn">
                            Compare Posts
                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </section>


        <!-- COMPARISON RESULTS -->

        <?php if ($postA && $postB): ?>

            <?php

            $postAEngagement = getEngagementRate($postA);
            $postBEngagement = getEngagementRate($postB);

            ?>

            <section class="comparison-card">

                <h2>Comparison Results</h2>

                <div class="posts-grid">

                    <!-- FIRST POST -->

                    <div class="post-info">

                        <span class="post-label">POST A</span>

                        <h3><?= escape($postA['brand_name']) ?></h3>

                        <p>
                            <?= escape($postA['platform']) ?>
                            -
                            <?= escape($postA['account_name']) ?>
                        </p>

                        <p class="post-id">
                            Post #<?= escape($postA['post_id']) ?>
                        </p>

                        <p class="caption">
                            <?= escape($postA['caption'] ?: 'No caption available') ?>
                        </p>

                        <?php if (!$postA['recorded_at']): ?>
                            <span class="no-metrics">No metrics recorded</span>
                        <?php else: ?>
                            <span class="recorded-date">
                                Updated: <?= escape($postA['recorded_at']) ?>
                            </span>
                        <?php endif; ?>

                    </div>


                    <!-- SECOND POST -->

                    <div class="post-info">

                        <span class="post-label post-b-label">POST B</span>

                        <h3><?= escape($postB['brand_name']) ?></h3>

                        <p>
                            <?= escape($postB['platform']) ?>
                            -
                            <?= escape($postB['account_name']) ?>
                        </p>

                        <p class="post-id">
                            Post #<?= escape($postB['post_id']) ?>
                        </p>

                        <p class="caption">
                            <?= escape($postB['caption'] ?: 'No caption available') ?>
                        </p>

                        <?php if (!$postB['recorded_at']): ?>
                            <span class="no-metrics">No metrics recorded</span>
                        <?php else: ?>
                            <span class="recorded-date">
                                Updated: <?= escape($postB['recorded_at']) ?>
                            </span>
                        <?php endif; ?>

                    </div>

                </div>


                <!-- METRICS TABLE -->

                <h2 class="metrics-heading">Performance Metrics</h2>

                <div class="comparison-table-wrapper">

                    <table class="comparison-table">

                        <thead>

                            <tr>
                                <th>Metric</th>
                                <th>Post A</th>
                                <th>Post B</th>
                                <th>Winner</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php

                            foreach ($comparisonMetrics as $key => $label):

                                $valueA = (int)($postA[$key] ?? 0);
                                $valueB = (int)($postB[$key] ?? 0);

                                $winner = 'Tie';

                                if ($valueA > $valueB) {
                                    $winner = 'Post A';
                                } elseif ($valueB > $valueA) {
                                    $winner = 'Post B';
                                }

                            ?>

                                <tr>

                                    <td><?= escape($label) ?></td>

                                    <td><?= number_format($valueA) ?></td>

                                    <td><?= number_format($valueB) ?></td>

                                    <td>
                                        <span class="winner-badge">
                                            <?= escape($winner) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <!-- ENGAGEMENT RATE -->

                            <?php

                            $engagementWinner = 'Tie';

                            if ($postAEngagement > $postBEngagement) {
                                $engagementWinner = 'Post A';
                            } elseif ($postBEngagement > $postAEngagement) {
                                $engagementWinner = 'Post B';
                            }

                            ?>

                            <tr>

                                <td>Engagement Rate</td>

                                <td><?= number_format($postAEngagement, 2) ?>%</td>

                                <td><?= number_format($postBEngagement, 2) ?>%</td>

                                <td>
                                    <span class="winner-badge">
                                        <?= escape($engagementWinner) ?>
                                    </span>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- VISUAL COMPARISON -->

                <!-- PERFORMANCE SUMMARY -->
                <h2 class="metrics-heading">Performance Overview</h2>

                <div class="summary-grid">

                    <div class="summary-card">
                        <span>Post A Wins</span>
                        <strong><?= $winsA ?></strong>
                        <small>Out of 6 metrics</small>
                    </div>

                    <div class="summary-card">
                        <span>Post B Wins</span>
                        <strong><?= $winsB ?></strong>
                        <small>Out of 6 metrics</small>
                    </div>

                    <div class="summary-card">
                        <span>Tied Metrics</span>
                        <strong><?= $ties ?></strong>
                        <small>Equal performance</small>
                    </div>

                </div>

                <!-- CHARTS -->
                <h2 class="metrics-heading">Performance Infographics</h2>

                <div class="charts-grid">

                    <div class="chart-card">
                        <h3>Metric Comparison</h3>
                        <p>Compare views, likes, comments, shares and reach.</p>

                        <div class="chart-container">
                            <canvas id="metricsChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3>Engagement Rate</h3>
                        <p>Compare engagement as a percentage of reach.</p>

                        <div class="chart-container doughnut-container">
                            <canvas id="engagementChart"></canvas>
                        </div>
                    </div>

                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

                <script>
                    const comparisonData = <?= json_encode(
                                                $chartData,
                                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                            ) ?>;

                    const chartFontColor = '#cbd5e1';

                    // Bar chart
                    new Chart(document.getElementById('metricsChart'), {
                        type: 'bar',

                        data: {
                            labels: comparisonData.labels,

                            datasets: [{
                                    label: 'Post A',
                                    data: comparisonData.postA,
                                    backgroundColor: '#00e5ff',
                                    borderRadius: 6
                                },
                                {
                                    label: 'Post B',
                                    data: comparisonData.postB,
                                    backgroundColor: '#a78bfa',
                                    borderRadius: 6
                                }
                            ]
                        },

                        options: {
                            responsive: true,
                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    labels: {
                                        color: chartFontColor
                                    }
                                }
                            },

                            scales: {
                                x: {
                                    ticks: {
                                        color: chartFontColor
                                    },
                                    grid: {
                                        display: false
                                    }
                                },

                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        color: chartFontColor
                                    },
                                    grid: {
                                        color: 'rgba(148, 163, 184, 0.12)'
                                    }
                                }
                            }
                        }
                    });

                    // Engagement doughnut chart
                    new Chart(document.getElementById('engagementChart'), {
                        type: 'doughnut',

                        data: {
                            labels: ['Post A', 'Post B'],

                            datasets: [{
                                data: [
                                    comparisonData.engagementA,
                                    comparisonData.engagementB
                                ],

                                backgroundColor: ['#00e5ff', '#a78bfa'],
                                borderColor: '#0b1220',
                                borderWidth: 4
                            }]
                        },

                        options: {
                            responsive: true,
                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: chartFontColor,
                                        padding: 20
                                    }
                                }
                            }
                        }
                    });
                </script>

            </section>

        <?php endif; ?>

    </div>

</body>

</html>