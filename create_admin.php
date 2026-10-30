<?php

require_once __DIR__ . '/config/database.php';

$email = 'admin@socialpulse.local';
$password = 'Admin123!';

try {
    $pdo->beginTransaction();

    // Check whether the Admin account already exists
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        $pdo->rollBack();
        exit('Admin account already exists.');
    }

    // Hash the password before storing it
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Create the Admin user
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        'SocialPulse Admin',
        $email,
        $passwordHash
    ]);

    $userId = $pdo->lastInsertId();

    // Find the Admin role
    $stmt = $pdo->prepare(
        "SELECT role_id FROM roles WHERE role_name = ?"
    );

    $stmt->execute(['Admin']);
    $role = $stmt->fetch();

    if (!$role) {
        throw new Exception('Admin role was not found.');
    }

    // Assign the Admin role to the user
    $stmt = $pdo->prepare(
        "INSERT INTO user_roles (user_id, role_id)
         VALUES (?, ?)"
    );

    $stmt->execute([$userId, $role['role_id']]);

    $pdo->commit();

    echo 'Admin account created successfully!';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Could not create Admin account. Check the PHP error log.');
}