<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if (
    !hasRole('Admin') &&
    !hasRole('Social Media Manager')
) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = getUserId();
$userRole = getUserRole();

// Fetch accessible brands
if ($userRole === 'Admin') {

    $sql = "SELECT brand_id, brand_name
            FROM brands";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {

    $sql = "SELECT b.brand_id, b.brand_name
            FROM brands b
            INNER JOIN brand_members bm
                ON b.brand_id = bm.brand_id
            WHERE bm.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
}

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>CSV Import | SocialPulse</title>
</head>

<body>

    <h1>Import Social Media Analytics</h1>

    <form action="process.php" method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label>Select Brand:</label>

        <select name="brand_id" id="brand_id" required>

            <option value="">Select Brand</option>

            <?php foreach ($brands as $brand): ?>

                <option value="<?= $brand['brand_id'] ?>">
                    <?= htmlspecialchars($brand['brand_name']) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <br><br>

        <label>Select Social Media Account:</label>

        <select name="account_id" id="account_id" required disabled>

            <option value="">Select Account</option>

        </select>

        <br><br>

        <label>Upload CSV:</label>

        <input
            type="file"
            name="csv_file"
            accept=".csv,text/csv"
            required>

        <br><br>

        <button type="submit">Import CSV</button>

    </form>

    <script>
        const brandSelect = document.getElementById('brand_id');
        const accountSelect = document.getElementById('account_id');

        brandSelect.addEventListener('change', async function() {

            accountSelect.innerHTML = '<option value="">Loading...</option>';

            accountSelect.disabled = accounts.length === 0;

            if (accounts.length === 0) {
                accountSelect.innerHTML =
                    '<option value="">No accounts available</option>';
            }

            if (!this.value) {
                accountSelect.innerHTML = '<option value="">Select Account</option>';
                return;
            }

            try {

                const response = await fetch(
                    'get_accounts.php?brand_id=' + encodeURIComponent(this.value)
                );

                if (!response.ok) {
                    throw new Error('Unable to load accounts');
                }

                const accounts = await response.json();

                accountSelect.innerHTML = '<option value="">Select Account</option>';

                accounts.forEach(account => {

                    const option = document.createElement('option');

                    option.value = account.account_id;

                    option.textContent =
                        account.platform + ' - ' + account.account_name;

                    accountSelect.appendChild(option);

                });

                accountSelect.disabled = false;

            } catch (error) {

                accountSelect.innerHTML =
                    '<option value="">Unable to load accounts</option>';

            }

        });
    </script>

</body>

</html>