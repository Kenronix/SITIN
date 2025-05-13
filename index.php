<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->execute([$_SESSION['id_number']]);
$user = $stmt->fetch();

// Get remaining sessions
$remaining_sessions = $user['remaining_sessions'];

// Get announcements
try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY date_published DESC LIMIT 5");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $announcements = [];
}

// Get reservation statistics
try {
    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_number = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_reservations = $stmt->fetchColumn();

    // Pending reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_number = ? AND status = 'Pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_reservations = $stmt->fetchColumn();

    // Approved reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_number = ? AND status = 'Approved'");
    $stmt->execute([$_SESSION['user_id']]);
    $approved_reservations = $stmt->fetchColumn();

    // Recent reservations
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id_number = ? ORDER BY date DESC, time_in DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $total_reservations = 0;
    $pending_reservations = 0;
    $approved_reservations = 0;
    $recent_reservations = [];
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
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
    <title>Dashboard - SITIN</title>
    <link rel="stylesheet" href="modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container fade-in">
            <!-- Welcome Section -->
            <div class="card mb-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="card-title">Welcome back, <?php echo htmlspecialchars($user['firstname']); ?>!</h1>
                        <p class="text-secondary">You have <?php echo $remaining_sessions; ?> remaining sessions</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="status-badge status-approved">
                            <i class="fas fa-check-circle"></i>
                            <span>Active Student</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check"></i>
                            Total Reservations
                        </h3>
                    </div>
                    <div class="card-value"><?php echo $total_reservations; ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i>
                            Pending
                        </h3>
                    </div>
                    <div class="card-value"><?php echo $pending_reservations; ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-check-circle"></i>
                            Approved
                        </h3>
                    </div>
                    <div class="card-value"><?php echo $approved_reservations; ?></div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs-container">
                <div class="tab-navigation">
                    <button type="button" class="tab-button <?php echo $activeTab == 'announcements' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'announcements')">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </button>
                    <button class="tab-button <?php echo $activeTab == 'sitinrules' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'sitinrules')">
                        <i class="fas fa-book"></i> Sit-In Rules
                    </button>
                    <button class="tab-button <?php echo $activeTab == 'labrules' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'labrules')">
                        <i class="fas fa-clipboard-list"></i> Lab Rules
                    </button>
                    <button class="tab-button <?php echo $activeTab == 'labschedule' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'labschedule')">
                        <i class="fas fa-clock"></i> Lab Schedule
                    </button>
                    <button class="tab-button <?php echo $activeTab == 'resources' ? 'active' : ''; ?>" 
                            onclick="openTab(event, 'resources')">
                        <i class="fas fa-file-alt"></i> Resources
                    </button>
                </div>

                <!-- Announcements Tab -->
                <div id="announcements" class="tab-content <?php echo $activeTab == 'announcements' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3 class="card-title mb-3">
                            <i class="fas fa-bullhorn"></i>
                            Latest Announcements
                        </h3>
                        <div class="latest-announcements">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <strong><?= htmlspecialchars($announcement['title']) ?></strong><br>
                                    <?= htmlspecialchars($announcement['content']) ?><br>
                                    <span class="announcement-date">
                                        <i class="fa fa-clock"></i>
                                        Posted on: <?= date('F j, Y h:i A', strtotime($announcement['date_published'])) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Lab Schedule Tab -->
                <div id="labschedule" class="tab-content <?php echo $activeTab == 'labschedule' ? 'active' : ''; ?>">
                    <div class="card">
                        <div class="lab-schedule-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock"></i>
                                Lab Schedule
                            </h3>
                        </div>
                        
                        <div class="lab-tabs lab-tabs-padded">
                            <form method="GET" action="" id="labSelectionForm" class="d-flex gap-2">
                                <input type="hidden" name="tab" value="labschedule">
                                <?php foreach ($labs as $lab): 
                                    $isSelected = ($lab == $selected_lab);
                                ?>
                                    <button type="submit" name="lab" value="<?php echo $lab; ?>" 
                                            class="btn <?php echo $isSelected ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Lab <?php echo $lab; ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        </div>
                        
                        <div class="table-container mt-3">
                            <table class="table">
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
                                                $status = isset($scheduleData[$day][$time_key]) ? $scheduleData[$day][$time_key] : 'available';
                                                $statusClass = $status == 'available' ? 'status-approved' : 'status-rejected';
                                            ?>
                                                <td>
                                                    <span class="status-badge <?php echo $statusClass; ?>">
                                                        <i class="fas fa-<?php echo $status == 'available' ? 'check' : 'times'; ?>"></i>
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Resources Tab -->
                <div id="resources" class="tab-content <?php echo $activeTab == 'resources' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3 class="card-title mb-3">
                            <i class="fas fa-file-alt"></i>
                            Lab Resources
                        </h3>
                        <?php if (count($resources) > 0): ?>
                            <div class="table-container">
                                <table class="table">
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
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas <?php echo getFileIcon($resource['file_type']); ?> me-2"></i>
                                                        <?php echo htmlspecialchars($resource['file_name']); ?>
                                                        <span class="status-badge status-pending ms-2">
                                                            <?php echo strtoupper($resource['file_type']); ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td><?php echo formatFileSize($resource['file_size']); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($resource['upload_date'])); ?></td>
                                                <td>
                                                    <a href="<?php echo $resource['file_path']; ?>" class="btn btn-primary" download>
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-secondary">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <h3>No Resources Available</h3>
                                <p>There are no resources available at this time.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rules Tabs Content -->
                <div id="sitinrules" class="tab-content <?php echo $activeTab == 'sitinrules' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3 class="card-title mb-3">
                            <i class="fas fa-book"></i>
                            Sit-In Rules and Guidelines
                        </h3>
                        <div class="rules-container">
                            <div class="rules-section">
                                <h4><i class="fas fa-info-circle section-icon"></i> General Information</h4>
                                <p>
                                    The <strong>Sit-In program</strong> allows students to use computer laboratory facilities outside of their regular class hours. This service is designed to provide students with additional time to complete coursework, conduct research, and practice skills learned in class.
                                </p>
                                <div class="highlight-box">
                                    <span class="highlight-label">Available Hours:</span> <span class="highlight-value">Monday to Friday (7:30 AM - 5:30 PM), Saturday (7:30 AM - 12:00 PM)</span><br>
                                    <span class="highlight-label">Location:</span> <span class="highlight-value">Computer Laboratories 524, 526, 528, 530, 542, 544, and 517</span>
                                </div>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-user-check section-icon"></i> Eligibility and Session Allocation</h4>
                                <ul class="custom-list">
                                    <li>All currently enrolled students are eligible to use the Sit-In service.</li>
                                    <li>Each student is allocated a specific number of Sit-In sessions per semester.</li>
                                    <li>Students must have remaining session credits to avail of the Sit-In service.</li>
                                    <li>Session credits cannot be transferred to other students or carried over to the next semester.</li>
                                    <li>Additional session credits may be granted for special academic requirements with proper documentation and approval from the department.</li>
                                </ul>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-calendar-check section-icon"></i> Reservation Process</h4>
                                <ul class="custom-list">
                                    <li>Students must make reservations through the online system at least <strong>1 hour before</strong> the intended Sit-In time.</li>
                                    <li>Walk-in requests are subject to availability and may be declined if laboratories are fully booked.</li>
                                    <li>Reservations can be made up to <strong>7 days in advance</strong>.</li>
                                    <li>Each reservation is for a <strong>1-hour time slot</strong>. Students requiring more time must make multiple consecutive reservations.</li>
                                    <li>Students can cancel reservations up to <strong>2 hours before</strong> the scheduled time without penalty.</li>
                                    <li>Repeated no-shows (3 or more) will result in temporary suspension of Sit-In privileges.</li>
                                </ul>
                                <div class="note-box">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <strong>Note:</strong> During peak periods (midterms and finals week), reservation policies may be adjusted to accommodate higher demand. Please check announcements for any temporary changes.
                                </div>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-check-circle section-icon"></i> Check-in Procedure</h4>
                                <ul class="custom-list">
                                    <li>Students must arrive within 15 minutes of their reserved time slot. Late arrivals may forfeit their reservation.</li>
                                    <li>Present your valid student ID to the laboratory assistant upon arrival.</li>
                                    <li>Sign in using the provided system or logbook before using a computer.</li>
                                    <li>The laboratory assistant will assign a specific computer unit. Students are not allowed to switch units without permission.</li>
                                    <li>Personal belongings must be stored in designated areas as directed by laboratory staff.</li>
                                </ul>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-file-alt section-icon"></i> Usage Policies</h4>
                                <ul class="custom-list">
                                    <li>Computer usage is strictly for academic purposes only.</li>
                                    <li>Software installation or modification of computer settings is prohibited.</li>
                                    <li>Students may use USB drives for file transfer but must scan them for viruses before use.</li>
                                    <li>Printing services are available according to the standard printing policies.</li>
                                    <li>Food and drinks are not allowed inside the laboratory.</li>
                                    <li>Students must vacate the computer promptly at the end of their scheduled time.</li>
                                    <li>All work must be saved and personal files removed before leaving. The department is not responsible for any lost data.</li>
                                </ul>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-users section-icon"></i> Conduct and Behavior</h4>
                                <ul class="custom-list">
                                    <li>Maintain a quiet environment conducive to study and work.</li>
                                    <li>Mobile phones should be set to silent mode.</li>
                                    <li>Group work is permitted only if it does not disturb other users.</li>
                                    <li>Treat laboratory equipment with care. Report any issues to the laboratory staff immediately.</li>
                                    <li>Follow all instructions from laboratory staff.</li>
                                    <li>Violation of any laboratory rules may result in immediate termination of the Sit-In session and possible disciplinary action.</li>
                                </ul>
                            </div>
                            <div class="rules-section">
                                <h4><i class="fas fa-exclamation-circle section-icon"></i> Sanctions for Violations</h4>
                                <ul class="custom-list">
                                    <li><strong>Minor Violations:</strong> Verbal warning and documentation</li>
                                    <li><strong>Repeated Minor Violations:</strong> Temporary suspension of Sit-In privileges (1 week)</li>
                                    <li><strong>Major Violations:</strong> Suspension of Sit-In privileges for the remainder of the semester and referral to the Discipline Office</li>
                                    <li><strong>Damage to Equipment:</strong> Responsibility for repair costs and suspension of privileges until resolved</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="labrules" class="tab-content <?php echo $activeTab == 'labrules' ? 'active' : ''; ?>">
                    <div class="card">
                        <div class="rules-container">
                            <div class="rules-section">
                                <h3 class="card-title mb-3">
                                    <i class="fas fa-clipboard-list"></i>
                                    Laboratory Rules and Regulations
                                </h3>
                                <p class="mb-3" style="font-weight:600;">
                                    To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:
                                </p>
                                <ol class="custom-ol">
                                    <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                                    <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                                    <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                                    <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                                    <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                                    <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                                    <li>
                                        Observe proper decorum while inside the laboratory.
                                        <ul class="custom-ul">
                                            <li>Do not use the lab for personal purposes.</li>
                                            <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                                            <li>Follow the seating arrangement of your instructor.</li>
                                            <li>At the end of class, all software programs must be closed.</li>
                                            <li>Return all chairs to their proper places after using.</li>
                                        </ul>
                                    </li>
                                    <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                                    <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                                    <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                                    <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                                    <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
                                </ol>
                                <div class="disciplinary-action mt-3">
                                    <strong>DISCIPLINARY ACTION</strong>
                                    <ul class="custom-ul">
                                        <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                                        <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove 'active' from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show the selected tab and activate the button
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');

            // Update the URL without reloading the page
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabButton = document.querySelector(`.tab-button[onclick="openTab(event, '${tab}')"]`);
                if (tabButton) {
                    document.getElementById(tab).classList.add('active');
                    tabButton.classList.add('active');
                }
            }
        });
    </script>
</body>
</html>