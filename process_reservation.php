<?php
// process_reservation.php
require_once 'conn.php';
session_start();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the form data
$id_number = $_POST['id_number'] ?? '';
$lab = $_POST['lab'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$time_in = $_POST['time_in'] ?? '';
$date = $_POST['date'] ?? '';
$status = $_POST['status'] ?? 'Pending';
$remaining_session = 30; // Default value
$pc_number = $_POST['pc_number'] ?? null;

// Validate the required fields
if (empty($id_number) || empty($lab) || empty($purpose) || empty($time_in) || empty($date) || empty($pc_number)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate date and time
$current_date = date('Y-m-d');
$selected_date = $date;
$selected_time = strtotime($time_in);
$start_time = strtotime('08:00:00');
$end_time = strtotime('17:00:00');

if ($selected_date < $current_date) {
    echo json_encode(['success' => false, 'message' => 'Cannot reserve for past dates']);
    exit;
}

if ($selected_time < $start_time || $selected_time > $end_time) {
    echo json_encode(['success' => false, 'message' => 'Reservation time must be between 8:00 AM and 5:00 PM']);
    exit;
}

// Check for existing reservations for the same lab, date, time and pc_number
$check_sql = "SELECT COUNT(*) FROM reservations WHERE lab = :lab AND date = :date AND time_in = :time_in AND pc_number = :pc_number";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([
    'lab' => $lab,
    'date' => $date,
    'time_in' => $time_in,
    'pc_number' => $pc_number
]);

if ($check_stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'This PC is already reserved for the selected time slot']);
    exit;
}

// Prepare SQL statement using PDO with status field and pc_number
$sql = "INSERT INTO reservations (id_number, lab, pc_number, purpose, status, time_in, date, remaining_session) 
        VALUES (:id_number, :lab, :pc_number, :purpose, :status, :time_in, :date, :remaining_session)";

$stmt = $pdo->prepare($sql);

// Bind parameters
$params = [
    'id_number' => $id_number,
    'lab' => $lab,
    'pc_number' => $pc_number,
    'purpose' => $purpose,
    'status' => $status,
    'time_in' => $time_in,
    'date' => $date,
    'remaining_session' => $remaining_session
];

// Execute the statement
if ($stmt->execute($params)) {
    echo json_encode(['success' => true, 'message' => 'Reservation created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create reservation']);
}