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
$totalClients = count($clients);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Client Management | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/clients.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <header class="page-header">
        <div>
            <h1>Client Management</h1>
            <p>Create and manage clients registered on SocialPulse.</p>
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
            <span>Total Clients</span>
            <h2><?= $totalClients ?></h2>
        </div>

    </section>


    <!-- CREATE CLIENT -->
    <section class="content-card">

        <div class="section-header">
            <h2>Create New Client</h2>
            <p>Register a new client to manage their brands and social accounts.</p>
        </div>

        <form method="POST" class="client-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">

                <label for="client_name">Client Name</label>

                <input
                    type="text"
                    id="client_name"
                    name="client_name"
                    maxlength="255"
                    placeholder="Enter client name"
                    required
                >

            </div>

            <button type="submit" class="submit-btn">
                + Create Client
            </button>

        </form>

    </section>


    <!-- REGISTERED CLIENTS -->
    <section class="content-card">

        <div class="section-header">
            <h2>Registered Clients</h2>
            <p>All clients currently registered in SocialPulse.</p>
        </div>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>Client ID</th>
                        <th>Client Name</th>
                        <th>Created At</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($clients) > 0): ?>

                        <?php foreach ($clients as $client): ?>

                            <tr>

                                <td>
                                    #<?= (int) $client['client_id'] ?>
                                </td>

                                <td class="client-name">
                                    <?= htmlspecialchars($client['client_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($client['created_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty-state">
                                No clients have been registered yet.
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