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


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please try again.';
    } else {

        $action = $_POST['action'] ?? '';

        // =====================================
        // CREATE USER
        // =====================================

        if ($action === 'create_user') {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $clientId = $_POST['client_id'] ?? '';

            if ($name === '' || $email === '' || $password === '') {

                $error = 'All fields are required.';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $error = 'Please enter a valid email address.';

            } elseif (strlen($password) < 8) {

                $error = 'Password must be at least 8 characters.';

            } else {

                // Check selected role
                $stmt = $pdo->prepare(
                    "SELECT role_name FROM roles WHERE role_id = ?"
                );

                $stmt->execute([$roleId]);

                $role = $stmt->fetch();

                if (!$role) {

                    $error = 'Please select a valid role.';

                } elseif (
                    $role['role_name'] === 'Client' &&
                    $clientId === ''
                ) {

                    $error = 'Please select a client for this user.';

                } else {

                    // Assign client only to Client users
                    if ($role['role_name'] === 'Client') {

                        $stmt = $pdo->prepare(
                            "SELECT client_id FROM clients WHERE client_id = ?"
                        );

                        $stmt->execute([(int) $clientId]);

                        if (!$stmt->fetch()) {

                            $error = 'Invalid client selected.';

                        } else {

                            $clientId = (int) $clientId;

                        }

                    } else {

                        $clientId = null;

                    }


                    // Insert user if validation passed
                    if ($error === '') {

                        try {

                            $pdo->beginTransaction();

                            $passwordHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $stmt = $pdo->prepare(
                                "INSERT INTO users
                                (client_id, name, email, password_hash)
                                VALUES (?, ?, ?, ?)"
                            );

                            $stmt->execute([
                                $clientId,
                                $name,
                                $email,
                                $passwordHash
                            ]);

                            $userId = $pdo->lastInsertId();


                            // Assign role to user
                            $stmt = $pdo->prepare(
                                "INSERT INTO user_roles
                                (user_id, role_id)
                                VALUES (?, ?)"
                            );

                            $stmt->execute([$userId, $roleId]);


                            $pdo->commit();

                            $message = 'User created successfully!';

                        } catch (PDOException $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            if ($e->getCode() === '23000') {

                                $error = 'This email address is already registered.';

                            } else {

                                error_log($e->getMessage());

                                $error = 'Unable to create user.';

                            }

                        }

                    }

                }

            }

        }


        // =====================================
        // CHANGE USER STATUS
        // =====================================

        elseif ($action === 'change_status') {

            $userId = (int) ($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($userId <= 0) {

                $error = 'Invalid user.';

            } elseif (!in_array($status, ['Active', 'Inactive'], true)) {

                $error = 'Invalid status.';

            } elseif (
                $userId === (int) getUserId() &&
                $status === 'Inactive'
            ) {

                $error = 'You cannot deactivate your own account.';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE users SET status = ? WHERE user_id = ?"
                );

                $stmt->execute([$status, $userId]);

                if ($stmt->rowCount() > 0) {

                    $message = 'User status updated successfully!';

                } else {

                    $error = 'User not found or status was not changed.';

                }

            }

        }

    }

}


// =====================================
// FETCH ROLES
// =====================================

$stmt = $pdo->query(
    "SELECT role_id, role_name FROM roles ORDER BY role_id"
);

$roles = $stmt->fetchAll();


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
// FETCH ALL USERS
// =====================================

$stmt = $pdo->query(
    "SELECT
        u.user_id,
        u.name,
        u.email,
        u.status,
        u.created_at,
        r.role_name,
        c.client_name

     FROM users u

     LEFT JOIN user_roles ur
        ON u.user_id = ur.user_id

     LEFT JOIN roles r
        ON ur.role_id = r.role_id

     LEFT JOIN clients c
        ON u.client_id = c.client_id

     ORDER BY u.user_id DESC"
);

$users = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management | SocialPulse</title>

</head>

<body>

    <h1>User Management</h1>

    <a href="../dashboard.php">Back to Dashboard</a>

    <hr>


    <!-- ================================= -->
    <!-- MESSAGES -->
    <!-- ================================= -->

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


    <!-- ================================= -->
    <!-- CREATE USER FORM -->
    <!-- ================================= -->

    <h2>Create User</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="create_user"
        >


        <label for="name">Full Name:</label>

        <input
            type="text"
            id="name"
            name="name"
            required
        >

        <br><br>


        <label for="email">Email:</label>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <br><br>


        <label for="password">Password:</label>

        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >

        <br><br>


        <label for="role_id">Role:</label>

        <select
            name="role_id"
            id="role_id"
            required
        >

            <option value="">Select Role</option>

            <?php foreach ($roles as $role): ?>

                <option value="<?= (int) $role['role_id'] ?>">

                    <?= htmlspecialchars($role['role_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <br><br>


        <label for="client_id">Assign Client:</label>

        <select
            name="client_id"
            id="client_id"
        >

            <option value="">Select Client</option>

            <?php foreach ($clients as $client): ?>

                <option value="<?= (int) $client['client_id'] ?>">

                    <?= htmlspecialchars($client['client_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <br><br>


        <button type="submit">Create User</button>

    </form>


    <hr>


    <!-- ================================= -->
    <!-- REGISTERED USERS -->
    <!-- ================================= -->

    <h2>Registered Users</h2>

    <table border="1" cellpadding="10">

        <thead>

            <tr>

                <th>ID</th>

                <th>Name</th>

                <th>Email</th>

                <th>Role</th>

                <th>Client</th>

                <th>Status</th>

                <th>Created At</th>

                <th>Action</th>

            </tr>

        </thead>


        <tbody>

            <?php foreach ($users as $user): ?>

                <tr>

                    <td>
                        <?= (int) $user['user_id'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['email']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['role_name'] ?? 'No Role') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['client_name'] ?? 'N/A') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['status']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['created_at']) ?>
                    </td>

                    <td>

                        <?php if ((int) $user['user_id'] !== (int) getUserId()): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="change_status"
                                >

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= (int) $user['user_id'] ?>"
                                >

                                <?php if ($user['status'] === 'Active'): ?>

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Inactive"
                                    >

                                    <button type="submit">
                                        Deactivate
                                    </button>

                                <?php else: ?>

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Active"
                                    >

                                    <button type="submit">
                                        Activate
                                    </button>

                                <?php endif; ?>

                            </form>

                        <?php else: ?>

                            Current Account

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</body>

</html>