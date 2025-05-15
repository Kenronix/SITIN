<?php
session_start();
include 'conn.php'; // Database connection

// Get total students count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get active sit-ins count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sit_ins WHERE time_out IS NULL");
$activeSitIns = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get pending reservations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'");
$pendingReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total sit-ins today
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sit_ins WHERE DATE(time_in) = CURDATE()");
$todaySitIns = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent sit-ins
$stmt = $pdo->query("SELECT s.*, CONCAT(u.firstname, ' ', u.lastname) as student_name 
                     FROM sit_ins s 
                     JOIN users u ON s.id_number = u.id_number 
                     ORDER BY s.time_in DESC LIMIT 5");
$recentSitIns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent reservations
$stmt = $pdo->query("SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) as student_name 
                     FROM reservations r 
                     JOIN users u ON r.id_number = u.id_number 
                     ORDER BY r.date DESC, r.time_in DESC 
                     LIMIT 5");
$recentReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab usage statistics for the last 7 days
$stmt = $pdo->query("SELECT DATE(time_in) as date, COUNT(*) as count 
                     FROM sit_ins 
                     WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                     GROUP BY DATE(time_in) 
                     ORDER BY date");
$labUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab distribution
$stmt = $pdo->query("SELECT lab, COUNT(*) as count 
                     FROM sit_ins 
                     WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                     GROUP BY lab");
$labDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch admin notifications
$admin_id = '000'; // or your actual admin id_number
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'student_reservation' ORDER BY created_at DESC LIMIT 10");
$notifStmt->execute([$admin_id]);
$admin_notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND type = 'student_reservation'");
$unreadStmt->execute([$admin_id]);
$admin_unread_count = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-laptop-code"></i> Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> Pending Reservation</a></li>
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
            <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
            <!-- Admin Notification Bell -->
            <div class="notification-container" style="position: relative;">
                <div class="notification-bell" onclick="toggleAdminNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($admin_unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $admin_unread_count; ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="adminNotificationDropdown" style="display:none; position:absolute; right:0; top:120%; width:350px; background:white; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); z-index:1000;">
                    <div class="notification-header" style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:16px; color:#333;">Notifications</h3>
                        <?php if (count($admin_notifications) > 0): ?>
                            <button onclick="markAllAdminRead(event)" class="mark-all-read" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:14px; padding:5px 10px;">Mark all as read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list" style="max-height:400px; overflow-y:auto;">
                        <?php if (count($admin_notifications) > 0): ?>
                            <?php foreach ($admin_notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>" onclick="markAdminAsRead(<?php echo $notification['id']; ?>)">
                                    <div class="notification-icon" style="color:#2563eb; font-size:18px; padding-top:2px;"><i class="fas fa-calendar-check"></i></div>
                                    <div class="notification-content" style="flex:1;">
                                        <p style="margin:0 0 5px 0; color:#333; font-size:14px;"> <?php echo htmlspecialchars($notification['message']); ?> </p>
                                        <small style="color:#666; font-size:12px;"> <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?> </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-notifications" style="padding:30px; text-align:center; color:#666;">
                                <i class="fas fa-bell-slash" style="font-size:24px; margin-bottom:10px;"></i>
                                <p style="margin:0;">No notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="header-actions" style="display: flex; align-items: center; gap: 1rem; margin-bottom:0.25rem; max-width: 500px;">
            <div id="studentSearchWrapper" style="position: relative; flex-grow: 1;">
                <form id="studentSearchForm" style="display: flex; gap: 0.5rem;">
                    <input type="text" id="studentSearchInput" class="form-control" placeholder="Search student by name or ID..." autocomplete="off" style="flex-grow:1;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
                <div id="studentSearchResults" class="dropdown-results" style="display:none;">
                </div>
            </div>
        </div>


        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-top: 1.5rem;">
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(37, 99, 235, 0.1);">
                    <i class="fas fa-user-graduate" style="color: var(--primary-color);"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Students</h3>
                    <p class="stat-number"><?php echo number_format($totalStudents); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(34, 197, 94, 0.1);">
                    <i class="fas fa-users" style="color: var(--success-color);"></i>
                </div>
                <div class="stat-details">
                    <h3>Active Sit-Ins</h3>
                    <p class="stat-number"><?php echo number_format($activeSitIns); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1);">
                    <i class="fas fa-calendar-check" style="color: var(--warning-color);"></i>
                </div>
                <div class="stat-details">
                    <h3>Pending Reservations</h3>
                    <p class="stat-number"><?php echo number_format($pendingReservations); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(59, 130, 246, 0.1);">
                    <i class="fas fa-laptop" style="color: var(--info-color);"></i>
                </div>
                <div class="stat-details">
                    <h3>Today's Sit-Ins</h3>
                    <p class="stat-number"><?php echo number_format($todaySitIns); ?></p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Lab Usage (Last 7 Days)</h3>
                </div>
                <div class="card-body" style="height: 300px;"> 
                    <canvas id="usageChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Lab Distribution (Last 30 Days)</h3>
                </div>
                <div class="card-body" style="height: 300px;">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activities-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Sit-Ins</h3>
                    <a href="current_sitin.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (!empty($recentSitIns)): ?>
                            <?php foreach ($recentSitIns as $sitin): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-laptop"></i>
                                    </div>
                                    <div class="activity-details">
                                        <h4><?php echo htmlspecialchars($sitin['student_name']); ?></h4>
                                        <p>
                                            <span class="lab-badge">
                                                <i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($sitin['lab']); ?>
                                            </span>
                                            <span class="time-badge">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('h:i A', strtotime($sitin['time_in'])); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users fa-2x"></i>
                                <p>No recent sit-ins</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Recent Reservations</h3>
                    <a href="reservations.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (!empty($recentReservations)): ?>
                            <?php foreach ($recentReservations as $reservation): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="activity-details">
                                        <h4><?php echo htmlspecialchars($reservation['student_name']); ?></h4>
                                        <p>
                                            <span class="lab-badge">
                                                <i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($reservation['lab']); ?>
                                            </span>
                                            <span class="status status-<?php echo strtolower($reservation['status']); ?>">
                                                <?php echo htmlspecialchars($reservation['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar fa-2x"></i>
                                <p>No recent reservations</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reserve Modal -->
    <div id="reserveModal" class="modal" style="display:none;">
      <div class="modal-content">
        <span class="close" id="closeReserveModal">×</span>
        <h2><i class="fas fa-calendar-plus"></i> Reserve for Walk-In</h2>
        <form id="walkinReserveForm">
          <div class="form-row">
            <input type="hidden" name="student_id" id="reserveStudentId">
            <input type="text" name="name" id="reserveStudentName" readonly>
            <input type="text" name="student_id_display" id="reserveStudentIdDisplay" readonly>
          </div>
          <div class="form-row">
            <input type="text" name="remaining_sessions" id="reserveStudentSessions" readonly>
            <input type="date" name="date" required>
            <input type="time" name="time" required>
          </div>
          <select name="lab" id="walkinLab" required>
            <option value="">Select Lab</option>
            <option value="524">524</option>
            <option value="526">526</option>
            <option value="528">528</option>
            <option value="530">530</option>
            <option value="542">542</option>
            <option value="544">544</option>
            <option value="517">517</option>
          </select>
          <select name="pc_number" id="walkinPC" required style="display:none">
            <option value="">Select PC</option>
          </select>
          <select name="purpose" required>
            <option value="">Select Purpose</option>
            <option value="C Programming">C Programming</option>
            <option value="Java Programming">Java Programming</option>
            <option value="C# Programming">C# Programming</option>
            <option value="System Integration and Architecture">System Integration and Architecture</option>
            <option value="Embedded System and IOT">Embedded System and IOT</option>
            <option value="Digital Logic and Design">Digital Logic and Design</option>
            <option value="Computer Application">Computer Application</option>
            <option value="Database">Database</option>
            <option value="Project Management">Project Management</option>
            <option value="Python Programming">Python Programming</option>
            <option value="Mobile Application">Mobile Application</option>
            <option value="Web Development">Web Development</option>
            <option value="Others">Others</option>
          </select>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary" onclick="onReserveClick(event)"><i class="fas fa-laptop"></i> Sit-In</button>
          </div>
        </form>
        <div id="reserveResult"></div>
      </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Usage Chart
            const usageCtx = document.getElementById('usageChart').getContext('2d');
            new Chart(usageCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($labUsage, 'date')); ?>,
                    datasets: [{
                        label: 'Sit-Ins',
                        data: <?php echo json_encode(array_column($labUsage, 'count')); ?>,
                        borderColor: 'rgb(37, 99, 235)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, suggestedMax: Math.max(...<?php echo json_encode(array_column($labUsage, 'count')); ?>) + 2 } } } } // Added suggestedMax for better y-axis scaling
            });

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($labDistribution, 'lab')); ?>,
                    datasets: [{ data: <?php echo json_encode(array_column($labDistribution, 'count')); ?>, backgroundColor: ['rgba(37, 99, 235, 0.8)','rgba(34, 197, 94, 0.8)','rgba(245, 158, 11, 0.8)','rgba(239, 68, 68, 0.8)','rgba(59, 130, 246, 0.8)'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        });

        // Student Search Dropdown Logic
        const studentSearchInput = document.getElementById('studentSearchInput');
        const resultsDiv = document.getElementById('studentSearchResults');
        const studentSearchForm = document.getElementById('studentSearchForm');
        const studentSearchWrapper = document.getElementById('studentSearchWrapper');

        function performSearch() {
            const query = studentSearchInput.value.trim();
            if (query.length < 1) {
                resultsDiv.innerHTML = '';
                resultsDiv.style.display = 'none';
                return;
            }

            fetch('search_student.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    resultsDiv.innerHTML = ''; 

                    if (!Array.isArray(data) || data.length === 0) {
                        resultsDiv.innerHTML = `<div class="alert-no-results"><i class="fas fa-user-slash"></i>No student found.</div>`;
                    } else {
                        data.forEach(student => {
                            const item = document.createElement('div');
                            item.className = 'search-result-item';
                            item.innerHTML = `
                                <div class="search-result-info">
                                    <span class="student-name">${student.name}</span>
                                    <span class="student-details">
                                        ID: ${student.id} • Sessions: <span class="sessions">${student.remaining_sessions || 0}</span>
                                    </span>
                                </div>
                                <button class="sitin-btn-dropdown" style="display:none;"><i class="fas fa-laptop"></i> Sit-In</button> 
                            `;
                            item.onclick = function() {
                                showReserveModal(student);
                                resultsDiv.innerHTML = '';
                                resultsDiv.style.display = 'none';
                                studentSearchInput.value = student.name; 
                            };
                            resultsDiv.appendChild(item);
                        });
                    }
                    resultsDiv.style.display = 'block';
                })
                .catch(err => {
                    resultsDiv.innerHTML = '<div class="alert-no-results"><i class="fas fa-exclamation-triangle"></i>Error searching.</div>';
                    resultsDiv.style.display = 'block';
                    console.error('Student search error:', err);
                });
        }

        studentSearchInput.addEventListener('input', performSearch);

        studentSearchInput.addEventListener('focus', () => {
            if (studentSearchInput.value.trim().length > 0 && resultsDiv.children.length > 0) { // Only show if there are results or input
                 performSearch(); // Re-perform search to show if hidden
            }
        });

        studentSearchForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            performSearch();    
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                if (resultsDiv.style.display === 'block') {
                    resultsDiv.innerHTML = '';
                    resultsDiv.style.display = 'none';
                }
            }
        });


        function showReserveModal(student) {
            document.getElementById('reserveStudentId').value = student.id;
            document.getElementById('reserveStudentName').value = student.name;
            document.getElementById('reserveStudentIdDisplay').value = student.id;
            document.getElementById('reserveStudentSessions').value = student.remaining_sessions || '0';
            
            const purposeSelect = document.querySelector('#reserveModal select[name="purpose"]');
            if (purposeSelect) { 
                if (student.purpose) {
                    let found = false;
                    for (let i = 0; i < purposeSelect.options.length; i++) {
                        if (purposeSelect.options[i].value === student.purpose) {
                            purposeSelect.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found) purposeSelect.selectedIndex = 0;
                } else {
                    purposeSelect.selectedIndex = 0;
                }
            }
            document.getElementById('reserveModal').style.display = 'flex';
        }

        document.getElementById('closeReserveModal').onclick = function() {
            document.getElementById('reserveModal').style.display = 'none';
            document.getElementById('reserveResult').innerHTML = '';
        };
        
        document.addEventListener('click', function(event) {
            const adminDropdown = document.getElementById('adminNotificationDropdown');
            const notificationBell = document.querySelector('.notification-bell');
            const reserveModal = document.getElementById('reserveModal');

            if (studentSearchWrapper && !studentSearchWrapper.contains(event.target) && resultsDiv.style.display === 'block') {
                resultsDiv.innerHTML = '';
                resultsDiv.style.display = 'none';
            }

            if (adminDropdown && adminDropdown.style.display === 'block' && 
                notificationBell && !notificationBell.contains(event.target) && 
                !adminDropdown.contains(event.target)) {
                 adminDropdown.style.display = 'none';
            }

            if (reserveModal && event.target == reserveModal) {
                reserveModal.style.display = 'none';
                document.getElementById('reserveResult').innerHTML = '';
            }
        });


        document.getElementById('walkinReserveForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('reserve_walkin.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                const reserveResultEl = document.getElementById('reserveResult');
                if (data.success) {
                    reserveResultEl.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> ${data.message}</div>`;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    reserveResultEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
                }
            });
        };

        function onReserveClick(event) { /* For walkinReserveForm submit button */ }

        const walkinLab = document.getElementById('walkinLab');
        const walkinDate = document.querySelector('#walkinReserveForm input[name="date"]');
        const walkinTime = document.querySelector('#walkinReserveForm input[name="time"]');
        const walkinPC = document.getElementById('walkinPC');

        function updateWalkinPCs() {
            const lab = walkinLab.value;
            const date = walkinDate.value;
            const time = walkinTime.value;
            if (lab && date && time) {
                fetch(`get_available_pcs.php?lab=${lab}&date=${date}&time=${time}`)
                    .then(res => res.json())
                    .then(pcs => {
                        walkinPC.innerHTML = '<option value="">Select PC</option>';
                        pcs.forEach(pc => { walkinPC.innerHTML += `<option value="${pc}">PC ${pc}</option>`; });
                        walkinPC.style.display = pcs.length ? 'block' : 'none';
                        walkinPC.required = pcs.length > 0;
                    });
            } else {
                walkinPC.innerHTML = '<option value="">Select PC</option>';
                walkinPC.style.display = 'none';
                walkinPC.required = false;
            }
        }
        if(walkinLab) walkinLab.addEventListener('change', updateWalkinPCs);
        if(walkinDate) walkinDate.addEventListener('change', updateWalkinPCs);
        if(walkinTime) walkinTime.addEventListener('change', updateWalkinPCs);

        function toggleAdminNotifications() {
            const dropdown = document.getElementById('adminNotificationDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        function markAdminAsRead(notificationId) {
            fetch('mark_admin_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'notification_id=' + notificationId })
            .then(response => response.json()).then(data => { if (data.success) location.reload(); });
        }
        function markAllAdminRead(e) {
            e.stopPropagation();
            fetch('mark_admin_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_all=1' })
            .then(response => response.json()).then(data => { if (data.success) location.reload(); });
        }
    </script>

    <style>
        /* General Card Body for Charts */
        .card .card-body {
            position: relative; /* Needed for chart responsiveness */
        }

        /* Student Search Input & Dropdown Styles */
        #studentSearchInput.form-control {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db; 
        }
        #studentSearchInput.form-control:focus {
            border-color: var(--primary-color, #2563eb);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
            outline: none;
        }
        #studentSearchForm .btn-primary { 
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
        }

        #studentSearchResults.dropdown-results {
            position: absolute;
            top: 100%; 
            left: 0;
            width: 100%; 
            background-color: #fff;
            border: 1px solid #e0e7ff;
            border-top: none; 
            border-radius: 0 0 0.5rem 0.5rem; 
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1050; 
        }

        .search-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6; 
            transition: background-color 0.15s ease-in-out;
            cursor: pointer; 
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item:hover {
            background-color: #f0f4ff; 
        }

        .search-result-info {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            flex-grow: 1; 
            margin-right: 1rem; 
        }
        .search-result-info .student-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
        }
        .search-result-info .student-details {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .search-result-info .student-details .sessions {
            font-weight: 500;
            color: var(--primary-color, #2563eb);
        }

        #studentSearchResults .alert-no-results {
            padding: 1rem 1.5rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }
        #studentSearchResults .alert-no-results i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: block;
            color: #9ca3af;
        }


        /* Reserve Modal Styles */
        #reserveModal.modal {
            display: flex; 
            align-items: center;
            justify-content: center;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.35);
            overflow: auto;
        }
        #reserveModal .modal-content {
            background: #fff;
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 1rem;
            max-width: 420px;
            width: 95%;
            position: relative;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            animation: modalPopIn 0.25s cubic-bezier(.4,2,.6,1) both;
        }

        @keyframes modalPopIn {
            0% { transform: scale(0.95) translateY(40px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        #reserveModal .close {
            position: absolute;
            right: 1.25rem;
            top: 1.25rem;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
        }
        #reserveModal .close:hover { color: #222; }

        #reserveModal h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary, #1a237e);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #reserveModal .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        #reserveModal .form-row input,
        #reserveModal .form-row select {
            flex: 1; 
        }


        #reserveModal input[type=\"text\"],
        #reserveModal input[type=\"date\"],
        #reserveModal input[type=\"time\"],
        #reserveModal select {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid #e0e7ef;
            border-radius: 0.5rem;
            font-size: 1rem;
            background: #f8fafc;
            color: #222;
            transition: border 0.2s;
            outline: none;
            margin-bottom: 1rem; 
        }
         #reserveModal .form-row input,
         #reserveModal .form-row select {
            margin-bottom: 0; 
         }


        #reserveModal input[type=\"text\"]:focus,
        #reserveModal input[type=\"date\"]:focus,
        #reserveModal input[type=\"time\"]:focus,
        #reserveModal select:focus {
            border-color: var(--primary-color, #2563eb);
            background: #fff;
        }

        #reserveModal .form-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
        }

        #reserveModal .btn-primary {
            background: var(--primary-color, #2563eb);
            color: #fff;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s, box-shadow 0.18s;
            box-shadow: 0 2px 8px rgba(37,99,235,0.08);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        #reserveModal .btn-primary:hover {
            background: #1746a0;
        }

        #reserveResult .alert {
            margin-top: 1rem;
            font-size: 0.98rem;
            padding: 0.8rem 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        #reserveResult .alert-success {
            background: #e6f4ea;
            color: #1e7e34;
        }
        #reserveResult .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 600px) {
            #reserveModal .modal-content {
                padding: 1.2rem 0.5rem 1rem 0.5rem;
                max-width: 98vw;
            }
            #reserveModal h2 { font-size: 1.1rem; }
            #reserveModal .form-row {
                flex-direction: column; 
                gap: 0; 
            }
            #reserveModal .form-row input,
            #reserveModal .form-row select {
                margin-bottom: 1rem; 
            }
            #reserveModal .form-row input:last-child,
            #reserveModal .form-row select:last-child {
                margin-bottom: 0;
            }
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; border-radius: 0.5rem; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 1.5rem; }
        .stat-details h3 { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .stat-number { font-size: 1.5rem; font-weight: 600; color: var(--text-primary); }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .activities-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; }
        .activity-list { display: flex; flex-direction: column; gap: 1rem; }
        .activity-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--light-bg); border-radius: 0.5rem; transition: transform 0.2s; }
        .activity-item:hover { transform: translateX(4px); }
        .activity-icon { width: 40px; height: 40px; border-radius: 0.5rem; background: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
        .activity-icon i { color: var(--primary-color); }
        .activity-details h4 { font-size: 0.875rem; margin-bottom: 0.25rem; }
        .activity-details p { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .empty-state { text-align: center; padding: 2rem; color: var(--text-secondary); }
        .empty-state i { margin-bottom: 0.5rem; }
        @media (max-width: 1024px) { .charts-grid, .activities-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } .stat-card { padding: 1rem; } }
    </style>
</body>
</html>