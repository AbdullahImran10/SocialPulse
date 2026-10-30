<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('Admin');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | SocialPulse</title>
</head>
<body>

    <h1>SocialPulse Admin Panel</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?>!
    </p>

    <p>You have Admin access.</p>

    <a href="../dashboard.php">Back to Dashboard</a>
    <br><br>
    <a href="../logout.php">Logout</a>

</body>
</html>