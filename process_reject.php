<?php
session_start();
include 'conn.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the reservation ID
$reservation_id = $_POST['reservation_id'] ?? '';

if (empty($reservation_id)) {
    echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get reservation details
    $stmt = $pdo->prepare("SELECT r.*, u.id_number 
                          FROM reservations r 
                          JOIN users u ON r.id_number = u.id_number 
                          WHERE r.id = ? AND r.status = 'pending'");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        throw new Exception('Reservation not found or already processed');
    }
    
    // Update reservation status
    $update_stmt = $pdo->prepare("UPDATE reservations SET status = 'rejected' WHERE id = ?");
    $update_stmt->execute([$reservation_id]);
    
    // Create notification for the student
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) 
                                VALUES (?, ?, 'reservation')");
    $notif_stmt->execute([
        $reservation['id_number'],
        "Your reservation for Lab {$reservation['lab']} on " . 
        date('F d, Y', strtotime($reservation['date'])) . 
        " at " . date('h:i A', strtotime($reservation['time_in'])) . 
        " has been rejected."
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reservation rejected successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?> 