<?php
session_start();
include 'conn.php'; // Your database connection file

// Initialize WHERE clauses and parameters
$where_clauses = [];
$parameters = [];

// Filter by status
$allowed_statuses = ['pending', 'approved', 'rejected']; // Define allowed statuses for the filter
if (!empty($_GET['status_filter']) && in_array($_GET['status_filter'], $allowed_statuses)) {
    $where_clauses[] = "r.status = :status_filter";
    $parameters[':status_filter'] = $_GET['status_filter'];
}

// Filter by date range
if (!empty($_GET['date_from'])) {
    $where_clauses[] = "r.date >= :date_from";
    $parameters[':date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where_clauses[] = "r.date <= :date_to";
    $parameters[':date_to'] = $_GET['date_to'];
}

// Base SQL query
$sql = "SELECT r.id, r.id_number, CONCAT(u.firstname, ' ', u.lastname) as student_name,
               u.course, u.year_level, r.lab, r.pc_number, r.date, r.time_in,
               r.purpose, r.status
        FROM reservations r
        JOIN users u ON r.id_number = u.id_number";

// Append WHERE clauses if any
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add ordering
$sql .= " ORDER BY r.date DESC, r.time_in DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parameters);
$all_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Helper function for status icons
function get_status_icon($status) {
    switch (strtolower($status)) {
        case 'pending': return 'hourglass-half';
        case 'approved': return 'check-circle';
        case 'rejected': return 'times-circle';
        case 'completed': return 'check-double';
        case 'cancelled': return 'ban';
        default: return 'info-circle';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Log | Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css"> <!-- Main admin styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Ensure these CSS variables are defined in admin_style.css or adjust as needed */
        /*
        :root {
            --text-primary: #374151;
            --text-secondary: #4b5563;
            --light-bg: #f8f9fa; / #f9fafb
            --border-color: #d1d5db; / #e5e7eb
            --primary-color: #007bff; / #4f46e5 (blue/indigo)
            --primary-dark-color: #0056b3; / #4338ca
        }
        */

        /* General table and badge styles (should be consistent with reservations.php) */
        .student-info { display: flex; flex-direction: column; }
        .student-name { font-weight: 500; color: var(--text-primary, #374151); }
        .course-badge, .lab-badge, .date-badge, .time-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem;
            background-color: var(--light-bg, #f8f9fa); color: var(--text-secondary, #4b5563);
        }
        .lab-badge.text-muted { color: #9ca3af; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.875rem;
            font-weight: 500; text-transform: capitalize; line-height: 1;
        }
        .status-badge i { font-size: 0.8em; }
        .status-badge.pending { background-color: #e0f2fe; color: #0284c7; }
        .status-badge.approved { background-color: #dcfce7; color: #16a34a; }
        .status-badge.rejected { background-color: #fee2e2; color: #dc2626; }
        .status-badge.completed { background-color: #f3f4f6; color: #4b5563; }
        .status-badge.cancelled { background-color: #fef9c3; color: #b45309; }

        .empty-state {
            text-align: center; padding: 3rem 1rem; color: var(--text-secondary, #6b7280);
            background-color: #fff; border-radius: 8px; margin-top: 1.5rem;
        }
        .empty-state i { color: var(--text-secondary, #6b7280); margin-bottom: 1rem; }
        .empty-state h3 { margin-top: 0; margin-bottom: 0.5rem; font-size: 1.25rem; color: var(--text-primary, #374151); }

        .table-container {
            margin-top: 1.5rem; background-color: #fff; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); }
        th {
            background-color: var(--light-bg, #f9fafb); font-weight: 600;
            color: var(--text-primary, #374151); text-transform: uppercase;
            font-size: 0.75rem; letter-spacing: 0.05em;
        }
        td { font-size: 0.875rem; color: var(--text-secondary, #4b5563); }

        /* Filter Section Styles - Designed to match the image */
        .filter-container {
            background-color: #fff; /* White background for the filter area */
            padding: 1.5rem;      /* Padding around the form elements */
            border-radius: 8px;   /* Rounded corners */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); /* Subtle shadow */
            margin-bottom: 1.5rem; /* Space below the filter section */
        }
        .filter-form {
                   /* Arrange items in a row */
            gap: 1.5rem;           /* Space between each filter group/button */
            align-items: flex-end; /* Align bottom edges of items (important for button) */
            flex-wrap: wrap;       /* Allow items to wrap to next line on smaller screens */
        }
        .filter-group {
            display: flex;
            flex-direction: column; /* Stack label on top of its input/select */
        }
        .filter-form label {
            font-weight: 500;
            color: var(--text-primary, #374151); /* Darker text for labels */
            margin-bottom: 0.35rem; /* Space between label and input */
            font-size: 0.875rem;   /* Standard smallish font size */
        }
        .filter-form select,
        .filter-form input[type="date"] {
            padding: 0.6rem 0.75rem; /* Comfortable padding inside inputs */
            border: 1px solid var(--border-color, #d1d5db); /* Standard border */
            border-radius: 6px;       /* Slightly rounded input corners */
            font-size: 0.875rem;
            min-width: 170px; /* Adjust if needed, balances appearance and space */
                               /* For date inputs, browsers add their own styling (like calendar icon) */
        }
        .filter-form button {
            padding: 0.65rem 1.25rem; /* Padding for the button */
            background-color: var(--primary-color, #007bff); /* Primary button color (blue) */
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s;
            line-height: normal; /* Ensure text is centered vertically */
        }
        .filter-form button:hover {
            background-color: var(--primary-dark-color, #0056b3); /* Darker shade on hover */
        }
        .filter-form button i {
            margin-right: 0.5rem; /* Space between icon and text in button */
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 1024px) { /* For slightly smaller desktops or tablets, allow table scroll */
            .table-container {
                overflow-x: auto;
            }
            th, td { white-space: nowrap; } /* Prevent text wrapping in table cells */
        }
        @media (max-width: 768px) { /* For tablets and mobile, stack filter items */
             .filter-form {
                flex-direction: column; /* Stack filter groups vertically */
                align-items: stretch;   /* Make filter groups take full width */
                gap: 1rem;              /* Reduce gap when stacked */
            }
            .filter-group {
                width: 100%; /* Full width for each group */
            }
            .filter-form select,
            .filter-form input[type="date"],
            .filter-form button {
                width: 100%;     /* Full width for inputs and button */
                min-width: 0;    /* Reset min-width */
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-laptop-code"></i> Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> Reservation</a></li>
            <li><a href="reservation_log.php" class="active"><i class="fas fa-history"></i> Reservation Log</a></li>
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
            <h1><i class="fas fa-history"></i> Reservation Log</h1>
        </div>

        <div class="filter-container">
            <form method="GET" action="reservation_log.php" class="filter-form">
                <div class="filter-group">
                    <label for="status_filter">Status:</label>
                    <select name="status_filter" id="status_filter">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= (($_GET['status_filter'] ?? '') == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= (($_GET['status_filter'] ?? '') == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= (($_GET['status_filter'] ?? '') == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                </div>

                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        <?php if (!empty($all_reservations)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Res. ID</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course & Year</th>
                            <th>Lab</th>
                            <th>PC</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_reservations as $log_entry): ?>
                            <tr>
                                <td><?= htmlspecialchars($log_entry['id']) ?></td>
                                <td><?= htmlspecialchars($log_entry['id_number']) ?></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?= htmlspecialchars($log_entry['student_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="course-badge">
                                        <?= htmlspecialchars($log_entry['course']) ?> -
                                        <?= htmlspecialchars($log_entry['year_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="lab-badge">
                                        <i class="fas fa-laptop"></i>
                                        <?= htmlspecialchars($log_entry['lab']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log_entry['pc_number'])): ?>
                                        <span class="lab-badge"><i class="fas fa-desktop"></i> <?= htmlspecialchars($log_entry['pc_number']) ?></span>
                                    <?php else: ?>
                                        <span class="lab-badge text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="date-badge">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($log_entry['date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="time-badge">
                                        <i class="far fa-clock"></i>
                                        <?= date('h:i A', strtotime($log_entry['time_in'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log_entry['purpose']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower(htmlspecialchars($log_entry['status'])) ?>">
                                        <i class="fas fa-<?= get_status_icon($log_entry['status']) ?>"></i>
                                        <?= htmlspecialchars($log_entry['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search fa-3x"></i>
                <h3>No Reservation Records Found</h3>
                <p>No records match your current filter criteria, or the log is empty.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>