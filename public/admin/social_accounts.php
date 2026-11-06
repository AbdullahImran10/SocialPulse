<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireRole('Admin');

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

        $brandId = (int) ($_POST['brand_id'] ?? 0);
        $platform = $_POST['platform'] ?? '';
        $accountName = trim($_POST['account_name'] ?? '');
        $platformAccountId = trim($_POST['platform_account_id'] ?? '');

        if ($brandId <= 0 || $accountName === '') {

            $error = 'Please complete all required fields.';

        } elseif (!in_array($platform, ['Instagram', 'Facebook'], true)) {

            $error = 'Invalid social media platform.';

        } else {

            // Check whether brand exists
            $stmt = $pdo->prepare(
                "SELECT brand_id FROM brands WHERE brand_id = ?"
            );

            $stmt->execute([$brandId]);

            if (!$stmt->fetch()) {

                $error = 'Selected brand does not exist.';

            } else {

                // Insert social account
                $stmt = $pdo->prepare(
                    "INSERT INTO social_accounts
                    (brand_id, platform, account_name, platform_account_id)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $brandId,
                    $platform,
                    $accountName,
                    $platformAccountId !== '' ? $platformAccountId : null
                ]);

                $message = 'Social account added successfully!';

            }

        }

    }

}


// =====================================
// FETCH BRANDS
// =====================================

$stmt = $pdo->query(
    "SELECT
        b.brand_id,
        b.brand_name,
        c.client_name

     FROM brands b

     JOIN clients c
        ON b.client_id = c.client_id

     ORDER BY b.brand_name"
);

$brands = $stmt->fetchAll();


// =====================================
// FETCH SOCIAL ACCOUNTS
// =====================================

$stmt = $pdo->query(
    "SELECT
        sa.account_id,
        sa.platform,
        sa.account_name,
        sa.platform_account_id,
        b.brand_name,
        c.client_name

     FROM social_accounts sa

     JOIN brands b
        ON sa.brand_id = b.brand_id

     JOIN clients c
        ON b.client_id = c.client_id

     ORDER BY sa.account_id DESC"
);

$accounts = $stmt->fetchAll();
$totalAccounts = count($accounts);
$instagramAccounts = 0;
$facebookAccounts = 0;

foreach ($accounts as $account) {
    if ($account['platform'] === 'Instagram') {
        $instagramAccounts++;
    } elseif ($account['platform'] === 'Facebook') {
        $facebookAccounts++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Social Accounts | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/social_accounts.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <header class="page-header">
        <div>
            <h1>Social Accounts</h1>
            <p>Manage connected social media accounts across your clients.</p>
        </div>

        <a href="../dashboard.php" class="back-btn">
            Back to Dashboard
        </a>
    </header>


    <!-- MESSAGES -->
    <?php if ($message !== ''): ?>
        <div class="alert success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <!-- STATISTICS -->
    <section class="stats-container">

        <div class="stat-card">
            <span>Total Accounts</span>
            <h2><?= $totalAccounts ?></h2>
        </div>

        <div class="stat-card">
            <span>Instagram Accounts</span>
            <h2><?= $instagramAccounts ?></h2>
        </div>

        <div class="stat-card">
            <span>Facebook Accounts</span>
            <h2><?= $facebookAccounts ?></h2>
        </div>

    </section>


    <!-- ADD ACCOUNT FORM -->
    <section class="content-card">

        <div class="section-header">
            <h2>Add Social Account</h2>
            <p>Connect a social media account to an existing brand.</p>
        </div>

        <form method="POST" class="account-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label for="brand_id">Select Brand</label>

                    <select name="brand_id" id="brand_id" required>
                        <option value="">Select Brand</option>

                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int) $brand['brand_id'] ?>">
                                <?= htmlspecialchars($brand['brand_name']) ?>
                                -
                                <?= htmlspecialchars($brand['client_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>


                <div class="form-group">
                    <label for="platform">Platform</label>

                    <select name="platform" id="platform" required>
                        <option value="">Select Platform</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Facebook">Facebook</option>
                    </select>
                </div>


                <div class="form-group">
                    <label for="account_name">Account Name</label>

                    <input
                        type="text"
                        name="account_name"
                        id="account_name"
                        placeholder="e.g. @nike"
                        maxlength="255"
                        required
                    >
                </div>


                <div class="form-group">
                    <label for="platform_account_id">
                        Platform Account ID
                        <span>(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="platform_account_id"
                        id="platform_account_id"
                        placeholder="Enter platform account ID"
                        maxlength="255"
                    >
                </div>

            </div>

            <button type="submit" class="submit-btn">
                + Add Account
            </button>

        </form>

    </section>


    <!-- REGISTERED ACCOUNTS -->
    <section class="content-card">

        <div class="section-header">
            <h2>Registered Accounts</h2>
            <p>All social media accounts registered in SocialPulse.</p>
        </div>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Brand</th>
                        <th>Platform</th>
                        <th>Account Name</th>
                        <th>Platform Account ID</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($accounts) > 0): ?>

                        <?php foreach ($accounts as $account): ?>

                            <tr>
                                <td>#<?= (int) $account['account_id'] ?></td>

                                <td>
                                    <?= htmlspecialchars($account['client_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($account['brand_name']) ?>
                                </td>

                                <td>
                                    <span class="platform-badge <?= strtolower($account['platform']) ?>">
                                        <?= htmlspecialchars($account['platform']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($account['account_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($account['platform_account_id'] ?? 'N/A') ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                No social accounts have been added yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

</body>
</html>