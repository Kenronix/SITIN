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
    <title>Lab Schedule</title>
    <link rel="stylesheet" href="admins.css">
    <style>
        .schedule-container {
            margin-top: 20px;
            overflow-x: auto;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .schedule-table th, .schedule-table td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: center;
        }
        
        .schedule-table th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
        }
        
        .schedule-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .lab-selector {
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: flex-start;
            align-items: center;
        }
        
        .lab-card {
            flex: 0 0 120px;
            height: 90px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .lab-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .lab-card.selected {
            border-color: #007bff;
            background-color: #e7f1ff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .lab-card h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .lab-card p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #777;
        }
        
        .lab-selection-form {
            display: none;
        }
        
        .available {
            background-color: #d4edda;
        }
        
        .unavailable {
            background-color: #f8d7da;
        }
        
        .status-selector {
            margin-bottom: 5px;
        }
        
        .status-select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            width: 100%;
            background-color: white;
        }
        
        .submit-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        
        .submit-btn:hover {
            background-color: #0056b3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .legend {
            margin: 25px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 4px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .schedule-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .schedule-heading h3 {
            margin: 0;
            font-size: 22px;
            color: #333;
        }
        
        .info-text {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .time-column {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .lab-card {
                flex: 0 0 calc(33.333% - 10px);
            }
        }
        
        @media (max-width: 576px) {
            .lab-card {
                flex: 0 0 calc(50% - 10px);
            }
        }
    </style>
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
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <h2>Lab Schedule Management</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="lab-selector">
            <form method="GET" action="labsched.php" class="lab-selection-form" id="labSelectionForm">
                <input type="hidden" name="lab" id="selectedLab" value="<?php echo $selected_lab; ?>">
            </form>
            
            <?php foreach ($labs as $lab): 
                $isSelected = ($lab == $selected_lab);
            ?>
                <div class="lab-card <?php echo $isSelected ? 'selected' : ''; ?>" 
                     data-lab="<?php echo $lab; ?>" 
                     onclick="selectLab('<?php echo $lab; ?>')">
                    <h3>Lab <?php echo $lab; ?></h3>
                    <p>Room #<?php echo $lab; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        
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
            
            <div class="schedule-container">
                <div class="schedule-heading">
                    <h3>Schedule for Lab <?php echo $selected_lab; ?></h3>
                </div>
                <p class="info-text">Set the availability status for each time slot in the selected lab.</p>
                
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
            
            <button type="submit" name="update_schedule" class="submit-btn">Update Schedule</button>
        </form>
    </div>
    
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