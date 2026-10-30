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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request.';
    }

    else {

        $clientName = trim($_POST['client_name'] ?? '');

        if ($clientName === '') {
            $error = 'Client name is required.';
        }

        else {

            $stmt = $pdo->prepare(
                "INSERT INTO clients (client_name)
                 VALUES (?)"
            );

            $stmt->execute([$clientName]);

            $message = 'Client created successfully!';
        }
    }
}

// Get all clients
$stmt = $pdo->query(
    "SELECT client_id, client_name, created_at
     FROM clients
     ORDER BY client_id DESC"
);

$clients = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Client Management | SocialPulse</title>
</head>

<body>

    <h1>Client Management</h1>

    <a href="../dashboard.php">Back to Dashboard</a>

    <hr>

    <h2>Create Client</h2>

    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label for="client_name">Client Name:</label>

        <input
            type="text"
            id="client_name"
            name="client_name"
            required
        >

        <button type="submit">Create Client</button>

    </form>

    <hr>

    <h2>Registered Clients</h2>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Client Name</th>
            <th>Created At</th>
        </tr>

        <?php foreach ($clients as $client): ?>

            <tr>

                <td><?= $client['client_id'] ?></td>

                <td>
                    <?= htmlspecialchars($client['client_name']) ?>
                </td>

                <td><?= $client['created_at'] ?></td>

            </tr>

        <?php endforeach; ?>

    </table>

</body>
</html>