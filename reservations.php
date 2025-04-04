<?php
session_start();
include 'conn.php'; // Database connection

$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reservations_stmt = $pdo->query("SELECT r.id, r.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                r.lab, r.date, r.time_in, r.purpose, r.status 
                                FROM reservations r 
                                JOIN users u ON r.id_number = u.id_number 
                                ORDER BY r.date DESC, r.time_in DESC");
$reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['action']) && isset($_GET['reservation_id'])) {
    $action = $_GET['action'];
    $reservation_id = $_GET['reservation_id'];
    
    // Validate reservation ID
    if (!is_numeric($reservation_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
        exit;
    }
    
    switch ($action) {
        case 'approve':
            // Start a transaction
            $pdo->beginTransaction();
            try {
                // Get reservation details
                $stmt = $pdo->prepare("SELECT id_number, lab, time_in, date, purpose FROM reservations WHERE id = ?");
                $stmt->execute([$reservation_id]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$reservation) {
                    throw new Exception("Reservation not found");
                }
                
                // Update the reservation status to Approved
                $stmt = $pdo->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
                $stmt->execute([$reservation_id]);
                
                // Reduce student's remaining sessions by 1
                $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0");
                $stmt->execute([$reservation['id_number']]);
                
                // Create a sit-in record for the approved reservation
                $stmt = $pdo->prepare("INSERT INTO sit_ins (id_number, lab, purpose, time_in, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $reservation['id_number'], 
                    $reservation['lab'], 
                    $reservation['purpose'],
                    $reservation['time_in'],
                    $reservation['date']
                ]);
                
                // Commit the transaction
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Reservation approved successfully. The student has been added to sit-ins and remaining sessions reduced.']);
            } catch (Exception $e) {
                // Roll back the transaction in case of error
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to approve reservation: ' . $e->getMessage()]);
            }
            break;
            
        case 'reject':
            // Update the reservation status to Rejected
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'Rejected' WHERE id = ?");
            if ($stmt->execute([$reservation_id])) {
                echo json_encode(['success' => true, 'message' => 'Reservation rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject reservation']);
            }
            break;
            
        case 'view':
            // Get reservation details
            $stmt = $pdo->prepare("SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                 u.course, u.year_level, u.email
                                 FROM reservations r
                                 JOIN users u ON r.id_number = u.id_number
                                 WHERE r.id = ?");
            $stmt->execute([$reservation_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                echo json_encode(['success' => true, 'reservation' => $reservation]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Reservation not found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admins.css">

</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="reservations.php">Pending Reservation</a></li>
            <li><a href="current_sitin.php">Current Sit-In</a></li>
            <li><a href="sitin_reports.php">Sit-In Reports</a></li>
            <li><a href="students.php">Students</a></li>
            <li><a href="announcement.php">Announcement</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="survey.php">Satisfaction Survey</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
            <h3>Reservation Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reservations)): ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php 
                            $statusClass = '';
                            switch($reservation['status']) {
                                case 'Pending':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'Approved':
                                    $statusClass = 'status-approved';
                                    break;
                                case 'Rejected':
                                    $statusClass = 'status-rejected';
                                    break;
                                case 'Completed':
                                    $statusClass = 'status-completed';
                                    break;
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($reservation['id_number']) ?></td>
                                <td><?= htmlspecialchars($reservation['student_name']) ?></td>
                                <td><?= htmlspecialchars($reservation['lab']) ?></td>
                                <td><?= htmlspecialchars(date('F d, Y', strtotime($reservation['date']))) ?></td>
                                <td><?= htmlspecialchars(date('h:i A', strtotime($reservation['time_in']))) ?></td>
                                <td><?= htmlspecialchars($reservation['purpose']) ?></td>
                                <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($reservation['status']) ?></span></td>
                                <td>
                                    <button class="action-btn view-reservation-btn" data-id="<?= $reservation['id'] ?>"><i class="fas fa-eye"></i>See Details</button>
                                    <?php if ($reservation['status'] == 'Pending'): ?>
                                        <button class="action-btn approve-reservation-btn" data-id="<?= $reservation['id'] ?>">Approve</button>
                                        <button class="action-btn reject-reservation-btn" data-id="<?= $reservation['id'] ?>">Disapprove</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data">No reservations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
    </div>
    
</body>
</html>
