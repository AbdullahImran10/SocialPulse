<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if (!hasRole('Admin') && !hasRole('Social Media Manager')) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate CSRF token.
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid security token.');
}

$reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId <= 0) {
    http_response_code(400);
    exit('Invalid report ID.');
}

$userId = getUserId();
$userRole = getUserRole();

try {

    // Verify that the report exists and belongs to an accessible brand.
    if ($userRole === 'Admin') {

        $stmt = $pdo->prepare("
            SELECT report_id
            FROM reports
            WHERE report_id = ?
        ");

        $stmt->execute([$reportId]);

    } else {

        $stmt = $pdo->prepare("
            SELECT r.report_id
            FROM reports r
            INNER JOIN brand_members bm
                ON bm.brand_id = r.brand_id
            WHERE r.report_id = ?
            AND bm.user_id = ?
        ");

        $stmt->execute([$reportId, $userId]);
    }

    if (!$stmt->fetch()) {
        http_response_code(404);
        exit('Report not found or access denied.');
    }

    // Delete only the report, not its posts or metrics.
    $stmt = $pdo->prepare("
        DELETE FROM reports
        WHERE report_id = ?
    ");

    $stmt->execute([$reportId]);

    header('Location: ../reports.php?deleted=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to delete report.');
}