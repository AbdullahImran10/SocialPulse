<?php
// DEMO ONLY: adds a fresh metrics snapshot to random posts every few seconds
// so you can watch the dashboard update live.
//   php tools/simulate_metrics.php          (Ctrl+C to stop)
// Real data needs the Meta Graph API (see README "Future Improvements").

if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
require_once __DIR__ . '/../config/database.php';

echo "Simulating live metrics... Ctrl+C to stop\n";
while (true) {
    $posts = $pdo->query('SELECT post_id FROM posts')->fetchAll(PDO::FETCH_COLUMN);
    if (!$posts) { exit("No posts in database.\n"); }
    $pid = $posts[array_rand($posts)];

    $q = $pdo->prepare('SELECT views, likes, comments, shares, reach FROM post_metrics
                        WHERE post_id = ? ORDER BY recorded_at DESC, metric_id DESC LIMIT 1');
    $q->execute([$pid]);
    $m = $q->fetch() ?: ['views' => 100, 'likes' => 10, 'comments' => 2, 'shares' => 1, 'reach' => 80];

    $views = $m['views'] + random_int(20, 300);
    $ins = $pdo->prepare('INSERT INTO post_metrics (post_id, views, likes, comments, shares, reach)
                          VALUES (?,?,?,?,?,?)');
    $ins->execute([
        $pid, $views,
        $m['likes'] + random_int(1, 25),
        $m['comments'] + random_int(0, 4),
        $m['shares'] + random_int(0, 3),
        $m['reach'] + random_int(15, 250),
    ]);
    echo date('H:i:s') . " post #$pid -> $views views\n";
    sleep(4);
}
