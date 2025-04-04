<?php
// create_sitin.php
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

// Validate the required fields
if (empty($id_number) || empty($lab) || empty($purpose) || empty($time_in) || empty($date)) {
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
    echo json_encode(['success' => false, 'message' => 'Cannot create sit-in for past dates']);
    exit;
}

if ($selected_time < $start_time || $selected_time > $end_time) {
    echo json_encode(['success' => false, 'message' => 'Sit-in time must be between 8:00 AM and 5:00 PM']);
    exit;
}

// Check for existing sit-ins for the same lab, date, and time
$check_sql = "SELECT COUNT(*) FROM sit_ins WHERE lab = :lab AND DATE(time_in) = :date AND time_out IS NULL";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([
    'lab' => $lab,
    'date' => $selected_date
]);

if ($check_stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'This lab is already occupied at the selected time']);
    exit;
}



// Prepare SQL statement to create sit-in
$sql = "INSERT INTO sit_ins (id_number, lab, purpose, time_in, date) 
        VALUES (:id_number, :lab, :purpose, :time_in, :date)";

$stmt = $pdo->prepare($sql);

// Bind parameters
$params = [
    'id_number' => $id_number,
    'lab' => $lab,
    'purpose' => $purpose,
    'time_in' => "$selected_date $time_in:00",
    'date' => $selected_date
];

// Execute the statement
if ($stmt->execute($params)) {
    echo json_encode(['success' => true, 'message' => 'Sit-in created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create sit-in']);
}
?>