<?php
session_start();
include 'conn.php'; // Database connection


// Base query for students
$query = "SELECT u.*, 
          COUNT(DISTINCT s.id) as total_sitins,
          COUNT(DISTINCT r.id) as total_reservations
          FROM users u 
          LEFT JOIN sit_ins s ON u.id_number = s.id_number
          LEFT JOIN reservations r ON u.id_number = r.id_number
          WHERE u.role = 'student'";

$params = [];



$query .= " GROUP BY u.id_number ORDER BY u.lastname, u.firstname";

// Get students
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students | Admin Panel</title>
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
            <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
            <li><a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a></li>
            <li><a href="labsched.php"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-user-graduate"></i> Students</h1>

        </div>


        <!-- Students Table -->
        <div class="table-container">
                        
            <?php if (!empty($students)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Course & Year</th>
                            <th>Email</th>
                            <th>Remaining Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['id_number']) ?></td>
                                <td>
                                    <img src="<?= !empty($student['profile_picture']) ? 'uploads/' . htmlspecialchars($student['profile_picture']) : 'assets/default-avatar.png' ?>" alt="Profile" class="avatar-img" style="width:40px;height:40px;border-radius:50%;object-fit:cover;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                                </td>
                                <td><?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?></td>
                                <td><?= htmlspecialchars($student['course']) ?> - <?= htmlspecialchars($student['year_level']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td><?= isset($student['remaining_sessions']) ? (int)$student['remaining_sessions'] : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate fa-3x"></i>
                    <h3>No Students Found</h3>
                    <p>No student records available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background-color: var(--primary-color-light);
            color: var(--primary-color);
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #e6f4ea;
            color: #1e7e34;
        }

        .status-inactive {
            background-color: #fbe9e7;
            color: #d32f2f;
        }

        .student-info {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .course-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: var(--secondary-color-light);
            color: var(--secondary-color);
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.4); }
        .modal-content { background: #fff; margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 500px; position: relative; }
        .close { position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; }
        .search-results { margin-top: 1rem; }
        .student-list { list-style: none; padding: 0; }
        .student-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .student-item i { margin-right: 0.5rem; color: var(--primary-color); }
        .student-id { color: #888; font-size: 0.9em; margin-left: 0.5rem; }
        .reserveBtn { margin-left: 1rem; }
    </style>

    <script>
    </script>
</body>
</html>