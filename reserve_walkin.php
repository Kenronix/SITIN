<?php
// reserve_walkin.php

require_once 'conn.php'; // include your DB connection

header('Content-Type: application/json');

// Debug line
error_log('POST data: ' . print_r($_POST, true));

// Get POST data
$student_id = $_POST['student_id'] ?? '';
$name = $_POST['name'] ?? '';
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$lab = $_POST['lab'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$pc_number = $_POST['pc_number'] ?? '';

// Debug line
error_log('Parsed data: ' . print_r([
    'student_id' => $student_id,
    'name' => $name,
    'date' => $date,
    'time' => $time,
    'lab' => $lab,
    'purpose' => $purpose,
    'pc_number' => $pc_number
], true));

// Basic validation
if (empty($student_id) || empty($name) || empty($date) || empty($time) || empty($lab) || empty($purpose) || empty($pc_number)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required, including PC number.']);
    exit;
}

// Check if student has remaining sessions (if you track this)
$stmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id_number = ?");
$stmt->execute([$student_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit;
}
if ($row['remaining_sessions'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'No remaining sessions for this student.']);
    exit;
}

// Check if the PC is available
$check_pc = $pdo->prepare("SELECT is_available FROM lab_pcs WHERE lab = ? AND pc_number = ?");
$check_pc->execute([$lab, $pc_number]);
$pc_row = $check_pc->fetch(PDO::FETCH_ASSOC);
if (!$pc_row || $pc_row['is_available'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Selected PC is not available.']);
    exit;
}

// Insert into sit_ins with PC number (no name column)
$stmt = $pdo->prepare("INSERT INTO sit_ins (id_number, lab, pc_number, purpose, time_in, date) VALUES (?, ?, ?, ?, ?, ?)");
if ($stmt->execute([$student_id, $lab, $pc_number, $purpose, "$date $time:00", $date])) {
    // Decrement remaining sessions
    $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ?")->execute([$student_id]);
    // Mark PC as unavailable
    $pdo->prepare("UPDATE lab_pcs SET is_available = 0 WHERE lab = ? AND pc_number =?")->execute([$lab, $pc_number]);
    // Create notification for the student
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at, is_read) VALUES (?, ?, 'walkin', NOW(), 0)");
    $notif_stmt->execute([
        $student_id,
        "Your walk-in sit-in for Lab $lab on $date at $time was successful."
    ]);
    echo json_encode(['success' => true, 'message' => 'Sit-in reserved successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>
