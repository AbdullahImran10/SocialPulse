<?php

function logActivity($pdo, $userId, $action, $details = null)
{
    try {

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
            (user_id, action, details)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $details
        ]);

        return true;

    } catch (PDOException $e) {

        error_log("Audit Log Error: " . $e->getMessage());

        return false;
    }
}