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
    $stmt = $pdo->prepare("SELECT r.*, u.id_number, u.remaining_sessions 
                          FROM reservations r 
                          JOIN users u ON r.id_number = u.id_number 
                          WHERE r.id = ? AND r.status = 'pending'");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        throw new Exception('Reservation not found or already processed');
    }
    
    if ($reservation['remaining_sessions'] <= 0) {
        throw new Exception('Student has no remaining sessions');
    }
    
    // Update reservation status
    $update_stmt = $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
    $update_stmt->execute([$reservation_id]);
    
    // Set the PC as unavailable in lab_pcs
    if (!empty($reservation['lab']) && !empty($reservation['pc_number'])) {
        $update_pc_stmt = $pdo->prepare("UPDATE lab_pcs SET is_available = 0 WHERE lab = ? AND pc_number = ?");
        $update_pc_stmt->execute([$reservation['lab'], $reservation['pc_number']]);
    }
    
    // Create sit-in record
    $datetime_in = $reservation['date'] . ' ' . $reservation['time_in'];
    $insert_sitin_stmt = $pdo->prepare("INSERT INTO sit_ins (id_number, lab, pc_number, purpose, time_in, date) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
    $insert_sitin_stmt->execute([
        $reservation['id_number'],
        $reservation['lab'],
        $reservation['pc_number'],
        $reservation['purpose'],
        $datetime_in,
        $reservation['date']
    ]);
    
    // Create notification for the student
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) 
                                VALUES (?, ?, 'reservation')");
    $notif_stmt->execute([
        $reservation['id_number'],
        "Your reservation for Lab {$reservation['lab']} on " . 
        date('F d, Y', strtotime($reservation['date'])) . 
        " at " . date('h:i A', strtotime($reservation['time_in'])) . 
        " has been approved."
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reservation approved successfully'
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