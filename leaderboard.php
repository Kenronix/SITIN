<?php
session_start();
include 'conn.php'; // Database connection

// Fetch all students and their reward points
$stmt = $pdo->query("SELECT u.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                      u.course, u.year_level, u.email, u.remaining_sessions,
                      COALESCE(l.points, 0) AS reward_points
                      FROM users u
                      LEFT JOIN leaderboard l ON u.id_number = l.student_id
                      WHERE u.role = 'student'
                      ORDER BY COALESCE(l.points, 0) DESC, student_name ASC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top 3 students
$top_students = array_slice($students, 0, 3);

// Get total rewards given
$rewards_stmt = $pdo->query("SELECT COUNT(*) AS total_rewards FROM reward_logs");
$total_rewards = $rewards_stmt->fetch(PDO::FETCH_ASSOC)['total_rewards'];

// Get number of students with rewards
$rewarded_students_stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) AS rewarded_students FROM leaderboard WHERE points > 0");
$rewarded_students = $rewarded_students_stmt->fetch(PDO::FETCH_ASSOC)['rewarded_students'];

// Get total bonus sessions granted
$bonus_sessions_stmt = $pdo->query("SELECT SUM(points / 3) AS bonus_sessions FROM leaderboard");
$bonus_sessions = floor($bonus_sessions_stmt->fetch(PDO::FETCH_ASSOC)['bonus_sessions']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Leaderboard | Admin Panel</title>
    <link rel="stylesheet" href="admins.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <li><a href="labsched.php">Lab Schedule</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php" class="active">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <h1><i class="fas fa-trophy"></i> Student Leaderboard</h1>
        
        <div class="reward-description">
            <h3><i class="fas fa-star"></i> Reward System</h3>
            <p>Students earn reward points for exemplary behavior or contributions during their lab sessions. Every 3 points accumulated grants an additional session to the student.</p>
            <p>Points are awarded by lab admins through the Current Sit-In panel using the REWARD button. When rewarded, the student is automatically logged out and uses one session.</p>
        </div>
        
        <!-- Stats Overview -->
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">

        </div>
        
        <!-- Top 3 Students -->
        <?php if (count($top_students) > 0): ?>
        <h3><i class="fas fa-crown"></i> Top Performing Students</h3>
        <div style="display: flex; justify-content: center; margin: 20px 0; flex-wrap: wrap;">
            <?php foreach ($top_students as $index => $student): ?>
                <?php 
                    $rank = $index + 1;
                    $medal_color = '';
                    $bg_color = '';
                    $icon = '';
                    
                    if ($rank == 1) {
                        $medal_color = '#FFD700'; // gold
                        $bg_color = '#fcf8e3';
                        $icon = 'crown';
                    } elseif ($rank == 2) {
                        $medal_color = '#C0C0C0'; // silver
                        $bg_color = '#f7f7f7';
                        $icon = 'medal';
                    } elseif ($rank == 3) {
                        $medal_color = '#CD7F32'; // bronze
                        $bg_color = '#f8f5f0';
                        $icon = 'award';
                    }
                ?>
                <div style="background-color: <?= $bg_color ?>; padding: 20px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); flex: 1; margin: 0 10px; max-width: 300px; position: relative; text-align: center; border: 1px solid #dee2e6;">
                    <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background-color: <?= $medal_color ?>; color: <?= $rank == 1 ? '#000' : '#fff' ?>; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <?= $rank ?>
                    </div>
                    <i class="fas fa-<?= $icon ?>" style="color: <?= $medal_color ?>; font-size: 28px; margin-bottom: 10px;"></i>
                    <h4 style="margin: 10px 0; font-size: 18px;"><?= htmlspecialchars($student['student_name']) ?></h4>
                    <p style="color: #6c757d; margin: 5px 0;"><?= htmlspecialchars($student['course']) ?> (<?= htmlspecialchars($student['year_level']) ?>)</p>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <p style="margin: 0;"><strong style="color: #28a745;"><?= htmlspecialchars($student['reward_points']) ?></strong> Points</p>
                        <p style="margin: 5px 0;"><strong style="color: #007bff;"><?= htmlspecialchars($student['remaining_sessions']) ?></strong> Sessions</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Complete Leaderboard -->
        <h3><i class="fas fa-list-ol"></i> Complete Leaderboard Ranking</h3>
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th width="60px">Rank</th>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Course</th>
                    <th>Year Level</th>
                    <th width="120px">Reward Points</th>
                    <th width="120px">Remaining Sessions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php $rank = 1; ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div class="rank <?= ($rank <= 3) ? 'rank-'.$rank : '' ?>">
                                    <?= $rank ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($student['id_number']) ?></td>
                            <td><?= htmlspecialchars($student['student_name']) ?></td>
                            <td><?= htmlspecialchars($student['course']) ?></td>
                            <td><?= htmlspecialchars($student['year_level']) ?></td>
                            <td class="reward-points"><?= htmlspecialchars($student['reward_points']) ?></td>
                            <td class="remaining-sessions"><?= htmlspecialchars($student['remaining_sessions']) ?></td>
                        </tr>
                        <?php $rank++; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No students found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Optional: Add animations or interactivity if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Add search functionality or sorting options
        });
    </script>
</body>
</html>