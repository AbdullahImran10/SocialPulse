<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check required fields
    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    }

    // Validate email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {

        // Find user
        $stmt = $pdo->prepare(
            "SELECT user_id, name, email, password_hash, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify credentials and account status
        if (
            $user &&
            $user['status'] === 'Active' &&
            password_verify($password, $user['password_hash'])
        ) {

            // Get user's role
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

                // Regenerate session ID
                session_regenerate_id(true);

                // Store user information
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $role['role_name'];

                // Record successful login
                logActivity(
                    $pdo,
                    $user['user_id'],
                    'Login',
                    'User logged in successfully.'
                );

                header('Location: dashboard.php');
                exit;
            } else {

                $error = 'Your account has no assigned role. Contact the administrator.';

                logActivity(
                    $pdo,
                    $user['user_id'],
                    'Login Failed',
                    'Login attempted but user has no assigned role.'
                );
            }
        } else {

            $error = 'Incorrect email or password.';

            // Record failed login
            logActivity(
                $pdo,
                $user ? $user['user_id'] : null,
                'Login Failed',
                'Invalid credentials or inactive account.'
            );
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/login.css">
    <title>SocialPulse Login</title>
</head>

<body>

    <h1>SocialPulse Login</h1>
    <?php if ($error !== ''): ?>
        <div class="error-message">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <label for="email">Email:</label>

        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required>

        <br><br>

        <label for="password">Password:</label>

        <input
            type="password"
            id="password"
            name="password"
            required>

        <br><br>

        <button type="submit">Login</button>

    </form>

</body>

</html>