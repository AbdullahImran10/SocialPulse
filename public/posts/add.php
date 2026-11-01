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

    <title>Add Social Media Post | SocialPulse</title>
</head>

<body>

    <h1>Add Social Media Post</h1>
    <?php if (isset($_GET['success'])): ?>

        <p>Post and analytics saved successfully!</p>

    <?php endif; ?>

    <p>Enter post information and its analytics below.</p>

    <?php if (empty($accounts)): ?>

        <p>No social media accounts are available.</p>

    <?php else: ?>

        <form action="process.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <label>Brand:</label>

            <select name="brand_id" required>

                <option value="">Select Brand</option>

                <?php foreach ($brands as $brand): ?>

                    <option value="<?= $brand['brand_id'] ?>">
                        <?= htmlspecialchars($brand['brand_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <br><br>

            <label>Social Media Account:</label>

            <select name="account_id" required>

                <option value="">Select Account</option>

                <?php foreach ($accounts as $account): ?>

                    <option
                        value="<?= $account['account_id'] ?>"
                        data-brand="<?= $account['brand_id'] ?>">
                        <?= htmlspecialchars(
                            $account['brand_name'] . ' - ' .
                                $account['platform'] . ' - ' .
                                $account['account_name']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <br><br>

            <label>Caption:</label>

            <textarea
                name="caption"
                rows="4"
                placeholder="Enter post caption"></textarea>

            <br><br>

            <label>Post Type:</label>

            <select name="post_type">

                <option value="Image">Image</option>
                <option value="Video">Video</option>
                <option value="Reel">Reel</option>
                <option value="Story">Story</option>
                <option value="Carousel">Carousel</option>

            </select>

            <br><br>

            <label>Published Date:</label>

            <input
                type="datetime-local"
                name="published_at"
                required>

            <br><br>

            <label>Post URL:</label>

            <input
                type="url"
                name="post_url"
                maxlength="500"
                placeholder="https://...">

            <br><br>

            <h3>Post Analytics</h3>

            <label>Views:</label>
            <input type="number" name="views" min="0" value="0" required>

            <br><br>

            <label>Likes:</label>
            <input type="number" name="likes" min="0" value="0" required>

            <br><br>

            <label>Comments:</label>
            <input type="number" name="comments" min="0" value="0" required>

            <br><br>

            <label>Shares:</label>
            <input type="number" name="shares" min="0" value="0" required>

            <br><br>

            <label>Reach:</label>
            <input type="number" name="reach" min="0" value="0" required>

            <br><br>

            <button type="submit">Save Post</button>

        </form>

    <?php endif; ?>
    <script>
        const brandSelect = document.querySelector('[name="brand_id"]');
        const accountSelect = document.querySelector('[name="account_id"]');

        const allAccounts = Array.from(accountSelect.options);

        brandSelect.addEventListener('change', function() {

            const selectedBrand = this.value;

            accountSelect.innerHTML = '<option value="">Select Account</option>';

            allAccounts.forEach(option => {

                if (
                    option.dataset.brand === selectedBrand &&
                    option.value !== ''
                ) {
                    accountSelect.appendChild(option.cloneNode(true));
                }

            });

        });
    </script>

</body>

</html>