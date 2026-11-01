<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

header('Content-Type: application/json');

if (
    !hasRole('Admin') &&
    !hasRole('Social Media Manager')
) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$brandId = $_GET['brand_id'] ?? '';

if (!ctype_digit((string)$brandId)) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$userId = getUserId();

if (hasRole('Admin')) {

    $sql = "SELECT account_id, platform, account_name
            FROM social_accounts
            WHERE brand_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$brandId]);

} else {

    $sql = "SELECT sa.account_id, sa.platform, sa.account_name
            FROM social_accounts sa
            INNER JOIN brand_members bm
                ON sa.brand_id = bm.brand_id
            WHERE sa.brand_id = ?
            AND bm.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$brandId, $userId]);

}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));