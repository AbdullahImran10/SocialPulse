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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Social Account Management | SocialPulse</title>

</head>

<body>

    <h1>Social Account Management</h1>

    <a href="../dashboard.php">Back to Dashboard</a>

    <hr>


    <!-- MESSAGES -->

    <?php if ($message !== ''): ?>

        <p><?= htmlspecialchars($message) ?></p>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <p><?= htmlspecialchars($error) ?></p>

    <?php endif; ?>


    <!-- ADD SOCIAL ACCOUNT -->

    <h2>Add Social Account</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label for="brand_id">Select Brand:</label>

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

        <br><br>


        <label for="platform">Platform:</label>

        <select name="platform" id="platform" required>

            <option value="">Select Platform</option>

            <option value="Instagram">Instagram</option>

            <option value="Facebook">Facebook</option>

        </select>

        <br><br>


        <label for="account_name">Account Name:</label>

        <input
            type="text"
            name="account_name"
            id="account_name"
            required
        >

        <br><br>


        <label for="platform_account_id">
            Platform Account ID (Optional):
        </label>

        <input
            type="text"
            name="platform_account_id"
            id="platform_account_id"
        >

        <br><br>


        <button type="submit">Add Account</button>

    </form>


    <hr>


    <!-- REGISTERED ACCOUNTS -->

    <h2>Registered Social Accounts</h2>

    <table border="1" cellpadding="10">

        <thead>

            <tr>

                <th>Account ID</th>

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

                        <td>
                            <?= (int) $account['account_id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($account['client_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($account['brand_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($account['platform']) ?>
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

                    <td colspan="6">
                        No social accounts have been added yet.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>

</html>