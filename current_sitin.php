<?php
session_start();
include 'conn.php'; // Database connection

$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_sitin_stmt = $pdo->query("SELECT s.id, s.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                s.lab, s.time_in, s.purpose 
                                FROM sit_ins s 
                                JOIN users u ON s.id_number = u.id_number 
                                WHERE s.time_out IS NULL 
                                ORDER BY s.time_in DESC");
$current_sitins = $current_sitin_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Sit-In</title>
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
    <div class="content" id="current-sitin">

        <h3>Current Sit-In Records</h3>
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Lab</th>
                    <th>Time In</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($current_sitins)): ?>
                    <?php foreach ($current_sitins as $sitin): ?>
                        <tr>
                            <td><?= htmlspecialchars($sitin['id_number']) ?></td>
                            <td><?= htmlspecialchars($sitin['student_name']) ?></td>
                            <td><?= htmlspecialchars($sitin['lab']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($sitin['time_in']))) ?></td>
                            <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                            <td><span class="status status-active">Active</span></td>
                            <td>
                                <button class="action-btn reject-btn" data-id="<?= $sitin['id'] ?>"><i class="fas fa-sign-out-alt"></i> Logout</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No active sit-ins at the moment.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all logout buttons
            const logoutButtons = document.querySelectorAll('.reject-btn');
            
            logoutButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const sitInId = this.getAttribute('data-id');
                    
                    if (confirm('Are you sure you want to log this student out? This will reduce their remaining session count by 1.')) {
                        // Send AJAX request to process the logout
                        fetch('process_logout.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'sit_in_id=' + sitInId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Student logged out successfully. Remaining sessions reduced by 1.');
                                // Reload the page to update the table
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while processing the logout.');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
