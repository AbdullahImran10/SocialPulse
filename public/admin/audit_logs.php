<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('Admin');

// Escape output
function auditEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Pagination
$limit = 15;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

$page = ($page && $page > 0) ? $page : 1;
$offset = ($page - 1) * $limit;

// Search
$search = trim($_GET['search'] ?? '');

try {

    // Count total records
    $countSql = "
        SELECT COUNT(*)
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
    ";

    if ($search !== '') {
        $countSql .= "
            WHERE u.name LIKE :search1
            OR u.email LIKE :search2
            OR al.action LIKE :search3
            OR al.details LIKE :search4
        ";
    }

    $countStmt = $pdo->prepare($countSql);

    if ($search !== '') {
        $term = "%$search%";

        $countStmt->execute([
            ':search1' => $term,
            ':search2' => $term,
            ':search3' => $term,
            ':search4' => $term
        ]);
    } else {
        $countStmt->execute();
    }

    $totalLogs = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalLogs / $limit));

    // Get audit logs
    $sql = "
        SELECT
            al.log_id,
            al.user_id,
            al.action,
            al.details,
            al.created_at,
            u.name AS user_name,
            u.email AS user_email

        FROM audit_logs al

        LEFT JOIN users u
            ON al.user_id = u.user_id
    ";

    if ($search !== '') {
        $sql .= "
            WHERE u.name LIKE :search1
            OR u.email LIKE :search2
            OR al.action LIKE :search3
            OR al.details LIKE :search4
        ";
    }

    $sql .= "
        ORDER BY al.created_at DESC, al.log_id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $term = "%$search%";

        $stmt->bindValue(':search1', $term);
        $stmt->bindValue(':search2', $term);
        $stmt->bindValue(':search3', $term);
        $stmt->bindValue(':search4', $term);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to load audit logs.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Audit Logs | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/audit_logs.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

    <div class="audit-container">

        <header class="audit-header">

            <div>
                <h1>Audit Logs</h1>
                <p>Monitor user activities across SocialPulse.</p>
            </div>

            <a href="/socialpulse/public/dashboard.php"
                class="back-dashboard-btn">
                ← Back to Dashboard
            </a>

        </header>


        <!-- Statistics -->

        <section class="audit-stats">

            <div class="audit-stat-card">
                <h3>Total Activities</h3>
                <p><?= number_format($totalLogs) ?></p>
            </div>

        </section>


        <!-- Search -->

        <section class="audit-card">

            <form method="GET" class="audit-search">

                <input
                    type="text"
                    name="search"
                    placeholder="Search user, action or details..."
                    value="<?= auditEscape($search) ?>">

                <button type="submit">Search</button>

                <a href="audit_logs.php" class="reset-btn">
                    Reset
                </a>

            </form>


            <!-- Logs Table -->

            <div class="table-responsive">

                <table class="audit-table">

                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($logs)): ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    No audit activities found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($logs as $log): ?>

                                <tr>

                                    <td>
                                        #<?= (int)$log['log_id'] ?>
                                    </td>

                                    <td>
                                        <?= auditEscape($log['user_name'] ?? 'Unknown User') ?>
                                    </td>

                                    <td>
                                        <?= auditEscape($log['user_email'] ?? 'N/A') ?>
                                    </td>

                                    <td>
                                        <span class="action-badge">
                                            <?= auditEscape($log['action']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= auditEscape($log['details'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= auditEscape($log['created_at']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- Pagination -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php if ($page > 1): ?>

                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                            Previous
                        </a>

                    <?php endif; ?>

                    <span>
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>

                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </div>

</body>

</html>