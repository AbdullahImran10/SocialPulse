<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$userId = getUserId();
$role = getUserRole();

// Only Admin and Social Media Managers can generate reports.
if (!in_array($role, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$errors = [];

// Get accessible brands
if ($role === 'Admin') {
    $brandStmt = $pdo->query("
        SELECT brand_id, brand_name
        FROM brands
        ORDER BY brand_name
    ");
} else {
    $brandStmt = $pdo->prepare("
        SELECT b.brand_id, b.brand_name
        FROM brands b
        INNER JOIN brand_members bm ON bm.brand_id = b.brand_id
        WHERE bm.user_id = ?
        ORDER BY b.brand_name
    ");

    $brandStmt->execute([$userId]);
}

$brands = $brandStmt->fetchAll();

// Generate report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request. Please refresh the page.';
    } else {

        $brandId = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);
        $reportName = trim($_POST['report_name'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $platform = $_POST['platform'] ?? 'All';

        // Validate report name
        if ($reportName === '' || strlen($reportName) > 150) {
            $errors[] = 'Report name is required and must not exceed 150 characters.';
        }

        // Validate brand
        $brandExists = false;

        foreach ($brands as $brand) {
            if ((int)$brand['brand_id'] === (int)$brandId) {
                $brandExists = true;
                break;
            }
        }

        if (!$brandId || !$brandExists) {
            $errors[] = 'Please select a valid brand.';
        }

        // Validate dates
        $start = DateTime::createFromFormat('!Y-m-d', $startDate);
        $end = DateTime::createFromFormat('!Y-m-d', $endDate);

        if (!$start || $start->format('Y-m-d') !== $startDate) {
            $errors[] = 'Please enter a valid start date.';
        }

        if (!$end || $end->format('Y-m-d') !== $endDate) {
            $errors[] = 'Please enter a valid end date.';
        }

        if ($start && $end && $start > $end) {
            $errors[] = 'Start date cannot be after end date.';
        }

        // Validate platform
        if (!in_array($platform, ['Instagram', 'Facebook', 'All'], true)) {
            $errors[] = 'Invalid platform selected.';
        }

        // Save report
        if (empty($errors)) {

            $stmt = $pdo->prepare("
                INSERT INTO reports
                (brand_id, created_by, report_name, start_date, end_date, platform)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $brandId,
                $userId,
                $reportName,
                $startDate,
                $endDate,
                $platform
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: reports.php?success=1');
            exit;
        }
    }
}

// Get report history
if ($role === 'Admin') {

    $historyStmt = $pdo->query("
        SELECT
            r.*,
            b.brand_name,
            u.name AS creator_name
        FROM reports r
        INNER JOIN brands b ON b.brand_id = r.brand_id
        INNER JOIN users u ON u.user_id = r.created_by
        ORDER BY r.created_at DESC
    ");
} else {

    $historyStmt = $pdo->prepare("
        SELECT
            r.*,
            b.brand_name,
            u.name AS creator_name
        FROM reports r
        INNER JOIN brands b ON b.brand_id = r.brand_id
        INNER JOIN users u ON u.user_id = r.created_by
        INNER JOIN brand_members bm ON bm.brand_id = r.brand_id
        WHERE bm.user_id = ?
        ORDER BY r.created_at DESC
    ");

    $historyStmt->execute([$userId]);
}

$reports = $historyStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports Management | SocialPulse</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/reports.css">
</head>

<body>

    <div class="reports-container">

        <div class="reports-header">
            <div>
                <h1>Reports Management</h1>
                <p>Generate and manage your social media performance reports.</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Report created successfully!
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="error-message">
                <?= escape($error) ?>
            </div>
        <?php endforeach; ?>


        <!-- Generate Report Form -->

        <section class="report-card">

            <h2>Generate New Report</h2>

            <form method="POST" action="reports.php" class="report-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($csrfToken) ?>">

                <div class="form-group full-width">
                    <label for="report_name">Report Name</label>

                    <input
                        type="text"
                        id="report_name"
                        name="report_name"
                        maxlength="150"
                        placeholder="e.g. November Performance Report"
                        required>
                </div>

                <div class="form-group">
                    <label for="brand_id">Select Brand</label>

                    <select name="brand_id" id="brand_id" required>
                        <option value="">Choose Brand</option>

                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= escape($brand['brand_id']) ?>">
                                <?= escape($brand['brand_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="platform">Platform</label>

                    <select name="platform" id="platform" required>
                        <option value="All">All Platforms</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Facebook">Facebook</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        required>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>

                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="generate-btn">
                        Generate Report
                    </button>
                </div>

            </form>

        </section>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-message">
                Report deleted successfully!
            </div>
        <?php endif; ?>


        <!-- Report History -->

        <section class="report-card">

            <div class="report-history-header">
                <h2>Report History</h2>
                <span><?= count($reports) ?> Reports</span>
            </div>

            <div class="table-responsive">

                <table class="reports-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Report Name</th>
                            <th>Brand</th>
                            <th>Platform</th>
                            <th>Date Range</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($reports)): ?>

                            <tr>
                                <td colspan="8" class="empty-state">
                                    No reports have been generated yet.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($reports as $report): ?>

                                <tr>
                                    <td>#<?= escape($report['report_id']) ?></td>

                                    <td>
                                        <?= escape($report['report_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($report['brand_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($report['platform']) ?>
                                    </td>

                                    <td>
                                        <?= escape($report['start_date']) ?>
                                        to
                                        <?= escape($report['end_date']) ?>
                                    </td>

                                    <td>
                                        <?= escape($report['creator_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($report['created_at']) ?>
                                    </td>

                                    <td>
                                        <div class="report-actions">

                                            <a href="/socialpulse/public/reports/view.php?id=<?= (int)$report['report_id'] ?>"
                                                class="view-report-btn">
                                                View Report
                                            </a>

                                            <a href="/socialpulse/public/reports/download.php?id=<?= (int)$report['report_id'] ?>"
                                                class="download-report-btn">
                                                Download CSV
                                            </a>

                                            <form action="/socialpulse/public/reports/delete.php"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this report?');">

                                                <input type="hidden"
                                                    name="report_id"
                                                    value="<?= (int)$report['report_id'] ?>">

                                                <input type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>">

                                                <button type="submit" class="delete-report-btn">
                                                    Delete
                                                </button>

                                            </form>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>

</body>

</html>