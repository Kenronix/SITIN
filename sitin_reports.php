<?php
session_start();
include 'conn.php'; // Database connection

$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
        
        <h3>Sit-In Reports</h3>
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Lab</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sitin_reports)): ?>
                    <?php foreach ($sitin_reports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['id_number']) ?></td>
                            <td><?= htmlspecialchars($report['student_name']) ?></td>
                            <td><?= htmlspecialchars($report['lab']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($report['time_in']))) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($report['time_out']))) ?></td>
                            <td><?= htmlspecialchars($report['purpose']) ?></td>
                            <td><span class="status status-completed">Completed</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No sit-in reports available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    
    </div>
</body>
</html>
