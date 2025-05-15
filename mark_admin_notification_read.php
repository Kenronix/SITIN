<?php
session_start();
require_once 'conn.php';

header('Content-Type: application/json');

$admin_id = '000'; // Use the same admin id as in your notification logic

try {
    if (isset($_POST['mark_all'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'student_reservation' AND is_read = 0");
        $stmt->execute([$admin_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($_POST['notification_id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $admin_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 