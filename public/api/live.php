<?php
// GET /public/api/live.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
// Returns the same numbers as dashboard.php, as JSON, for live polling.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

// Release the session lock so polling never blocks other page loads
session_write_close();

// ---------- date range (same rules as dashboard.php) ----------
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days'));
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$s = DateTime::createFromFormat('!Y-m-d', $startDate);
$e = DateTime::createFromFormat('!Y-m-d', $endDate);
if (
    !$s || !$e ||
    $s->format('Y-m-d') !== $startDate ||
    $e->format('Y-m-d') !== $endDate ||
    $startDate > $endDate ||
    $endDate > date('Y-m-d')
) {
    $startDate = date('Y-m-d', strtotime('-29 days'));
    $endDate   = date('Y-m-d');
}

// ---------- role-based access (same rules as dashboard.php) ----------
$access = '';
$accessParams = [];

if ($userRole === 'Social Media Manager') {
    $access = ' AND EXISTS (SELECT 1 FROM brand_members bm
                            WHERE bm.brand_id = b.brand_id AND bm.user_id = ?)';
    $accessParams[] = $userId;
} elseif ($userRole === 'Client') {
    $access = ' AND b.client_id = (SELECT client_id FROM users WHERE user_id = ?)';
    $accessParams[] = $userId;
} elseif ($userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    // latest metric row per post inside the date range
    $latest = "LEFT JOIN post_metrics pm ON pm.metric_id = (
                   SELECT pm2.metric_id FROM post_metrics pm2
                   WHERE pm2.post_id = p.post_id
                     AND pm2.recorded_at >= ?
                     AND pm2.recorded_at < DATE_ADD(?, INTERVAL 1 DAY)
                   ORDER BY pm2.recorded_at DESC, pm2.metric_id DESC
                   LIMIT 1)";
    $base = "FROM brands b
             INNER JOIN social_accounts sa ON sa.brand_id = b.brand_id
             INNER JOIN posts p ON p.account_id = sa.account_id
             $latest
             WHERE 1 = 1 $access";
    $params = array_merge([$startDate, $endDate], $accessParams);

    // totals
    $st = $pdo->prepare("SELECT COUNT(DISTINCT p.post_id) AS total_posts,
            COALESCE(SUM(pm.views),0)    AS total_views,
            COALESCE(SUM(pm.likes),0)    AS total_likes,
            COALESCE(SUM(pm.comments),0) AS total_comments,
            COALESCE(SUM(pm.shares),0)   AS total_shares,
            COALESCE(SUM(pm.reach),0)    AS total_reach $base");
    $st->execute($params);
    $t = array_map('intval', $st->fetch());
    $t['total_engagement'] = $t['total_likes'] + $t['total_comments'] + $t['total_shares'];

    // platform split
    $st = $pdo->prepare("SELECT sa.platform, COALESCE(SUM(pm.views),0) AS v $base GROUP BY sa.platform");
    $st->execute($params);
    $platform = ['labels' => [], 'views' => []];
    foreach ($st->fetchAll() as $r) {
        $platform['labels'][] = $r['platform'];
        $platform['views'][]  = (int) $r['v'];
    }

    // recent activity feed (newest metric rows)
    $st = $pdo->prepare("SELECT pm.metric_id, pm.recorded_at, pm.views, pm.likes, pm.comments, pm.shares,
                                sa.platform, sa.account_name, LEFT(COALESCE(p.caption,''), 60) AS caption
            FROM post_metrics pm
            INNER JOIN posts p ON p.post_id = pm.post_id
            INNER JOIN social_accounts sa ON sa.account_id = p.account_id
            INNER JOIN brands b ON b.brand_id = sa.brand_id
            WHERE 1 = 1 $access
            ORDER BY pm.recorded_at DESC, pm.metric_id DESC
            LIMIT 6");
    $st->execute($accessParams);
    $activity = $st->fetchAll();

    echo json_encode([
        'totals'     => $t,
        'platform'   => $platform,
        'activity'   => $activity,
        'last_id'    => $activity ? (int) $activity[0]['metric_id'] : 0,
        'server_time' => date('H:i:s'),
    ]);
} catch (Throwable $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
