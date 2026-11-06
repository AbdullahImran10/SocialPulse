<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Only Admin and Social Media Manager can add posts
if (
    !hasRole('Admin') &&
    !hasRole('Social Media Manager')
) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = getUserId();
$userRole = getUserRole();

// Get brands accessible to the logged-in user
if ($userRole === 'Admin') {

    $sql = "SELECT brand_id, brand_name
            FROM brands
            ORDER BY brand_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {

    $sql = "SELECT b.brand_id, b.brand_name
            FROM brands b
            INNER JOIN brand_members bm
                ON b.brand_id = bm.brand_id
            WHERE bm.user_id = ?
            ORDER BY b.brand_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
}

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get accessible social accounts
if ($userRole === 'Admin') {

    $sql = "SELECT sa.account_id,
                   sa.platform,
                   sa.account_name,
                   sa.brand_id,
                   b.brand_name
            FROM social_accounts sa
            INNER JOIN brands b
                ON sa.brand_id = b.brand_id
            ORDER BY b.brand_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {

    $sql = "SELECT sa.account_id,
                   sa.platform,
                   sa.account_name,
                   sa.brand_id,
                   b.brand_name
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

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Post | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/add_post.css">
</head>

<body>

    <div class="page-container">

        <header class="page-header">

            <div>
                <span class="page-label">CONTENT MANAGEMENT</span>
                <h1>Add Social Media Post</h1>
                <p>Create a post record and enter its analytics.</p>
            </div>

            <a href="../posts.php" class="back-btn">
                Back to Posts
            </a>

        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                Post and analytics saved successfully!
            </div>
        <?php endif; ?>

        <?php if (empty($accounts)): ?>

            <section class="form-card empty-state">
                <h2>No Social Media Accounts</h2>
                <p>There are no accessible social media accounts. Contact an administrator to add an account or assign one to you.</p>
            </section>

        <?php else: ?>

            <form action="process.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <!-- Post Information -->
                <section class="form-card">

                    <div class="section-heading">
                        <h2>Post Information</h2>
                        <p>Enter the basic details of your social media post.</p>
                    </div>

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="brandSelect">Brand</label>

                            <select name="brand_id" id="brandSelect" required>

                                <option value="">Select Brand</option>

                                <?php foreach ($brands as $brand): ?>

                                    <option value="<?= htmlspecialchars($brand['brand_id']) ?>">
                                        <?= htmlspecialchars($brand['brand_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="accountSelect">Social Media Account</label>

                            <select name="account_id" id="accountSelect" required>

                                <option value="">Select Account</option>

                                <?php foreach ($accounts as $account): ?>

                                    <option
                                        value="<?= htmlspecialchars($account['account_id']) ?>"
                                        data-brand="<?= htmlspecialchars($account['brand_id']) ?>">

                                        <?= htmlspecialchars(
                                            $account['brand_name'] . ' - ' .
                                            $account['platform'] . ' - ' .
                                            $account['account_name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="post_type">Post Type</label>

                            <select name="post_type" id="post_type" required>

                                <option value="Image">Image</option>
                                <option value="Video">Video</option>
                                <option value="Reel">Reel</option>
                                <option value="Story">Story</option>
                                <option value="Carousel">Carousel</option>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="published_at">Published Date</label>

                            <input
                                type="datetime-local"
                                id="published_at"
                                name="published_at"
                                required
                            >

                        </div>

                        <div class="form-group full-width">

                            <label for="caption">Caption</label>

                            <textarea
                                name="caption"
                                id="caption"
                                rows="5"
                                placeholder="Enter post caption..."
                            ></textarea>

                        </div>

                        <div class="form-group full-width">

                            <label for="post_url">Post URL</label>

                            <input
                                type="url"
                                name="post_url"
                                id="post_url"
                                maxlength="500"
                                placeholder="https://..."
                            >

                        </div>

                    </div>

                </section>

                <!-- Analytics -->
                <section class="form-card">

                    <div class="section-heading">
                        <h2>Post Analytics</h2>
                        <p>Enter the performance metrics for this post.</p>
                    </div>

                    <div class="analytics-grid">

                        <div class="form-group">
                            <label for="views">Views</label>
                            <input type="number" id="views" name="views" min="0" value="0" required>
                        </div>

                        <div class="form-group">
                            <label for="likes">Likes</label>
                            <input type="number" id="likes" name="likes" min="0" value="0" required>
                        </div>

                        <div class="form-group">
                            <label for="comments">Comments</label>
                            <input type="number" id="comments" name="comments" min="0" value="0" required>
                        </div>

                        <div class="form-group">
                            <label for="shares">Shares</label>
                            <input type="number" id="shares" name="shares" min="0" value="0" required>
                        </div>

                        <div class="form-group">
                            <label for="reach">Reach</label>
                            <input type="number" id="reach" name="reach" min="0" value="0" required>
                        </div>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="save-btn">
                            Save Post
                        </button>

                        <a href="../posts.php" class="cancel-btn">
                            Cancel
                        </a>

                    </div>

                </section>

            </form>

        <?php endif; ?>

    </div>

    <script>
        const brandSelect = document.getElementById('brandSelect');
        const accountSelect = document.getElementById('accountSelect');

        if (brandSelect && accountSelect) {

            const allAccounts = Array.from(accountSelect.options)
                .filter(option => option.value !== '')
                .map(option => ({
                    value: option.value,
                    text: option.textContent.trim(),
                    brand: option.dataset.brand
                }));

            function filterAccounts() {

                const selectedBrand = brandSelect.value;

                accountSelect.innerHTML =
                    '<option value="">Select Account</option>';

                allAccounts.forEach(account => {

                    if (account.brand === selectedBrand) {

                        const option = document.createElement('option');

                        option.value = account.value;
                        option.textContent = account.text;

                        accountSelect.appendChild(option);
                    }

                });

                accountSelect.disabled = !selectedBrand ||
                    accountSelect.options.length === 1;
            }

            brandSelect.addEventListener('change', filterAccounts);

            filterAccounts();
        }
    </script>

</body>
</html>