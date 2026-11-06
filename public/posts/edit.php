<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if (!hasRole('Admin') && !hasRole('Social Media Manager')) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = getUserId();
$userRole = getUserRole();

// Validate post ID
$postId = $_GET['id'] ?? '';

if (!ctype_digit((string)$postId) || (int)$postId < 1) {
    http_response_code(400);
    exit('Invalid post ID.');
}

try {

    // Get the post only if the user is authorized to access it
    if ($userRole === 'Admin') {

        $sql = "SELECT p.*, sa.brand_id
                FROM posts p
                INNER JOIN social_accounts sa
                    ON p.account_id = sa.account_id
                WHERE p.post_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$postId]);

    } else {

        $sql = "SELECT p.*, sa.brand_id
                FROM posts p
                INNER JOIN social_accounts sa
                    ON p.account_id = sa.account_id
                INNER JOIN brand_members bm
                    ON sa.brand_id = bm.brand_id
                WHERE p.post_id = ?
                AND bm.user_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$postId, $userId]);
    }

    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        exit('Post not found or access denied.');
    }

    // Get accounts accessible to this user
    if ($userRole === 'Admin') {

        $sql = "SELECT sa.account_id, sa.platform, sa.account_name,
                       sa.brand_id, b.brand_name
                FROM social_accounts sa
                INNER JOIN brands b
                    ON sa.brand_id = b.brand_id
                ORDER BY b.brand_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

    } else {

        $sql = "SELECT DISTINCT sa.account_id, sa.platform,
                       sa.account_name, sa.brand_id, b.brand_name
                FROM social_accounts sa
                INNER JOIN brands b
                    ON sa.brand_id = b.brand_id
                INNER JOIN brand_members bm
                    ON b.brand_id = bm.brand_id
                WHERE bm.user_id = ?
                ORDER BY b.brand_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to load post.');

}

// Format date for datetime-local input
$publishedAt = date(
    'Y-m-d\TH:i',
    strtotime($post['published_at'])
);

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Post | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/edit_post.css">
</head>

<body>

    <div class="page-container">

        <header class="page-header">

            <div>
                <span class="page-label">CONTENT MANAGEMENT</span>
                <h1>Edit Social Media Post</h1>
                <p>Update your post information and publishing details.</p>
            </div>

            <a href="../posts.php" class="back-btn">
                Back to Posts
            </a>

        </header>

        <section class="form-card">

            <div class="form-heading">
                <h2>Post Information</h2>
                <p>Make changes to the selected post below.</p>
            </div>

            <form action="update.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="post_id"
                    value="<?= escape($post['post_id']) ?>"
                >

                <div class="form-grid">

                    <!-- Brand -->
                    <div class="form-group">

                        <label for="brandSelect">Brand</label>

                        <select name="brand_id" id="brandSelect" required>

                            <option value="">Select Brand</option>

                            <?php
                            $displayedBrands = [];

                            foreach ($accounts as $account):

                                if (isset($displayedBrands[$account['brand_id']])) {
                                    continue;
                                }

                                $displayedBrands[$account['brand_id']] = true;
                            ?>

                                <option
                                    value="<?= escape($account['brand_id']) ?>"
                                    <?= $account['brand_id'] == $post['brand_id'] ? 'selected' : '' ?>>

                                    <?= escape($account['brand_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Social Account -->
                    <div class="form-group">

                        <label for="accountSelect">Social Media Account</label>

                        <select name="account_id" id="accountSelect" required>

                            <?php foreach ($accounts as $account): ?>

                                <option
                                    value="<?= escape($account['account_id']) ?>"
                                    data-brand="<?= escape($account['brand_id']) ?>"
                                    <?= $account['account_id'] == $post['account_id'] ? 'selected' : '' ?>>

                                    <?= escape(
                                        $account['brand_name'] . ' - ' .
                                        $account['platform'] . ' - ' .
                                        $account['account_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Post Type -->
                    <div class="form-group">

                        <label for="post_type">Post Type</label>

                        <select name="post_type" id="post_type" required>

                            <?php
                            $types = ['Image', 'Video', 'Reel', 'Story', 'Carousel'];

                            foreach ($types as $type):
                            ?>

                                <option
                                    value="<?= escape($type) ?>"
                                    <?= $post['post_type'] === $type ? 'selected' : '' ?>>

                                    <?= escape($type) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Published Date -->
                    <div class="form-group">

                        <label for="published_at">Published Date</label>

                        <input
                            type="datetime-local"
                            id="published_at"
                            name="published_at"
                            value="<?= escape($publishedAt) ?>"
                            required
                        >

                    </div>

                    <!-- Caption -->
                    <div class="form-group full-width">

                        <label for="caption">Caption</label>

                        <textarea
                            id="caption"
                            name="caption"
                            rows="5"
                        ><?= escape($post['caption']) ?></textarea>

                    </div>

                    <!-- Post URL -->
                    <div class="form-group full-width">

                        <label for="post_url">Post URL</label>

                        <input
                            type="url"
                            id="post_url"
                            name="post_url"
                            maxlength="500"
                            value="<?= escape($post['post_url'] ?? '') ?>"
                            placeholder="https://..."
                        >

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="update-btn">
                        Update Post
                    </button>

                    <a href="../posts.php" class="cancel-btn">
                        Cancel
                    </a>

                </div>

            </form>

        </section>

    </div>

    <script>
        const brandSelect = document.getElementById('brandSelect');
        const accountSelect = document.getElementById('accountSelect');

        const allAccounts = Array.from(accountSelect.options).map(option => ({
            value: option.value,
            text: option.textContent.trim(),
            brand: option.dataset.brand
        }));

        function filterAccounts() {

            const selectedBrand = brandSelect.value;
            const currentAccount = accountSelect.value;

            accountSelect.innerHTML = '';

            let selectedAccountExists = false;

            allAccounts.forEach(account => {

                if (account.brand === selectedBrand) {

                    const option = document.createElement('option');

                    option.value = account.value;
                    option.textContent = account.text;
                    option.dataset.brand = account.brand;

                    if (account.value === currentAccount) {
                        option.selected = true;
                        selectedAccountExists = true;
                    }

                    accountSelect.appendChild(option);
                }
            });

            if (!selectedAccountExists && accountSelect.options.length > 0) {
                accountSelect.selectedIndex = 0;
            }
        }

        brandSelect.addEventListener('change', filterAccounts);

        filterAccounts();
    </script>

</body>
</html>