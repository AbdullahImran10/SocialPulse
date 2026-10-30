<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SocialPulse Dashboard</title>
</head>
<body>

    <h1>SocialPulse Dashboard</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?>!
    </p>

    <p>
        Your role:
        <?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php if (hasRole('Admin')): ?>

        <a href="admin/index.php">Open Admin Panel</a>

    <?php elseif (hasRole('Social Media Manager')): ?>

        <a href="manager/index.php">Open Manager Panel</a>

    <?php elseif (hasRole('Client')): ?>

        <a href="client/index.php">Open Client Panel</a>

    <?php endif; ?>

    <br><br>

    <a href="logout.php">Logout</a>

</body>
</html>