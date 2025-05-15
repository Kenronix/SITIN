<?php
session_start();
include 'conn.php'; // Database connection

// Check if admin is logged in
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit;
// }

// Get lab rooms from database (you can modify this query to match your database structure)
$stmt = $pdo->query("SELECT DISTINCT lab FROM sit_ins WHERE lab != '525' ORDER BY lab ASC");
$labs = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Default labs if none in database
if (empty($labs)) {
    $labs = ['524', '526', '528', '530', '542', '544', '517'];
}

// Remove lab 525 from the array in case it was added by some other process
$labs = array_filter($labs, function($lab) {
    return $lab !== '525';
});

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete old schedule for the specified lab
        $deleteStmt = $pdo->prepare("DELETE FROM lab_schedule WHERE lab = ?");
        $deleteStmt->execute([$_POST['lab']]);
        
        // Loop through each day and time slot
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        foreach ($days as $day) {
            // Process availability status
            if (isset($_POST[$day]) && is_array($_POST[$day])) {
                foreach ($_POST[$day] as $time => $status) {
                    $insertStmt = $pdo->prepare("INSERT INTO lab_schedule (lab, day, time_slot, status) VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([$_POST['lab'], $day, $time, $status]);
                }
            }
        }
        
        $pdo->commit();
        $success_message = "Schedule for Lab " . $_POST['lab'] . " has been updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating schedule: " . $e->getMessage();
    }
}

// Get the selected lab (default to first lab if none selected)
$selected_lab = isset($_GET['lab']) ? $_GET['lab'] : $labs[0];

// Get schedule for selected lab
$scheduleStmt = $pdo->prepare("SELECT day, time_slot, status FROM lab_schedule WHERE lab = ?");
$scheduleStmt->execute([$selected_lab]);
$schedule = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to easier to use format
$scheduleData = [];
foreach ($schedule as $slot) {
    $scheduleData[$slot['day']][$slot['time_slot']] = $slot['status'];
}

// Time slots from 7:30am to 5:30pm (10 slots of 1 hour each)
$timeSlots = [
    '7:30-8:30' => '7:30 AM - 8:30 AM',
    '8:30-9:30' => '8:30 AM - 9:30 AM',
    '9:30-10:30' => '9:30 AM - 10:30 AM',
    '10:30-11:30' => '10:30 AM - 11:30 AM',
    '11:30-12:30' => '11:30 AM - 12:30 PM',
    '12:30-1:30' => '12:30 PM - 1:30 PM',
    '1:30-2:30' => '1:30 PM - 2:30 PM',
    '2:30-3:30' => '2:30 PM - 3:30 PM',
    '3:30-4:30' => '3:30 PM - 4:30 PM',
    '4:30-5:30' => '4:30 PM - 5:30 PM'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule | Admin Panel</title>
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
            <li><a href="labsched.php" class="active"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-clock"></i> Lab Schedule Management</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-building"></i> Select Lab Room</h3>
            </div>
            <div class="card-body">
                <div class="lab-selector">
                    <form method="GET" action="labsched.php" class="lab-selection-form" id="labSelectionForm">
                        <input type="hidden" name="lab" id="selectedLab" value="<?php echo $selected_lab; ?>">
                    </form>
                    
                    <div class="lab-grid">
                        <?php foreach ($labs as $lab): 
                            $isSelected = ($lab == $selected_lab);
                        ?>
                            <div class="lab-card <?php echo $isSelected ? 'selected' : ''; ?>" 
                                 data-lab="<?php echo $lab; ?>" 
                                 onclick="selectLab('<?php echo $lab; ?>')">
                                <div class="lab-icon">
                                    <i class="fas fa-laptop"></i>
                                </div>
                                <div class="lab-info">
                                    <h3>Lab <?php echo $lab; ?></h3>
                                    <p>Room #<?php echo $lab; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Schedule for Lab <?php echo $selected_lab; ?></h3>
            </div>
            <div class="card-body">
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color unavailable"></div>
                        <span>Unavailable</span>
                    </div>
                </div>

                <form method="POST" action="labsched.php">
                    <input type="hidden" name="lab" value="<?php echo $selected_lab; ?>">
                    
                    <div class="table-container">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Monday</th>
                                    <th>Tuesday</th>
                                    <th>Wednesday</th>
                                    <th>Thursday</th>
                                    <th>Friday</th>
                                    <th>Saturday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeSlots as $time_key => $timeDisplay): ?>
                                    <tr>
                                        <td class="time-column"><?php echo $timeDisplay; ?></td>
                                        <?php foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day): 
                                            $current_status = isset($scheduleData[$day][$time_key]) ? $scheduleData[$day][$time_key] : 'available';
                                            $cell_class = $current_status == 'available' ? 'available' : 'unavailable';
                                        ?>
                                            <td class="<?php echo $cell_class; ?>">
                                                <div class="status-selector">
                                                    <select name="<?php echo $day; ?>[<?php echo $time_key; ?>]" class="status-select" data-day="<?php echo $day; ?>" data-time="<?php echo $time_key; ?>">
                                                        <option value="available" <?php echo $current_status == 'available' ? 'selected' : ''; ?>>Available</option>
                                                        <option value="unavailable" <?php echo $current_status == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                                    </select>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_schedule" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Lab Grid Layout */
        .lab-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .lab-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .lab-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .lab-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(37, 99, 235, 0.05);
        }

        .lab-icon {
            width: 48px;
            height: 48px;
            background: var(--light-bg);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .lab-info h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .lab-info p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Schedule Table Styles */
        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1rem 0;
        }

        .schedule-table th {
            background: var(--light-bg);
            padding: 1rem;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid var(--border-color);
        }

        .schedule-table td {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .time-column {
            font-weight: 500;
            background: var(--light-bg);
        }

        .status-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background: white;
            cursor: pointer;
        }

        .available {
            background-color: rgba(34, 197, 94, 0.1);
        }

        .unavailable {
            background-color: rgba(239, 68, 68, 0.1);
        }

        /* Legend Styles */
        .legend {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 0.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legend-color {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
        }

        .legend-color.available {
            background-color: rgba(34, 197, 94, 0.2);
            border: 2px solid rgba(34, 197, 94, 0.4);
        }

        .legend-color.unavailable {
            background-color: rgba(239, 68, 68, 0.2);
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        /* Form Actions */
        .form-actions {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .lab-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .schedule-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .lab-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .legend {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle status change
            const statusSelects = document.querySelectorAll('.status-select');
            
            statusSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    const cell = this.closest('td');
                    
                    // Update cell color based on selection
                    if (this.value === 'available') {
                        cell.className = 'available';
                    } else if (this.value === 'unavailable') {
                        cell.className = 'unavailable';
                    }
                });
            });
        });
        
        function selectLab(lab) {
            document.getElementById('selectedLab').value = lab;
            document.getElementById('labSelectionForm').submit();
        }
    </script>
</body>
</html>