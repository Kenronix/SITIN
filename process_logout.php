<?php
session_start();
include 'conn.php'; // Database connection

if (!isset($_POST['sit_in_id']) || empty($_POST['sit_in_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the sit-in record
    $stmt = $pdo->prepare("SELECT id_number FROM sit_ins WHERE id = ? AND time_out IS NULL");
    $stmt->execute([$_POST['sit_in_id']]);
    $sitin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sitin) {
        echo json_encode(['success' => false, 'message' => 'Sit-in record not found or already logged out']);
        exit;
    }
    
    // Update the sit-in record with logout time (no status field)
    $update_stmt = $pdo->prepare("UPDATE sit_ins SET time_out = NOW() WHERE id = ?");
    $update_stmt->execute([$_POST['sit_in_id']]);
    
    // Reduce the remaining sessions by 1
    $reduce_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0");
    $reduce_stmt->execute([$sitin['id_number']]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>