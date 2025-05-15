<?php
session_start();
include 'conn.php'; // Database connection

// Get filter parameters
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$year_filter = $_GET['year'] ?? '';
$period_filter = $_GET['period'] ?? 'all'; // all, monthly, weekly

// Get unique courses and years for filters
$courses_query = "SELECT DISTINCT course FROM users WHERE role = 'student' ORDER BY course";
$years_query = "SELECT DISTINCT year_level FROM users WHERE role = 'student' ORDER BY year_level";
$courses = $pdo->query($courses_query)->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query($years_query)->fetchAll(PDO::FETCH_COLUMN);

// Fetch leaderboard data: join leaderboard and users, order by points DESC
$query = "SELECT 
            l.student_id as id_number,
            CONCAT(u.firstname, ' ', u.lastname) as student_name,
            u.course,
            u.year_level,
            l.points
          FROM leaderboard l
          JOIN users u ON l.student_id = u.id_number
          WHERE u.role = 'student'
          ORDER BY l.points DESC, u.lastname, u.firstname
          LIMIT 50";

$stmt = $pdo->query($query);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performers
$top_performers = array_slice($leaderboard, 0, 3);

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
    <title>Leaderboard | Admin Panel</title>
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
            <li><a href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a></li>
            <li><a href="labsched.php"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php" class="active"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-trophy"></i> Leaderboard</h1>
            <div class="header-actions">
                <div class="period-selector">
                    <select id="periodFilter" class="form-control" onchange="updatePeriod(this.value)">
                        <option value="all" <?= $period_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="monthly" <?= $period_filter === 'monthly' ? 'selected' : '' ?>>This Month</option>
                        <option value="weekly" <?= $period_filter === 'weekly' ? 'selected' : '' ?>>This Week</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="top-performers-modern">
            <?php foreach ($top_performers as $index => $student): ?>
                <div class="top-performer-modern-card rank-<?= $index + 1 ?>">
                    <div class="modern-rank-badge">
                        <i class="fas fa-medal"></i>
                        <span><?= $index + 1 ?></span>
                    </div>
                    <div class="modern-student-info">
                        <div class="modern-student-name"><?= htmlspecialchars($student['student_name']) ?></div>
                        <div class="modern-student-meta">
                            <?= htmlspecialchars($student['id_number']) ?>
                            <?= htmlspecialchars($student['course']) ?> - <?= htmlspecialchars($student['year_level']) ?>
                        </div>
                    </div>
                    <div class="modern-points-badge">
                        <i class="fas fa-star"></i> <?= (int)$student['points'] ?> Points
                    </div>
                </div>
            <?php endforeach; ?>
        </div>


        <!-- Leaderboard Table -->
        <div class="table-container">
            <div class="table-header">
                <h2>Student Rankings</h2>
            </div>
            
            <?php if (!empty($leaderboard)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Course & Year</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $i => $student): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($student['student_name']) ?></td>
                                <td><?= htmlspecialchars($student['id_number']) ?></td>
                                <td><?= htmlspecialchars($student['course']) ?> - <?= htmlspecialchars($student['year_level']) ?></td>
                                <td><?= (int)$student['points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-trophy fa-3x"></i>
                    <h3>No Rankings Found</h3>
                    <p>Try adjusting your filters to see more results.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .top-performers-modern {
            display: flex;
            gap: 2rem;
            margin-bottom: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .top-performer-modern-card {
            flex: 1 1 300px;
            max-width: 400px;
            min-width: 260px;
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem 1.5rem 1.5rem 1.5rem;
            position: relative;
            box-shadow: 0 6px 24px rgba(0,0,0,0.10);
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .top-performer-modern-card.rank-1 {
            background: linear-gradient(90deg, #ffe259 0%, #ffa751 100%);
        }
        .top-performer-modern-card.rank-2 {
            background: linear-gradient(90deg, #e0e0e0 0%, #bdbdbd 100%);
        }
        .top-performer-modern-card.rank-3 {
            background: linear-gradient(90deg, #  0%, #ffd200 100%);
        }
        .top-performer-modern-card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 12px 32px rgba(0,0,0,0.13);
        }
        .modern-rank-badge {
            position: absolute;
            top: -1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border-radius: 50%;
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            z-index: 2;
        }
        .modern-rank-badge i {
            color: #ffd700;
            margin-right: 0.5rem;
        }
        .modern-student-info {
            text-align: center;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
        }
        .modern-student-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 0.25rem;
        }
        .modern-student-meta {
            font-size: 1rem;
            color: #444;
            font-weight: 500;
        }
        .modern-points-badge {
            margin-top: 1.2rem;
            background: #fffbe6;
            color: #bfa100;
            border-radius: 1rem;
            padding: 0.5rem 1.25rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        }
        .modern-points-badge i {
            color: #ffd700;
        }
        @media (max-width: 1024px) {
            .top-performers-modern {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>

    <script>
        function updatePeriod(period) {
            const form = document.getElementById('filterForm');
            form.querySelector('input[name="period"]').value = period;
            form.submit();
        }
    </script>
</body>
</html>