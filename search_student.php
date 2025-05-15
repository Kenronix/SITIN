<?php
session_start();
include 'conn.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || strlen(trim($_GET['q'])) < 2) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q']);

// Search by id_number or name (firstname/lastname)
$stmt = $pdo->prepare("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, remaining_sessions FROM users WHERE role = 'student' AND (id_number LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?) ORDER BY name ASC LIMIT 10");
$like = "%$q%";
$stmt->execute([$like, $like, $like, $like]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$students = [];
foreach ($results as $row) {
    // Get latest purpose from reservations or sit_ins
    $purpose = '';
    $id_number = $row['id_number'];
    $purposeStmt = $pdo->prepare("SELECT purpose FROM (
        SELECT purpose, date, time_in FROM reservations WHERE id_number = ? AND purpose IS NOT NULL AND purpose != ''
        UNION ALL
        SELECT purpose, date, time_in FROM sit_ins WHERE id_number = ? AND purpose IS NOT NULL AND purpose != ''
        ORDER BY date DESC, time_in DESC LIMIT 1
    ) AS combined ORDER BY date DESC, time_in DESC LIMIT 1");
    $purposeStmt->execute([$id_number, $id_number]);
    $purposeRow = $purposeStmt->fetch(PDO::FETCH_ASSOC);
    if ($purposeRow && !empty($purposeRow['purpose'])) {
        $purpose = $purposeRow['purpose'];
    }
    $students[] = [
        'id' => $row['id_number'],
        'name' => $row['name'],
        'remaining_sessions' => $row['remaining_sessions'],
        'purpose' => $purpose
    ];
}

echo json_encode($students); 