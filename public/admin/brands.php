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
// CREATE BRAND
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $error = 'Invalid request. Please try again.';

    } else {

        $brandName = trim($_POST['brand_name'] ?? '');
        $clientId = (int) ($_POST['client_id'] ?? 0);

        if ($brandName === '') {

            $error = 'Brand name is required.';

        } elseif ($clientId <= 0) {

            $error = 'Please select a client.';

        } else {

            // Check whether client exists
            $stmt = $pdo->prepare(
                "SELECT client_id FROM clients WHERE client_id = ?"
            );

            $stmt->execute([$clientId]);

            $client = $stmt->fetch();

            if (!$client) {

                $error = 'Selected client does not exist.';

            } else {

                // Check for duplicate brand under the same client
                $stmt = $pdo->prepare(
                    "SELECT brand_id
                     FROM brands
                     WHERE client_id = ? AND brand_name = ?"
                );

                $stmt->execute([$clientId, $brandName]);

                if ($stmt->fetch()) {

                    $error = 'This brand already exists for this client.';

                } else {

                    // Insert brand
                    $stmt = $pdo->prepare(
                        "INSERT INTO brands (client_id, brand_name)
                         VALUES (?, ?)"
                    );

                    $stmt->execute([$clientId, $brandName]);

                    $message = 'Brand created successfully!';

                }

            }

        }

    }

}


// =====================================
// FETCH CLIENTS
// =====================================

$stmt = $pdo->query(
    "SELECT client_id, client_name
     FROM clients
     ORDER BY client_name"
);

$clients = $stmt->fetchAll();


// =====================================
// FETCH BRANDS
// =====================================

$stmt = $pdo->query(
    "SELECT
        b.brand_id,
        b.brand_name,
        b.created_at,
        c.client_name

     FROM brands b

     INNER JOIN clients c
        ON b.client_id = c.client_id

     ORDER BY b.brand_id DESC"
);

$brands = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Brand Management | SocialPulse</title>

</head>

<body>

    <h1>Brand Management</h1>

    <a href="../dashboard.php">Back to Dashboard</a>

    <hr>


    <!-- MESSAGES -->

    <?php if ($message !== ''): ?>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <p>
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- CREATE BRAND FORM -->

    <h2>Create Brand</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >


        <label for="brand_name">Brand Name:</label>

        <input
            type="text"
            id="brand_name"
            name="brand_name"
            required
        >

        <br><br>


        <label for="client_id">Select Client:</label>

        <select
            name="client_id"
            id="client_id"
            required
        >

            <option value="">Select Client</option>

            <?php foreach ($clients as $client): ?>

                <option value="<?= (int) $client['client_id'] ?>">

                    <?= htmlspecialchars($client['client_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <br><br>


        <button type="submit">Create Brand</button>

    </form>


    <hr>


    <!-- REGISTERED BRANDS -->

    <h2>Registered Brands</h2>

    <table border="1" cellpadding="10">

        <thead>

            <tr>

                <th>Brand ID</th>

                <th>Brand Name</th>

                <th>Client Name</th>

                <th>Created At</th>

            </tr>

        </thead>


        <tbody>

            <?php if (count($brands) > 0): ?>

                <?php foreach ($brands as $brand): ?>

                    <tr>

                        <td>
                            <?= (int) $brand['brand_id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($brand['brand_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($brand['client_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($brand['created_at']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="4">
                        No brands have been created yet.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>

</html>