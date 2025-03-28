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
        <h1>Student Feedback</h1>
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Lab</th>
                    <th>Date</th>
                    <th>Comments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Get feedback from database
                $feedback_stmt = $pdo->query("SELECT f.id, f.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                            f.lab, f.date, f.comments
                                            FROM feedback f
                                            JOIN users u ON f.id_number = u.id_number
                                            ORDER BY f.date DESC");
                $feedbacks = $feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($feedbacks)): 
                    foreach ($feedbacks as $feedback): 
                ?>
                    <tr>
                        <td><?= htmlspecialchars($feedback['id_number']) ?></td>
                        <td><?= htmlspecialchars($feedback['student_name']) ?></td>
                        <td><?= htmlspecialchars($feedback['lab']) ?></td>
                        <td><?= htmlspecialchars(date('F d, Y', strtotime($feedback['date']))) ?></td>
                        <td><?= htmlspecialchars($feedback['comments']) ?></td>
                        <td>
                            <button class="action-btn view-feedback-btn" data-id="<?= $feedback['id'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr>
                        <td colspan="7" class="no-data">No feedback records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        
            // Handle view feedback button clicks
            document.querySelectorAll('.view-feedback-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const feedbackId = this.getAttribute('data-id');
                    
                    // AJAX request to get feedback details
                    fetch(`admin.php?action=view_feedback&feedback_id=${feedbackId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const feedback = data.feedback;
                                
                                // Populate the modal with feedback details
                                document.getElementById('view-feedback-student').textContent = feedback.student_name;
                                document.getElementById('view-feedback-lab').textContent = feedback.lab;
                                document.getElementById('view-feedback-date').textContent = new Date(feedback.date).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                                
                                document.getElementById('view-feedback-comments').textContent = 
                                    feedback.comments ? feedback.comments : 'No comments provided';
                                
                                // Show the modal
                                new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while fetching feedback details');
                        });
                });
            });

    </script>
</body>
</html>
