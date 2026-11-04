<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

// Only Admin and Social Media Manager can access Posts Management
if (!hasRole('Admin') && !hasRole('Social Media Manager')) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = getUserId();
$userRole = getUserRole();

// Search and filter inputs
$search = trim($_GET['search'] ?? '');
$platform = $_GET['platform'] ?? '';

// Validate platform filter
if (!in_array($platform, ['', 'Instagram', 'Facebook'], true)) {
    $platform = '';
}

// Pagination
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;

// Build query conditions
$where = [];
$params = [];

// Search by caption
if ($search !== '') {
    $where[] = "p.caption LIKE ?";
    $params[] = '%' . $search . '%';
}

// Filter by platform
if ($platform !== '') {
    $where[] = "sa.platform = ?";
    $params[] = $platform;
}

// Managers can only access assigned brands
if ($userRole === 'Social Media Manager') {
    $where[] = "EXISTS (
        SELECT 1
        FROM brand_members bm
        WHERE bm.brand_id = b.brand_id
        AND bm.user_id = ?
    )";

    $params[] = $userId;
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = ' WHERE ' . implode(' AND ', $where);
}

try {

    // Get total number of matching posts
    $sql = "SELECT COUNT(*)
            FROM posts p
            INNER JOIN social_accounts sa
                ON p.account_id = sa.account_id
            INNER JOIN brands b
                ON sa.brand_id = b.brand_id
            $whereSQL";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $totalPosts = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalPosts / $limit));

    // Prevent requesting a page beyond the available pages
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Fetch posts
    $sql = "SELECT
                p.post_id,
                p.caption,
                p.post_type,
                p.published_at,
                p.post_url,
                sa.platform,
                sa.account_name,
                b.brand_name,
                c.client_name
            FROM posts p
            INNER JOIN social_accounts sa
                ON p.account_id = sa.account_id
            INNER JOIN brands b
                ON sa.brand_id = b.brand_id
            INNER JOIN clients c
                ON b.client_id = c.client_id
            $whereSQL
            ORDER BY p.published_at DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to load posts. Please try again.');
}

// Escape output
function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Preserve filters when changing pages
function pageUrl($pageNumber)
{
    $params = $_GET;
    $params['page'] = $pageNumber;

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Posts Management | SocialPulse</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <link rel="stylesheet" href="../assets/css/posts.css">

</head>

<body>

    <div class="dashboard-container">

        <!-- Sidebar -->

        <aside class="sidebar">

            <h2>SocialPulse</h2>

            <nav>

                <a href="dashboard.php">Dashboard</a>

                <?php if ($userRole === 'Admin'): ?>

                    <a href="admin/index.php">Admin Panel</a>

                <?php endif; ?>

                <?php if ($userRole === 'Social Media Manager'): ?>

                    <a href="manager/index.php">Manager Panel</a>

                <?php endif; ?>

                <a href="posts.php" class="active">Posts</a>

                <a href="metrics.php">Analytics</a>

                <a href="logout.php">Logout</a>

            </nav>

        </aside>


        <!-- Main Content -->

        <main class="main-content">

            <!-- Topbar -->

            <header class="topbar">

                <div>

                    <h1>Posts Management</h1>

                    <p>Manage your social media posts.</p>

                </div>

                <div class="user-info">

                    <span><?= escape($_SESSION['name'] ?? 'User') ?></span>

                    <span><?= escape($userRole) ?></span>

                </div>

            </header>


            <!-- Summary -->

            <section class="posts-summary">

                <div class="analytics-card">

                    <h3>Total Posts</h3>

                    <h2><?= number_format($totalPosts) ?></h2>

                </div>

            </section>


            <!-- Toolbar -->

            <section class="posts-toolbar">

                <form method="GET" action="posts.php" class="posts-filter-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Search by caption..."
                        value="<?= escape($search) ?>">

                    <select name="platform">

                        <option value="">All Platforms</option>

                        <option
                            value="Instagram"
                            <?= $platform === 'Instagram' ? 'selected' : '' ?>>

                            Instagram

                        </option>

                        <option
                            value="Facebook"
                            <?= $platform === 'Facebook' ? 'selected' : '' ?>>

                            Facebook

                        </option>

                    </select>

                    <button type="submit">Search</button>

                    <a href="posts.php">Reset</a>

                </form>


                <a href="posts/add.php" class="add-post-btn">

                    + Add New Post

                </a>

            </section>


            <!-- Success Messages -->

            <?php if (isset($_GET['updated'])): ?>

                <p class="success-message">
                    Post updated successfully!
                </p>

            <?php endif; ?>


            <!-- Posts Table -->

            <section class="posts-table-container">

                <div class="table-header">

                    <h3>All Posts</h3>

                    <span>
                        Showing <?= count($posts) ?> of <?= $totalPosts ?> posts
                    </span>

                </div>


                <?php if (empty($posts)): ?>

                    <div class="empty-state">

                        <h3>No Posts Found</h3>

                        <p>
                            No posts match your search or filter.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="posts-table">

                            <thead>

                                <tr>

                                    <th>ID</th>

                                    <th>Caption</th>

                                    <th>Client</th>

                                    <th>Brand</th>

                                    <th>Platform</th>

                                    <th>Account</th>

                                    <th>Post Type</th>

                                    <th>Published Date</th>

                                    <th>Post URL</th>

                                    <th>Actions</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($posts as $post): ?>

                                    <tr>

                                        <td>
                                            <?= (int)$post['post_id'] ?>
                                        </td>

                                        <td class="caption-cell">

                                            <?= escape($post['caption']) ?>

                                        </td>

                                        <td>
                                            <?= escape($post['client_name']) ?>
                                        </td>

                                        <td>
                                            <?= escape($post['brand_name']) ?>
                                        </td>

                                        <td>

                                            <span class="platform-badge
                                            <?= strtolower(escape($post['platform'])) ?>">

                                                <?= escape($post['platform']) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= escape($post['account_name']) ?>
                                        </td>

                                        <td>
                                            <?= escape($post['post_type']) ?>
                                        </td>

                                        <td>

                                            <?= escape(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($post['published_at'])
                                                )
                                            ) ?>

                                        </td>

                                        <td>

                                            <?php if (!empty($post['post_url'])): ?>

                                                <a
                                                    href="<?= escape($post['post_url']) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="post-link">

                                                    View Post

                                                </a>

                                            <?php else: ?>

                                                <span>-</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <a
                                                href="posts/edit.php?id=<?= (int)$post['post_id'] ?>"
                                                class="edit-btn">
                                                Edit
                                            </a>

                                            <form
                                                action="posts/delete.php"
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm('Are you sure you want to delete this post and its analytics?');">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['csrf_token']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="post_id"
                                                    value="<?= (int)$post['post_id'] ?>">

                                                <button type="submit" class="delete-btn">
                                                    Delete
                                                </button>

                                            </form>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>


                <!-- Pagination -->

                <?php if ($totalPages > 1): ?>

                    <div class="pagination">

                        <?php if ($page > 1): ?>

                            <a href="<?= escape(pageUrl($page - 1)) ?>">
                                Previous
                            </a>

                        <?php endif; ?>


                        <span>

                            Page <?= $page ?> of <?= $totalPages ?>

                        </span>


                        <?php if ($page < $totalPages): ?>

                            <a href="<?= escape(pageUrl($page + 1)) ?>">
                                Next
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>