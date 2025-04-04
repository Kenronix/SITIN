<?php
session_start();
include 'conn.php'; // Database connection

// Set proper content type for JSON response
header('Content-Type: application/json');

// Check if the student ID is provided
if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Invalid request'));
    exit;
}

$student_id = $_POST['student_id'];

try {
    // First verify the student exists
    $check_stmt = $pdo->prepare("SELECT id_number FROM users WHERE id_number = ? AND role = 'student'");
    $check_stmt->execute([$student_id]);
    
    if ($check_stmt->rowCount() == 0) {
        echo json_encode(array('success' => false, 'message' => 'Student not found'));
        exit;
    }
    
    // Reset the remaining sessions to 30
    $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = 30 WHERE id_number = ?");
    $result = $stmt->execute([$student_id]);
    
    if ($result) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Database error occurred'));
    }
    
} catch (PDOException $e) {
    echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
}
?>