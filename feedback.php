<?php
session_start();
include 'conn.php'; // Database connection

// Simple query to get all feedback
$query = "SELECT f.*, CONCAT(u.firstname, ' ', u.lastname) as student_name, 
          u.course, u.year_level
          FROM feedback f 
          JOIN users u ON f.id_number = u.id_number 
          ORDER BY f.date DESC";

// Get feedback
$stmt = $pdo->query($query);
$feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-laptop-code"></i> Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> Pending Reservation</a></li>
            <li><a href="current_sitin.php"><i class="fas fa-users"></i> Current Sit-In</a></li>
            <li><a href="sitin_reports.php"><i class="fas fa-file-alt"></i> Sit-In Reports</a></li>
            <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
            <li><a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a></li>
            <li><a href="feedback.php" class="active"><i class="fas fa-comment-alt"></i> Feedback</a></li>
            <li><a href="labsched.php"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-comment-alt"></i> Student Feedback</h1>
        </div>

        <!-- Feedback Table -->
        <div class="table-container">

            
            <?php if (!empty($feedback_list)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Course & Year</th>
                            <th>Lab</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback_list as $feedback): ?>
                            <tr>
                                <td>
                                    <span class="date-badge">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($feedback['date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="student-name"><?= htmlspecialchars($feedback['student_name']) ?></span>
                                </td>
                                <td>
                                    <span class="student-id"><?= htmlspecialchars($feedback['id_number']) ?></span>
                                </td>
                                <td>
                                    <span class="course-badge">
                                        <?= htmlspecialchars($feedback['course']) ?> - 
                                        <?= htmlspecialchars($feedback['year_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="lab-badge">
                                        <?= htmlspecialchars($feedback['lab']) ?>
                                    </span>
                                </td>
                                <td class="feedback-message">
                                    <?= htmlspecialchars($feedback['comments']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-alt fa-3x"></i>
                    <h3>No Feedback Found</h3>
                    <p>There are no feedback records available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.4); }
    .modal-content { background: #fff; margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 500px; position: relative; }
    .close { position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; }
    .search-results { margin-top: 1rem; }
    .student-list { list-style: none; padding: 0; }
    .student-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
    .student-item i { margin-right: 0.5rem; color: var(--primary-color); }
    .student-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .student-id { color: var(--text-secondary); font-size: 0.9em; }
    .student-name { font-weight: 500; }
    .student-sessions { color: var(--text-secondary); font-size: 0.85em; }
    .reserveBtn { margin-left: 1rem; }
    </style>
</body>
</html>