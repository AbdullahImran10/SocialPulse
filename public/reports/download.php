<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

$userId = getUserId();
$userRole = getUserRole();

// Admins, Managers, and Clients can download reports.
if (!in_array($userRole, ['Admin', 'Social Media Manager', 'Client'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId <= 0) {
    http_response_code(400);
    exit('Invalid report ID.');
}

try {

    // ==========================================
    // VERIFY REPORT ACCESS
    // ==========================================

    if ($userRole === 'Admin') {

        $stmt = $pdo->prepare("
            SELECT r.*, b.brand_name
            FROM reports r
            INNER JOIN brands b
                ON r.brand_id = b.brand_id
            WHERE r.report_id = ?
        ");

        $stmt->execute([$reportId]);

    } elseif ($userRole === 'Social Media Manager') {

        $stmt = $pdo->prepare("
            SELECT r.*, b.brand_name
            FROM reports r
            INNER JOIN brands b
                ON r.brand_id = b.brand_id
            WHERE r.report_id = ?
            AND EXISTS (
                SELECT 1
                FROM brand_members bm
                WHERE bm.brand_id = r.brand_id
                AND bm.user_id = ?
            )
        ");

        $stmt->execute([$reportId, $userId]);

    } else {

        // Clients can only access reports for their own brands.
        $stmt = $pdo->prepare("
            SELECT r.*, b.brand_name
            FROM reports r
            INNER JOIN brands b
                ON r.brand_id = b.brand_id
            INNER JOIN users u
                ON u.client_id = b.client_id
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


    // ==========================================
    // RETRIEVE LATEST METRICS FOR EACH POST
    // ==========================================

    $sql = "
        SELECT
            p.post_id,
            p.caption,
            p.post_type,
            p.published_at,
            sa.platform,
            sa.account_name,
            pm.views,
            pm.likes,
            pm.comments,
            pm.shares,
            pm.reach,
            pm.recorded_at

        FROM post_metrics pm

        INNER JOIN posts p
            ON pm.post_id = p.post_id

        INNER JOIN social_accounts sa
            ON p.account_id = sa.account_id

        WHERE sa.brand_id = :brand_id

        AND DATE(pm.recorded_at)
            BETWEEN :start_outer AND :end_outer

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


    // ==========================================
    // CALCULATE SUMMARY TOTALS
    // ==========================================

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

    $engagements = $totalLikes + $totalComments + $totalShares;

    $engagementRate = $totalReach > 0
        ? ($engagements / $totalReach) * 100
        : 0;


    // ==========================================
    // GENERATE CSV FILE
    // ==========================================

    $filename = 'SocialPulse_Report_' . $reportId . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility.
    fwrite($output, "\xEF\xBB\xBF");


    // Report information.
    fputcsv($output, ['SocialPulse Report']);
    fputcsv($output, ['Report Name', $report['report_name']]);
    fputcsv($output, ['Brand', $report['brand_name']]);
    fputcsv($output, ['Start Date', $report['start_date']]);
    fputcsv($output, ['End Date', $report['end_date']]);
    fputcsv($output, ['Platform', $report['platform']]);

    fputcsv($output, []);


    // Performance summary.
    fputcsv($output, ['Performance Summary']);
    fputcsv($output, ['Total Posts', count($posts)]);
    fputcsv($output, ['Total Views', $totalViews]);
    fputcsv($output, ['Total Likes', $totalLikes]);
    fputcsv($output, ['Total Comments', $totalComments]);
    fputcsv($output, ['Total Shares', $totalShares]);
    fputcsv($output, ['Total Reach', $totalReach]);

    fputcsv($output, [
        'Engagement Rate',
        number_format($engagementRate, 2) . '%'
    ]);

    fputcsv($output, []);


    // Post details.
    fputcsv($output, [
        'Post ID',
        'Platform',
        'Account',
        'Caption',
        'Post Type',
        'Published At',
        'Views',
        'Likes',
        'Comments',
        'Shares',
        'Reach',
        'Recorded At'
    ]);

    foreach ($posts as $post) {

        fputcsv($output, [
            $post['post_id'],
            $post['platform'],
            $post['account_name'],
            $post['caption'],
            $post['post_type'],
            $post['published_at'],
            $post['views'],
            $post['likes'],
            $post['comments'],
            $post['shares'],
            $post['reach'],
            $post['recorded_at']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to download report.');
}