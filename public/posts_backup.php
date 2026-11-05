<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$userId = (int) getUserId();
$userRole = getUserRole();

if (!in_array($userRole, ['Admin', 'Social Media Manager'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$error = '';


// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// =====================================
// HANDLE FORM SUBMISSION
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please try again.';
    } else {

        $accountId = (int) ($_POST['account_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        $postType = $_POST['post_type'] ?? '';
        $publishedAt = trim($_POST['published_at'] ?? '');
        $postUrl = trim($_POST['post_url'] ?? '');

        $allowedTypes = ['Image', 'Video', 'Reel', 'Story', 'Carousel', 'Text'];

        if ($accountId <= 0 || $postType === '') {

            $error = 'Please select an account and post type.';

        } elseif (!in_array($postType, $allowedTypes, true)) {

            $error = 'Invalid post type.';

        } elseif (
            $publishedAt !== '' &&
            !DateTime::createFromFormat('Y-m-d\TH:i', $publishedAt)
        ) {

            $error = 'Invalid publishing date.';

        } elseif (
            $postUrl !== '' &&
            !filter_var($postUrl, FILTER_VALIDATE_URL)
        ) {

            $error = 'Please enter a valid post URL.';

        } else {

            // Check account and brand authorization
            $sql = "
                SELECT sa.account_id
                FROM social_accounts sa
                JOIN brands b ON sa.brand_id = b.brand_id
                WHERE sa.account_id = ?
            ";

            $params = [$accountId];

            if ($userRole === 'Social Media Manager') {

                $sql .= "
                    AND EXISTS (
                        SELECT 1
                        FROM brand_members bm
                        WHERE bm.brand_id = b.brand_id
                          AND bm.user_id = ?
                    )
                ";

                $params[] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {

                $error = 'You are not authorized to add posts to this account.';

            } else {

                $stmt = $pdo->prepare(
                    "INSERT INTO posts
                    (account_id, caption, post_type, published_at, post_url)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $accountId,
                    $caption,
                    $postType,
                    $publishedAt !== ''
                        ? str_replace('T', ' ', $publishedAt) . ':00'
                        : null,
                    $postUrl !== '' ? $postUrl : null
                ]);

                $message = 'Post added successfully!';

            }

        }

    }

}


// =====================================
// FETCH AUTHORIZED SOCIAL ACCOUNTS
// =====================================

$sql = "
    SELECT
        sa.account_id,
        sa.platform,
        sa.account_name,
        b.brand_name,
        c.client_name

    FROM social_accounts sa

    JOIN brands b
        ON sa.brand_id = b.brand_id

    JOIN clients c
        ON b.client_id = c.client_id
";

$params = [];

if ($userRole === 'Social Media Manager') {

    $sql .= "
        WHERE EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
              AND bm.user_id = ?
        )
    ";

    $params[] = $userId;

}

$sql .= " ORDER BY b.brand_name, sa.platform";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$accounts = $stmt->fetchAll();


// =====================================
// FETCH POSTS
// =====================================

$sql = "
    SELECT
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

    JOIN social_accounts sa
        ON p.account_id = sa.account_id

    JOIN brands b
        ON sa.brand_id = b.brand_id

    JOIN clients c
        ON b.client_id = c.client_id
";

$params = [];

if ($userRole === 'Social Media Manager') {

    $sql .= "
        WHERE EXISTS (
            SELECT 1
            FROM brand_members bm
            WHERE bm.brand_id = b.brand_id
              AND bm.user_id = ?
        )
    ";

    $params[] = $userId;

}

$sql .= " ORDER BY p.post_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$posts = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post Management | SocialPulse</title>

</head>

<body>

    <h1>Post Management</h1>

    <a href="dashboard.php">Back to Dashboard</a>

    <hr>


    <!-- MESSAGES -->

    <?php if ($message !== ''): ?>

        <p><?= htmlspecialchars($message) ?></p>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <p><?= htmlspecialchars($error) ?></p>

    <?php endif; ?>


    <!-- ADD POST FORM -->

    <h2>Add New Post</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >


        <label for="account_id">Social Account:</label>

        <select name="account_id" id="account_id" required>

            <option value="">Select Account</option>

            <?php foreach ($accounts as $account): ?>

                <option value="<?= (int) $account['account_id'] ?>">

                    <?= htmlspecialchars($account['client_name']) ?>
                    -
                    <?= htmlspecialchars($account['brand_name']) ?>
                    -
                    <?= htmlspecialchars($account['platform']) ?>
                    -
                    <?= htmlspecialchars($account['account_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <br><br>


        <label for="caption">Caption:</label>

        <textarea
            name="caption"
            id="caption"
            rows="4"
            cols="40"
        ></textarea>

        <br><br>


        <label for="post_type">Post Type:</label>

        <select name="post_type" id="post_type" required>

            <option value="">Select Type</option>

            <option value="Image">Image</option>

            <option value="Video">Video</option>

            <option value="Reel">Reel</option>

            <option value="Story">Story</option>

            <option value="Carousel">Carousel</option>

            <option value="Text">Text</option>

        </select>

        <br><br>


        <label for="published_at">Publishing Date:</label>

        <input
            type="datetime-local"
            name="published_at"
            id="published_at"
        >

        <br><br>


        <label for="post_url">Post URL:</label>

        <input
            type="url"
            name="post_url"
            id="post_url"
        >

        <br><br>


        <button type="submit">Add Post</button>

    </form>


    <hr>


    <!-- DISPLAY POSTS -->

    <h2>Registered Posts</h2>

    <table border="1" cellpadding="10">

        <thead>

            <tr>

                <th>Post ID</th>

                <th>Client</th>

                <th>Brand</th>

                <th>Account</th>

                <th>Platform</th>

                <th>Caption</th>

                <th>Post Type</th>

                <th>Published At</th>

                <th>Post URL</th>

            </tr>

        </thead>


        <tbody>

            <?php if (count($posts) > 0): ?>

                <?php foreach ($posts as $post): ?>

                    <tr>

                        <td><?= (int) $post['post_id'] ?></td>

                        <td><?= htmlspecialchars($post['client_name']) ?></td>

                        <td><?= htmlspecialchars($post['brand_name']) ?></td>

                        <td><?= htmlspecialchars($post['account_name']) ?></td>

                        <td><?= htmlspecialchars($post['platform']) ?></td>

                        <td><?= htmlspecialchars($post['caption'] ?? '') ?></td>

                        <td><?= htmlspecialchars($post['post_type']) ?></td>

                        <td><?= htmlspecialchars($post['published_at'] ?? 'Not published') ?></td>

                        <td>

                            <?php if (!empty($post['post_url'])): ?>

                                <a
                                    href="<?= htmlspecialchars($post['post_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    View Post
                                </a>

                            <?php else: ?>

                                N/A

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="9">No posts found.</td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>
</html>