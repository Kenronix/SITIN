<?php
session_start();
include 'conn.php'; // Database connection

$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_sitin_stmt = $pdo->query("SELECT s.id, s.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                s.lab, s.pc_number, s.time_in, s.purpose,s.date
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
    <title>Current Sit-In | Admin Panel</title>
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
            <li><a href="current_sitin.php" class="active"><i class="fas fa-users"></i> Current Sit-In</a></li>
            <li><a href="sitin_reports.php"><i class="fas fa-file-alt"></i> Sit-In Reports</a></li>
            <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
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
            <h1><i class="fas fa-users"></i> Current Sit-In Records</h1>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Lab</th>
                        <th>PC</th>
                        <th>Time In</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($current_sitins)): ?>
                        <?php foreach ($current_sitins as $sitin): ?>
                            <tr class="fade-in">
                                <td><?= htmlspecialchars($sitin['id_number']) ?></td>   
                                <td><?= htmlspecialchars($sitin['student_name']) ?></td>
                                <td>
                                    <span class="lab-badge">
                                        <i class="fas fa-laptop"></i>
                                        <?= htmlspecialchars($sitin['lab']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($sitin['pc_number'])): ?>
                                        <span class="lab-badge"><i class="fas fa-desktop"></i> <?= htmlspecialchars($sitin['pc_number']) ?></span>
                                    <?php else: ?>
                                        <span class="lab-badge text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="time-badge">
                                        <i class="far fa-clock"></i>
                                        <?= htmlspecialchars(date('h:i A', strtotime($sitin['time_in']))) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                                <td>   
                                    <?php 
                                        if (!empty($sitin['date']) && $sitin['date'] !== '0000-00-00' && strtotime($sitin['date']) !== false) {
                                            echo htmlspecialchars(date('F d, Y', strtotime($sitin['date'])));
                                        } else {
                                            echo htmlspecialchars(date('F d, Y', strtotime($sitin['time_in'])));
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="status status-active">
                                        <i class="fas fa-circle"></i> Active
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-success reward-btn" data-id="<?= $sitin['id'] ?>" data-student-id="<?= $sitin['id_number'] ?>">
                                            <i class="fas fa-star"></i> Reward
                                        </button>
                                        <button class="btn btn-danger reject-btn" data-id="<?= $sitin['id'] ?>">
                                            <i class="fas fa-sign-out-alt"></i> Logout
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data">
                                <div class="empty-state">
                                    <i class="fas fa-users fa-3x mb-3"></i>
                                    <h3>No Active Sit-Ins</h3>
                                    <p>There are no students currently using the labs.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        /* Additional styles specific to current sit-in */
        .lab-badge, .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            background-color: var(--light-bg);
            color: var(--text-secondary);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .notification {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 1000;
            transition: opacity 0.3s;
        }

        .notification.success {
            border-left: 4px solid var(--success-color);
        }

        .notification.error {
            border-left: 4px solid var(--danger-color);
        }

        .notification i {
            font-size: 1.25rem;
        }

        .notification.success i {
            color: var(--success-color);
        }

        .notification.error i {
            color: var(--danger-color);
        }

        .status i {
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        @media (max-width: 1024px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
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

        .student-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .student-id {
            color: var(--text-secondary);
            font-size: 0.9em;
        }

        .student-name {
            font-weight: 500;
        }

        .student-sessions {
            color: var(--text-secondary);
            font-size: 0.85em;
        }
    </style>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Confirm Action</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p id="modalMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelAction">Cancel</button>
                <button class="btn" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const confirmBtn = document.getElementById('confirmAction');
        const cancelBtn = document.getElementById('cancelAction');
        const closeBtn = document.querySelector('.close');
        let currentAction = null;
        let currentId = null;
        let currentStudentId = null;

        function showModal(title, message, action, id, studentId) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            currentAction = action;
            currentId = id;
            currentStudentId = studentId;
            confirmBtn.className = 'btn ' + (action === 'reward' ? 'btn-success' : 'btn-danger');
            confirmBtn.innerHTML = action === 'reward' ? '<i class="fas fa-star"></i> Reward' : '<i class="fas fa-sign-out-alt"></i> Logout';
            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
            currentAction = null;
            currentId = null;
            currentStudentId = null;
        }

        document.querySelectorAll('.reward-btn').forEach(button => {
            button.addEventListener('click', function() {
                const sitinId = this.getAttribute('data-id');
                const studentId = this.getAttribute('data-student-id');
                showModal('Reward Student', 'Are you sure you want to reward this student? This will also log them out.', 'reward', sitinId, studentId);
            });
        });
        document.querySelectorAll('.reject-btn').forEach(button => {
            button.addEventListener('click', function() {
                const sitinId = this.getAttribute('data-id');
                showModal('Logout Student', 'Are you sure you want to log out this student?', 'logout', sitinId, null);
            });
        });
        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;
        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }
        confirmBtn.addEventListener('click', function() {
            if (!currentAction || !currentId) return;
            let url = '';
            let body = '';
            if (currentAction === 'reward') {
                url = 'process_reward.php';
                body = 'sit_in_id=' + encodeURIComponent(currentId) + '&student_id=' + encodeURIComponent(currentStudentId);
            } else if (currentAction === 'logout') {
                url = 'process_logout.php';
                body = 'sit_in_id=' + encodeURIComponent(currentId);
            }
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred while processing the request.', 'error');
            });
            closeModal();
        });
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type} fade-in`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    });
    </script>
</body>
</html>