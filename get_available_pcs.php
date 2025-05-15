<?php
require_once 'conn.php';
header('Content-Type: application/json');

$lab = $_GET['lab'] ?? '';
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (!$lab) {
    echo json_encode([]);
    exit;
}

// Get all available PCs for the lab
$stmt = $pdo->prepare("SELECT pc_number FROM lab_pcs WHERE lab = ? AND is_available = 1");
$stmt->execute([$lab]);
$all_pcs = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!$date || !$time) {
    // If date or time is missing, return all available PCs for the lab
    sort($all_pcs, SORT_NUMERIC);
    echo json_encode(array_values($all_pcs));
    exit;
}

// Get reserved PCs for this lab/date/time
$reserved_stmt = $pdo->prepare("SELECT pc_number FROM reservations WHERE lab = ? AND date = ? AND time_in = ?");
$reserved_stmt->execute([$lab, $date, $time]);
$reserved_pcs = $reserved_stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter out reserved PCs
$available_pcs = array_diff($all_pcs, $reserved_pcs);

// Return as sorted array
sort($available_pcs, SORT_NUMERIC);
echo json_encode(array_values($available_pcs)); 