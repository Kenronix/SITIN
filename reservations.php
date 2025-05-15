<?php
session_start();
include 'conn.php';

// Get all pending reservations with student details
$stmt = $pdo->query("SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) as student_name, 
                     u.course, u.year_level, u.remaining_sessions
                     FROM reservations r 
                     JOIN users u ON r.id_number = u.id_number 
                     WHERE r.status = 'pending'
                     ORDER BY r.date ASC, r.time_in ASC");
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Reservations | Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-laptop-code"></i> Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="reservations.php" class="active"><i class="fas fa-calendar-check"></i> Pending Reservation</a></li>
            <li><a href="current_sitin.php"><i class="fas fa-users"></i> Current Sit-In</a></li>
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
            <h1><i class="fas fa-calendar-check"></i> Pending Reservations</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php
                    if ($_GET['success'] == 'approve') {
                        echo "Reservation approved successfully!";
                    } elseif ($_GET['success'] == 'reject') {
                        echo "Reservation rejected successfully!";
                    }
                ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (!empty($reservations)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course & Year</th>
                            <th>Lab</th>
                            <th>PC</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Remaining Sessions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr class="fade-in">
                                <td><?= htmlspecialchars($reservation['id_number']) ?></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?= htmlspecialchars($reservation['student_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="course-badge">
                                        <?= htmlspecialchars($reservation['course']) ?> - 
                                        <?= htmlspecialchars($reservation['year_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="lab-badge">
                                        <i class="fas fa-laptop"></i>
                                        <?= htmlspecialchars($reservation['lab']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($reservation['pc_number'])): ?>
                                        <span class="lab-badge"><i class="fas fa-desktop"></i> <?= htmlspecialchars($reservation['pc_number']) ?></span>
                                    <?php else: ?>
                                        <span class="lab-badge text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="date-badge">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($reservation['date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="time-badge">
                                        <i class="far fa-clock"></i>
                                        <?= date('h:i A', strtotime($reservation['time_in'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($reservation['purpose']) ?></td>
                                <td>
                                    <span class="sessions-badge <?= $reservation['remaining_sessions'] <= 0 ? 'no-sessions' : '' ?>">
                                        <i class="fas fa-ticket-alt"></i>
                                        <?= $reservation['remaining_sessions'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($reservation['remaining_sessions'] > 0): ?>
                                            <button class="btn btn-success approve-btn" 
                                                    data-id="<?= $reservation['id'] ?>"
                                                    data-student="<?= htmlspecialchars($reservation['student_name']) ?>"
                                                    data-lab="<?= htmlspecialchars($reservation['lab']) ?>"
                                                    data-date="<?= htmlspecialchars($reservation['date']) ?>"
                                                    data-time="<?= htmlspecialchars($reservation['time_in']) ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger reject-btn" 
                                                data-id="<?= $reservation['id'] ?>"
                                                data-student="<?= htmlspecialchars($reservation['student_name']) ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check fa-3x"></i>
                    <h3>No Pending Reservations</h3>
                    <p>There are no pending lab reservations at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
            // Modal functionality
            const modal = document.getElementById('confirmationModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmAction');
            const cancelBtn = document.getElementById('cancelAction');
            const closeBtn = document.querySelector('.close');
            let currentAction = null;
            let currentId = null;

            function showModal(title, message, action, id) {
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                currentAction = action;
                currentId = id;
                
                // Set button style based on action
                confirmBtn.className = 'btn ' + (action === 'approve' ? 'btn-success' : 'btn-danger');
                confirmBtn.innerHTML = action === 'approve' ? 
                    '<i class="fas fa-check"></i> Approve' : 
                    '<i class="fas fa-times"></i> Reject';
                
                modal.style.display = 'block';
            }

            function closeModal() {
                modal.style.display = 'none';
                currentAction = null;
                currentId = null;
            }

            // Event listeners for approve buttons
            document.querySelectorAll('.approve-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const student = this.dataset.student;
                    const lab = this.dataset.lab;
                    const date = this.dataset.date;
                    const time = this.dataset.time;
                    
                    showModal(
                        'Approve Reservation',
                        `Are you sure you want to approve ${student}'s reservation for Lab ${lab} on ${date} at ${time}?`,
                        'approve',
                        this.dataset.id
                    );
                });
            });

            // Event listeners for reject buttons
            document.querySelectorAll('.reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const student = this.dataset.student;
                    
                    showModal(
                        'Reject Reservation',
                        `Are you sure you want to reject ${student}'s reservation?`,
                        'reject',
                        this.dataset.id
                    );
                });
            });

            // Modal close buttons
            closeBtn.onclick = closeModal;
            cancelBtn.onclick = closeModal;
            window.onclick = function(event) {
                if (event.target == modal) {
                    closeModal();
                }
            }

            // Handle confirmation
            confirmBtn.addEventListener('click', function() {
                if (!currentAction || !currentId) return;

                const url = currentAction === 'approve' ? 
                    'process_approve.php' : 
                    'process_reject.php';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'reservation_id=' + currentId
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
                    console.error('Error:', error);
                    showNotification('An error occurred while processing the request.', 'error');
                });

                closeModal();
            });

            // Notification function
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

            // Auto-dismiss success message
            const successMsg = document.querySelector('.alert-success');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    setTimeout(() => successMsg.remove(), 300);
                }, 5000);
            }
        });
    </script>

    <style>
        /* Additional styles specific to reservations */
        .student-info {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .course-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            background-color: var(--light-bg);
            color: var(--text-secondary);
        }

        .date-badge, .time-badge, .lab-badge, .sessions-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            background-color: var(--light-bg);
            color: var(--text-secondary);
        }

        .sessions-badge.no-sessions {
            background-color: #fee2e2;
            color: #991b1b;
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

        .alert {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: opacity 0.3s;
        }

        .alert i {
            font-size: 1.25rem;
        }

        @media (max-width: 1024px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.4); }
        .modal-content { background: #fff; margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 500px; position: relative; }
        .close { position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; }
    </style>
</body>
</html>