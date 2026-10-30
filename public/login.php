<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check that both fields are filled
    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    }

    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    else {

        // Find the user
        $stmt = $pdo->prepare(
            "SELECT user_id, name, email, password_hash, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password and account status
        if (
            $user &&
            $user['status'] === 'Active' &&
            password_verify($password, $user['password_hash'])
        ) {

            // Get the user's role
            $stmt = $pdo->prepare(
                "SELECT r.role_name
                 FROM roles r
                 JOIN user_roles ur ON r.role_id = ur.role_id
                 WHERE ur.user_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$user['user_id']]);
            $role = $stmt->fetch();

            if ($role) {

                // Regenerate session ID after login
                session_regenerate_id(true);

                // Store user information in the session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $role['role_name'];

                header('Location: dashboard.php');
                exit;

            } else {
                $error = 'Your account has no assigned role. Contact the administrator.';
            }

        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialPulse Login</title>
</head>

<body>

    <h1>SocialPulse Login</h1>

    <?php if ($error !== ''): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <label for="email">Email:</label>

        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="password">Password:</label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <br><br>

        <button type="submit">Login</button>

    </form>

</body>

</html>