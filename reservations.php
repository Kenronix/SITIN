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
                
                echo json_encode(['success' => true, 'message' => 'Reservation approved successfully.']);
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
    <style>
                /* Modal styles */
        .modal {
            display: block;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-button {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Status styles */
        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        /* Action button styles */
        .action-btn {
            margin: 2px;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 12px;
        }

        .view-reservation-btn {
            background-color: #17a2b8;
            color: white;
        }

        .approve-reservation-btn {
            background-color: #28a745;
            color: white;
        }

        .reject-reservation-btn {
            background-color: #dc3545;
            color: white;
        }
    </style>
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
        <script>
            document.addEventListener('DOMContentLoaded', function() {
            // Handle View Reservation Details
            const viewButtons = document.querySelectorAll('.view-reservation-btn');
            viewButtons.forEach(button => {
                // Only show See Details button for non-pending reservations
                if (button.closest('tr').querySelector('.status').textContent !== 'Pending') {
                    button.addEventListener('click', function() {
                        const reservationId = this.getAttribute('data-id');
                        viewReservationDetails(reservationId);
                    });
                } else {
                    button.style.display = 'none'; // Hide See Details for pending reservations
                }
            });

            // Handle Approve Reservation
            const approveButtons = document.querySelectorAll('.approve-reservation-btn');
            approveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to approve this reservation?')) {
                        approveReservation(reservationId);
                    }
                });
            });

            // Handle Reject Reservation
            const rejectButtons = document.querySelectorAll('.reject-reservation-btn');
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to disapprove this reservation?')) {
                        rejectReservation(reservationId);
                    }
                });
            });
        });

        // Function to view reservation details
        function viewReservationDetails(reservationId) {
            fetch(`reservations.php?action=view&reservation_id=${reservationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a modal to display the reservation details
                        const modal = document.createElement('div');
                        modal.className = 'modal';
                        
                        const modalContent = document.createElement('div');
                        modalContent.className = 'modal-content';
                        
                        const closeBtn = document.createElement('span');
                        closeBtn.className = 'close-button';
                        closeBtn.innerHTML = '&times;';
                        closeBtn.onclick = function() {
                            document.body.removeChild(modal);
                        };
                        
                        const reservation = data.reservation;
                        
                        modalContent.innerHTML = `
                            <h2>Reservation Details</h2>
                            <p><strong>Student ID:</strong> ${reservation.id_number}</p>
                            <p><strong>Student Name:</strong> ${reservation.student_name}</p>
                            <p><strong>Course:</strong> ${reservation.course}</p>
                            <p><strong>Year Level:</strong> ${reservation.year_level}</p>
                            <p><strong>Email:</strong> ${reservation.email}</p>
                            <p><strong>Lab:</strong> ${reservation.lab}</p>
                            <p><strong>Date:</strong> ${formatDate(reservation.date)}</p>
                            <p><strong>Time:</strong> ${formatTime(reservation.time_in)}</p>
                            <p><strong>Purpose:</strong> ${reservation.purpose}</p>
                            <p><strong>Status:</strong> ${reservation.status}</p>
                        `;
                        
                        modalContent.appendChild(closeBtn);
                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching reservation details.');
                });
        }

        // Function to approve reservation
        function approveReservation(reservationId) {
            fetch(`reservations.php?action=approve&reservation_id=${reservationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload the page to show updated status
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while approving the reservation.');
                });
        }

        // Function to reject reservation
        function rejectReservation(reservationId) {
            fetch(`reservations.php?action=reject&reservation_id=${reservationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload the page to show updated status
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while rejecting the reservation.');
                });
        }

        // Helper function to format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Helper function to format time
        function formatTime(timeString) {
            const time = new Date(`1970-01-01T${timeString}`);
            return time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
    </script>
</body>
</html>
