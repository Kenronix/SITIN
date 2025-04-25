<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$remaining_sessions = $user['remaining_sessions'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get announcements
try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY date_published DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get lab rooms from database
try {
    $stmt = $pdo->query("SELECT DISTINCT lab FROM lab_schedule ORDER BY lab ASC");
    $labs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Default labs if none in database
    if (empty($labs)) {
        $labs = ['524', '526', '528', '530', '542', '544', '517'];
    }
} catch (PDOException $e) {
    $labs = ['524', '526', '528', '530', '542', '544', '517'];
}

// Get the selected lab (default to first lab if none selected)
$selected_lab = isset($_GET['lab']) ? $_GET['lab'] : $labs[0];

// Time slots from 7:30am to 5:30pm
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

// Get schedule for selected lab
try {
    $scheduleStmt = $pdo->prepare("SELECT day, time_slot, status FROM lab_schedule WHERE lab = ?");
    $scheduleStmt->execute([$selected_lab]);
    $schedule = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert to easier to use format
    $scheduleData = [];
    foreach ($schedule as $slot) {
        $scheduleData[$slot['day']][$slot['time_slot']] = $slot['status'];
    }
} catch (PDOException $e) {
    $scheduleData = [];
}

// Get current day of the week
$currentDay = strtolower(date('l'));
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// Set default active tab (can be changed by URL parameter)
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'announcements';

// Get resources
try {
    $resourcesStmt = $pdo->query("SELECT * FROM lab_resources ORDER BY upload_date DESC");
    $resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $resources = [];
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to get file icon based on file type
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image'
    ];
    
    return isset($icons[$fileType]) ? $icons[$fileType] : 'fa-file';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="overallstyle.css">
    <link rel="stylesheet" href="modal.css">

    <title>Dashboard</title>
    <style>
        /* Tabs Styling */
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tab-navigation {
            display: flex;
            overflow-x: auto;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
            padding-bottom: 2px;
            gap: 5px;
        }
        
        .tab-button {
            padding: 12px 20px;
            background-color: #f8f9fa;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            color: #495057;
            transition: all 0.2s ease;
            flex-shrink: 0;
            position: relative;
            bottom: -2px;
        }
        
        .tab-button.active {
            background-color: #fff;
            color: #007bff;
            border: 2px solid #e9ecef;
            border-bottom: 2px solid #fff;
        }
        
        .tab-button:hover:not(.active) {
            background-color: #e9ecef;
        }
        
        .tab-content {
            display: none;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Lab Schedule Styles */
        .lab-schedule-section {
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .lab-schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .lab-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .lab-tab {
            padding: 8px 16px;
            border-radius: 20px;
            background-color: #e9ecef;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            border: none;
        }
        
        .lab-tab.active {
            background-color: #007bff;
            color: white;
        }
        
        .schedule-container {
            overflow-x: auto;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .schedule-table th, .schedule-table td {
            border: 1px solid #e9ecef;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }
        
        .schedule-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .schedule-table th.current-day {
            background-color: #007bff;
            color: white;
        }
        
        .time-slot {
            text-align: left;
            font-weight: 500;
            background-color: #f8f9fa;
        }
        
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            border-radius: 3px;
        }
        
        /* Announcement Styles */
        .announcement {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .announcement:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .announcement .title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #343a40;
        }
        
        .announcement .date {
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .announcement-image {
            max-width: 100%;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        /* Rules Styling */
        .rules-container {
            line-height: 1.6;
        }
        
        .rules-container li {
            margin-bottom: 8px;
        }
        
        .rules-container b {
            color: #343a40;
        }
        
        .rules-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .rules-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .rules-section h4 {
            color: #343a40;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .rules-section ol {
            padding-left: 20px;
        }
        
        .rules-section ul {
            padding-left: 20px;
            list-style-type: disc;
        }
        
        .highlight-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .note-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .tab-navigation {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .tab-button {
                white-space: nowrap;
            }
            
            .schedule-table {
                min-width: 600px;
            }
        }
                /* Resources Tab Styles */
        .resources-container {
            margin-top: 20px;
        }

        .resource-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .resource-table th, .resource-table td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }

        .resource-table th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
        }

        .resource-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .resource-table tr:hover {
            background-color: #e9ecef;
        }

        .file-icon {
            font-size: 1.2rem;
            margin-right: 10px;
            vertical-align: middle;
        }

        .resource-info {
            display: flex;
            align-items: center;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
        }

        .btn-success {
            color: #fff;
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #343a40;
        }

        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }

        .file-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }

        /* File type colors */
        .file-type-pdf { background-color: #f40f02; color: white; }
        .file-type-doc, .file-type-docx { background-color: #2b579a; color: white; }
        .file-type-xls, .file-type-xlsx { background-color: #217346; color: white; }
        .file-type-ppt, .file-type-pptx { background-color: #d24726; color: white; }
        .file-type-txt { background-color: #5c5c5c; color: white; }
        .file-type-zip, .file-type-rar { background-color: #ffc107; color: #212529; }
        .file-type-jpg, .file-type-jpeg, .file-type-png { background-color: #6610f2; color: white; }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="session-info">Remaining Session: <?php echo $remaining_sessions; ?></div>
            
            <!-- Tabbed Interface -->
            <div class="tabs-container">
                <div class="tab-navigation">
                    <button class="tab-button <?php echo $activeTab == 'announcements' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'announcements')">Announcements</button>
                    <button class="tab-button <?php echo $activeTab == 'sitinrules' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'sitinrules')">Sit-In Rules</button>
                    <button class="tab-button <?php echo $activeTab == 'labrules' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'labrules')">Lab Rules</button>
                    <button class="tab-button <?php echo $activeTab == 'labschedule' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'labschedule')">Lab Schedule</button>
                            <button class="tab-button <?php echo $activeTab == 'resources' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'resources')">Resources</button>
                </div>
                
                <!-- Announcements Tab -->
                <div id="announcements" class="tab-content <?php echo $activeTab == 'announcements' ? 'active' : ''; ?>">
                    <h3>Announcements</h3>
                    <?php if (isset($_GET['success'])): ?>
                        <p class="success-message">Announcement published successfully!</p>
                    <?php endif; ?>
                    
                    <?php if (empty($announcements)): ?>
                        <p>No announcements at this time.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $row): ?>
                            <div class="announcement">
                                <p class="title"><?php echo htmlspecialchars($row['title']); ?></p>
                                <p><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>
                                <?php if (!empty($row['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" class="announcement-image">
                                <?php endif; ?>
                                <p class="date">Posted on: <?php echo $row['date_published']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Sit-In Rules Tab -->
                <div id="sitinrules" class="tab-content <?php echo $activeTab == 'sitinrules' ? 'active' : ''; ?>">
                    <h3>Sit-In Rules and Guidelines</h3>
                    <div class="rules-container">
                        <div class="rules-section">
                            <h4>General Information</h4>
                            <p>The Sit-In program allows students to use computer laboratory facilities outside of their regular class hours. This service is designed to provide students with additional time to complete coursework, conduct research, and practice skills learned in class.</p>
                            
                            <div class="highlight-box">
                                <strong>Available Hours:</strong> Monday to Friday (7:30 AM - 5:30 PM), Saturday (7:30 AM - 12:00 PM)<br>
                                <strong>Location:</strong> Computer Laboratories 524, 526, 528, 530, 542, 544, and 517
                            </div>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Eligibility and Session Allocation</h4>
                            <ol>
                                <li>All currently enrolled students are eligible to use the Sit-In service.</li>
                                <li>Each student is allocated a specific number of Sit-In sessions per semester.</li>
                                <li>Students must have remaining session credits to avail of the Sit-In service.</li>
                                <li>Session credits cannot be transferred to other students or carried over to the next semester.</li>
                                <li>Additional session credits may be granted for special academic requirements with proper documentation and approval from the department.</li>
                            </ol>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Reservation Process</h4>
                            <ol>
                                <li>Students must make reservations through the online system at least 1 hour before the intended Sit-In time.</li>
                                <li>Walk-in requests are subject to availability and may be declined if laboratories are fully booked.</li>
                                <li>Reservations can be made up to 7 days in advance.</li>
                                <li>Each reservation is for a 1-hour time slot. Students requiring more time must make multiple consecutive reservations.</li>
                                <li>Students can cancel reservations up to 2 hours before the scheduled time without penalty.</li>
                                <li>Repeated no-shows (3 or more) will result in temporary suspension of Sit-In privileges.</li>
                            </ol>
                            
                            <div class="note-box">
                                <strong>Note:</strong> During peak periods (midterms and finals week), reservation policies may be adjusted to accommodate higher demand. Please check announcements for any temporary changes.
                            </div>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Check-in Procedure</h4>
                            <ol>
                                <li>Students must arrive within 15 minutes of their reserved time slot. Late arrivals may forfeit their reservation.</li>
                                <li>Present your valid student ID to the laboratory assistant upon arrival.</li>
                                <li>Sign in using the provided system or logbook before using a computer.</li>
                                <li>The laboratory assistant will assign a specific computer unit. Students are not allowed to switch units without permission.</li>
                                <li>Personal belongings must be stored in designated areas as directed by laboratory staff.</li>
                            </ol>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Usage Policies</h4>
                            <ol>
                                <li>Computer usage is strictly for academic purposes only.</li>
                                <li>Software installation or modification of computer settings is prohibited.</li>
                                <li>Students may use USB drives for file transfer but must scan them for viruses before use.</li>
                                <li>Printing services are available according to the standard printing policies.</li>
                                <li>Food and drinks are not allowed inside the laboratory.</li>
                                <li>Students must vacate the computer promptly at the end of their scheduled time.</li>
                                <li>All work must be saved and personal files removed before leaving. The department is not responsible for any lost data.</li>
                            </ol>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Conduct and Behavior</h4>
                            <ol>
                                <li>Maintain a quiet environment conducive to study and work.</li>
                                <li>Mobile phones should be set to silent mode.</li>
                                <li>Group work is permitted only if it does not disturb other users.</li>
                                <li>Treat laboratory equipment with care. Report any issues to the laboratory staff immediately.</li>
                                <li>Follow all instructions from laboratory staff.</li>
                                <li>Violation of any laboratory rules may result in immediate termination of the Sit-In session and possible disciplinary action.</li>
                            </ol>
                        </div>
                        
                        <div class="rules-section">
                            <h4>Sanctions for Violations</h4>
                            <ul>
                                <li><strong>Minor Violations:</strong> Verbal warning and documentation</li>
                                <li><strong>Repeated Minor Violations:</strong> Temporary suspension of Sit-In privileges (1 week)</li>
                                <li><strong>Major Violations:</strong> Suspension of Sit-In privileges for the remainder of the semester and referral to the Discipline Office</li>
                                <li><strong>Damage to Equipment:</strong> Responsibility for repair costs and suspension of privileges until resolved</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Lab Rules Tab -->
                <div id="labrules" class="tab-content <?php echo $activeTab == 'labrules' ? 'active' : ''; ?>">
                    <h3>Laboratory Rules and Regulations</h3>
                    <div class="rules-container">
                        <b>To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</b><br><br>

                        1. Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.<br><br>

                        2. Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.<br><br>

                        3. Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.<br><br>

                        4. Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.<br><br>

                        5. Deleting computer files and changing the set-up of the computer is a major offense.<br><br>

                        6. Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".<br><br>

                        7. Observe proper decorum while inside the laboratory.<br>
                            <ul>
                                <li>Do not use the lab for personal purposes.</li>
                                <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                                <li>Follow the seating arrangement of your instructor.</li>
                                <li>At the end of class, all software programs must be closed.</li>
                                <li>Return all chairs to their proper places after using.</li>
                            </ul><br>
                            
                        8. Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.<br><br>

                        9. Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.<br><br>

                        10. Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.<br><br>

                        11. For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.<br><br>

                        12. Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.<br><br>


                        <b>DISCIPLINARY ACTION</b><br>
                        <ul>
                            <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                            <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Lab Schedule Tab -->
                <div id="labschedule" class="tab-content <?php echo $activeTab == 'labschedule' ? 'active' : ''; ?>">
                    <h3>Lab Schedule</h3>
                    <div class="lab-schedule-section">
                        <div class="lab-schedule-header">
                            <h4>Lab Availability</h4>
                        </div>
                        
                        <div class="lab-tabs">
                            <form method="GET" action="" id="labSelectionForm">
                                <input type="hidden" name="tab" value="labschedule">
                                <?php foreach ($labs as $lab): 
                                    $isSelected = ($lab == $selected_lab);
                                ?>
                                    <button type="submit" name="lab" value="<?php echo $lab; ?>" class="lab-tab <?php echo $isSelected ? 'active' : ''; ?>">
                                        Lab <?php echo $lab; ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        </div>
                        
                        <div class="schedule-container">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <?php foreach($days as $day): 
                                            $dayClass = ($day == $currentDay) ? 'current-day' : '';
                                        ?>
                                            <th class="<?php echo $dayClass; ?>"><?php echo ucfirst($day); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeSlots as $time_key => $timeDisplay): ?>
                                        <tr>
                                            <td class="time-slot"><?php echo $timeDisplay; ?></td>
                                            <?php foreach($days as $day): 
                                                $current_status = isset($scheduleData[$day][$time_key]) ? $scheduleData[$day][$time_key] : 'available';
                                                $cell_class = $current_status;
                                            ?>
                                                <td class="<?php echo $cell_class; ?>">
                                                    <?php echo ucfirst($current_status); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
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
                        </div>
                    </div>
                </div>
                <!-- Resources Tab -->
                <div id="resources" class="tab-content <?php echo $activeTab == 'resources' ? 'active' : ''; ?>">
                    <h3>Lab Resources</h3>
                    <div class="resources-container">
                        <?php if (count($resources) > 0): ?>
                            <table class="resource-table">
                                <thead>
                                    <tr>
                                        <th>Resource Name</th>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Uploaded On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($resource['resource_name']); ?></td>
                                            <td>
                                                <div class="resource-info">
                                                    <i class="fas <?php echo getFileIcon($resource['file_type']); ?> file-icon"></i>
                                                    <?php echo htmlspecialchars($resource['file_name']); ?>
                                                    <span class="file-type-badge file-type-<?php echo $resource['file_type']; ?>">
                                                        <?php echo strtoupper($resource['file_type']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo formatFileSize($resource['file_size']); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($resource['upload_date'])); ?></td>
                                            <td>
                                                <a href="<?php echo $resource['file_path']; ?>" class="btn btn-success" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <h3>No Resources Available</h3>
                                <p>There are no resources available at this time.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript for tab functionality
        function openTab(evt, tabName) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Hide all tab content
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            var tablinks = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Show the current tab and add an "active" class to the button that opened the tab
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        // Activate the tab based on URL parameter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabButton = document.querySelector(`.tab-button[onclick="openTab(event, '${tab}')"]`);
                if (tabButton) {
                    document.getElementById(tab).classList.add("active");
                    tabButton.classList.add("active");
                }
            }
        });
    </script>
</body>
</html>