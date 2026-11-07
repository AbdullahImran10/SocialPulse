<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireRole('Admin');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please try again.';
    } else {

        $action = $_POST['action'] ?? '';

        // Assign brand
        if ($action === 'assign') {

            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $brandId = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);

            if (!$userId || !$brandId) {
                $error = 'Please select a valid user and brand.';
            } else {

                // Get user and role
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.client_id, u.status, r.role_name
                    FROM users u
                    JOIN user_roles ur ON u.user_id = ur.user_id
                    JOIN roles r ON ur.role_id = r.role_id
                    WHERE u.user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                // Get brand
                $stmt = $pdo->prepare("
                    SELECT brand_id, client_id
                    FROM brands
                    WHERE brand_id = ?
                ");

                $stmt->execute([$brandId]);
                $brand = $stmt->fetch();

                if (!$user || !$brand) {
                    $error = 'Selected user or brand does not exist.';
                } elseif ($user['status'] !== 'Active') {
                    $error = 'You cannot assign a brand to an inactive user.';
                } elseif (
                    !in_array(
                        $user['role_name'],
                        ['Client', 'Social Media Manager'],
                        true
                    )
                ) {
                    $error = 'Only Clients and Social Media Managers can be assigned brands.';
                } elseif (
                    $user['role_name'] === 'Client' &&
                    $user['client_id'] != $brand['client_id']
                ) {
                    $error = 'A Client can only be assigned brands belonging to their client account.';
                } else {

                    // Check for existing assignment
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM brand_members
                        WHERE user_id = ? AND brand_id = ?
                    ");

                    $stmt->execute([$userId, $brandId]);

                    if ($stmt->fetchColumn() > 0) {
                        $error = 'This brand is already assigned to this user.';
                    } else {

                        $stmt = $pdo->prepare("
                            INSERT INTO brand_members (brand_id, user_id)
                            VALUES (?, ?)
                        ");

                        $stmt->execute([$brandId, $userId]);

                        $message = 'Brand assigned successfully.';
                    }
                }
            }
        }

        // Remove assignment
        elseif ($action === 'remove') {

            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $brandId = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);

            if (!$userId || !$brandId) {
                $error = 'Invalid assignment.';
            } else {

                $stmt = $pdo->prepare("
                    DELETE FROM brand_members
                    WHERE user_id = ? AND brand_id = ?
                ");

                $stmt->execute([$userId, $brandId]);

                if ($stmt->rowCount() > 0) {
                    $message = 'Brand assignment removed successfully.';
                } else {
                    $error = 'Assignment not found.';
                }
            }
        }
    }
}

// Get eligible users
$stmt = $pdo->query("
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.client_id,
        r.role_name
    FROM users u
    JOIN user_roles ur ON u.user_id = ur.user_id
    JOIN roles r ON ur.role_id = r.role_id
    WHERE u.status = 'Active'
      AND r.role_name IN ('Client', 'Social Media Manager')
    ORDER BY u.name
");

$users = $stmt->fetchAll();

// Get brands
$stmt = $pdo->query("
    SELECT
        b.brand_id,
        b.brand_name,
        b.client_id,
        c.client_name
    FROM brands b
    JOIN clients c ON b.client_id = c.client_id
    ORDER BY b.brand_name
");

$brands = $stmt->fetchAll();

// Get existing assignments
$stmt = $pdo->query("
    SELECT
        bm.user_id,
        bm.brand_id,
        u.name AS user_name,
        u.email,
        r.role_name,
        b.brand_name,
        c.client_name
    FROM brand_members bm
    JOIN users u ON bm.user_id = u.user_id
    JOIN brands b ON bm.brand_id = b.brand_id
    JOIN clients c ON b.client_id = c.client_id
    JOIN user_roles ur ON u.user_id = ur.user_id
    JOIN roles r ON ur.role_id = r.role_id
    ORDER BY u.name, b.brand_name
");

$assignments = $stmt->fetchAll();

// Statistics
$totalAssignments = count($assignments);
$totalUsers = count($users);
$totalBrands = count($brands);

function escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Brand Assignments | SocialPulse</title>

    <link rel="stylesheet" href="../../assets/css/brand_members.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
</head>

<body>

    <div class="page-container">

        <!-- Header -->
        <header class="page-header">

            <div>
                <span class="page-label">ADMINISTRATION</span>
                <h1>Brand Assignments</h1>
                <p>Manage which brands are accessible to users.</p>
            </div>

            <a href="../dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </header>

        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="alert success">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <section class="stats-grid">

            <div class="stat-card">
                <span>Total Assignments</span>
                <h2><?= $totalAssignments ?></h2>
            </div>

            <div class="stat-card">
                <span>Eligible Users</span>
                <h2><?= $totalUsers ?></h2>
            </div>

            <div class="stat-card">
                <span>Available Brands</span>
                <h2><?= $totalBrands ?></h2>
            </div>

        </section>

        <!-- Assignment Form -->
        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Assign Brand to User</h2>
                    <p>Select a user and the brand they should access.</p>
                </div>
            </div>

            <form method="POST" class="assignment-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <input type="hidden" name="action" value="assign">

                <div class="form-group">

                    <label for="user_id">Select User</label>

                    <select name="user_id" id="user_id" required>

                        <option value="">Choose a user</option>

                        <?php foreach ($users as $user): ?>

                            <option value="<?= escape($user['user_id']) ?>">
                                <?= escape($user['name']) ?>
                                (<?= escape($user['role_name']) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="brand_id">Select Brand</label>

                    <select name="brand_id" id="brand_id" required>

                        <option value="">Choose a brand</option>

                        <?php foreach ($brands as $brand): ?>

                            <option value="<?= escape($brand['brand_id']) ?>">
                                <?= escape($brand['brand_name']) ?>
                                - <?= escape($brand['client_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button type="submit" class="primary-btn">
                    + Assign Brand
                </button>

            </form>

            <div class="form-note">
                Clients can only access brands belonging to their own client account.
                Social Media Managers can be assigned brands individually.
            </div>

        </section>

        <!-- Existing Assignments -->
        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Existing Assignments</h2>
                    <p>View and manage current user-brand access.</p>
                </div>

                <span class="count-badge">
                    <?= $totalAssignments ?> Assignments
                </span>
            </div>

            <?php if (empty($assignments)): ?>

                <div class="empty-state">
                    <h3>No assignments found</h3>
                    <p>Start by assigning a brand to a user above.</p>
                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Brand</th>
                                <th>Client</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($assignments as $assignment): ?>

                                <tr>

                                    <td class="user-name">
                                        <?= escape($assignment['user_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($assignment['email']) ?>
                                    </td>

                                    <td>
                                        <span class="role-badge">
                                            <?= escape($assignment['role_name']) ?>
                                        </span>
                                    </td>

                                    <td class="brand-name">
                                        <?= escape($assignment['brand_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($assignment['client_name']) ?>
                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Remove this brand assignment?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escape($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="remove"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= escape($assignment['user_id']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="brand_id"
                                                value="<?= escape($assignment['brand_id']) ?>"
                                            >

                                            <button type="submit" class="remove-btn">
                                                Remove
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</body>
</html>