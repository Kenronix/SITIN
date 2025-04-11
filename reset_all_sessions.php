<?php
session_start();
include 'conn.php'; // Database connection

header('Content-Type: application/json');

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Update all student accounts to have 30 remaining sessions
    $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = 30 WHERE role = 'student'");
    $stmt->execute();
    
    // Get count of updated rows
    $updatedCount = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'message' => "Successfully reset sessions for $updatedCount students"]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>