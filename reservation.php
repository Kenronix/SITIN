<?php include 'sidebar.php'; ?>

<div class="main-content">
<?php
session_start();
require_once 'conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch student data using session user_id
$sql = "SELECT id_number, lastname, firstname, middlename, course, year_level, remaining_sessions FROM users WHERE id_number = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's reservations
$reservation_sql = "SELECT * FROM reservations WHERE id_number = :id_number ORDER BY date DESC, time_in DESC";
$reservation_stmt = $pdo->prepare($reservation_sql);
$reservation_stmt->execute(['id_number' => $student_data['id_number']]);
$reservations = $reservation_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = $_POST['id_number'];
    $time_in = $_POST['time_in'];
    $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');  // fallback to today if missing
    $lab = $_POST['lab'];
    $purpose = $_POST['purpose'];
    $remaining_session = 30; // Default value
    $status = 'Pending'; // Default status for new reservations

    // Validate date and time
    $current_date = date('Y-m-d');
    $selected_date = $date;
    $selected_time = strtotime($time_in);
    $start_time = strtotime('08:00:00');
    $end_time = strtotime('17:00:00');

    $errors = [];

    if ($selected_date < $current_date) {
        $errors[] = "Cannot reserve for past dates";
    }

    if ($selected_time < $start_time || $selected_time > $end_time) {
        $errors[] = "Reservation time must be between 8:00 AM and 5:00 PM";
    }

    if (empty($errors)) {
        // Check lab schedule availability
        $day_of_week = strtolower(date('l', strtotime($date)));
        $time_slot = date('H:i', strtotime($time_in));

        // Map the time to the appropriate time slot
        $time_slots = [
            '7:30-8:30' => ['07:30', '08:30'],
            '8:30-9:30' => ['08:30', '09:30'],
            '9:30-10:30' => ['09:30', '10:30'],
            '10:30-11:30' => ['10:30', '11:30'],
            '11:30-12:30' => ['11:30', '12:30'],
            '12:30-1:30' => ['12:30', '13:30'],
            '1:30-2:30' => ['13:30', '14:30'],
            '2:30-3:30' => ['14:30', '15:30'],
            '3:30-4:30' => ['15:30', '16:30'],
            '4:30-5:30' => ['16:30', '17:30']
        ];

        $selected_time_slot = null;
        foreach ($time_slots as $slot => $times) {
            if ($time_slot >= $times[0] && $time_slot < $times[1]) {
                $selected_time_slot = $slot;
                break;
            }
        }

        if ($selected_time_slot) {
            $schedule_sql = "SELECT status FROM lab_schedule WHERE lab = :lab AND day = :day AND time_slot = :time_slot";
            $schedule_stmt = $pdo->prepare($schedule_sql);
            $schedule_stmt->execute([
                'lab' => $lab,
                'day' => $day_of_week,
                'time_slot' => $selected_time_slot
            ]);
            $schedule_status = $schedule_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule_status || $schedule_status['status'] !== 'available') {
                $errors[] = "The lab is not available at the selected time according to the lab schedule.";
            }
        } else {
            $errors[] = "Invalid time slot selected.";
        }

        if (empty($errors)) {
            // Check for existing reservations for the same lab, date, time
            $check_sql = "SELECT COUNT(*) FROM reservations WHERE lab = :lab AND date = :date AND time_in = :time_in";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                'lab' => $lab,
                'date' => $date,
                'time_in' => $time_in
            ]);

            $existing_count = $check_stmt->fetchColumn();

            if ($existing_count > 0) {
                $errors[] = "This lab is already reserved for the selected time slot.";
            } else {
                // Prepare SQL statement using PDO with status field
                $sql = "INSERT INTO reservations (id_number, lab, purpose, status, time_in, date, remaining_session) 
                        VALUES (:id_number, :lab, :purpose, :status, :time_in, :date, :remaining_session)";

                $stmt = $pdo->prepare($sql);

                $params = [
                    'id_number' => $id_number,
                    'lab' => $lab,
                    'purpose' => $purpose,
                    'status' => $status,
                    'time_in' => $time_in,
                    'date' => $date,
                    'remaining_session' => $remaining_session
                ];

                if ($stmt->execute($params)) {
                    $reservation_stmt->execute(['id_number' => $student_data['id_number']]);
                    $reservations = $reservation_stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<script>alert('Reservation successfully created!');</script>";
                } else {
                    $errors[] = "Failed to create reservation";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation</title>
    <link rel="stylesheet" href="modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Make a Reservation</h2>

        <?php if (isset($student_data['remaining_sessions'])): ?>
            <div class="alert alert-info" style="font-size:1.1rem; font-weight:500;">
                Remaining Sessions: <?php echo htmlspecialchars($student_data['remaining_sessions']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach($errors as $error): ?>
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($student_data): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-3">
                <label for="id_number" class="form-label">ID Number:</label>
                <input type="text" class="form-control" id="id_number" name="id_number" 
                    value="<?php echo htmlspecialchars($student_data['id_number']); ?>" readonly>
            </div>

            <div class="mb-3">
                <label for="fullname" class="form-label">Student Name:</label>
                <input type="text" class="form-control" id="fullname" 
                    value="<?php echo htmlspecialchars($student_data['lastname'] . ', ' . $student_data['firstname'] . ' ' . $student_data['middlename']); ?>" readonly>
            </div>

            <div class="mb-3">
                <label for="lab" class="form-label">Lab:</label>
                <select class="form-control" id="lab" name="lab" required>
                    <option value="">Select Lab</option>
                    <option value="524">524</option>
                    <option value="526">526</option>
                    <option value="528">528</option>
                    <option value="530">530</option>
                    <option value="542">542</option>
                    <option value="544">544</option>
                    <option value="517">517</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="purpose" class="form-label">Purpose:</label>
                <select class="form-control" id="purpose" name="purpose" required>
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
            </div>

            <div class="mb-3">
                <label for="time_in" class="form-label">Time In:</label>
                <input type="time" class="form-control" id="time_in" name="time_in" required>
            </div>

            <div class="mb-3">
                <label for="date" class="form-label">Date:</label>
                <input type="date" class="form-control" id="date" name="date" required>
            </div>

            <button type="submit" class="btn btn-primary">Reserve</button>
        </form>

        <!-- Reservations Table -->
        <div class="table-responsive">
            <h3>Your Reservations</h3>
            <?php if (!empty($reservations)): ?>
                <table class="table table-striped table-bordered reservation-table">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Lab</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php 
                            $statusClass = '';
                            $status = isset($reservation['status']) ? $reservation['status'] : 'Pending';
                            switch($status) {
                                case 'Pending':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'Approved':
                                    $statusClass = 'status-approved';
                                    break;
                                case 'Rejected':
                                    $statusClass = 'status-rejected';
                                    break;
                                case 'Completed':
                                    $statusClass = 'status-completed';
                                    break;
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php 
                                    $dateValue = $reservation['date'];
                                    if (!empty($dateValue) && $dateValue !== '0000-00-00' && strtotime($dateValue) !== false) {
                                        echo date('F d, Y', strtotime($dateValue));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $timeValue = $reservation['time_in'];
                                    if (!empty($timeValue) && strtotime($timeValue) !== false) {
                                        echo date('h:i A', strtotime($timeValue));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($reservation['lab']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                <td class="<?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">You have no reservations yet.</div>
            <?php endif; ?>
        </div>

        <?php else: ?>
            <div class="alert alert-danger">Student not found. Please try logging in again.</div>
        <?php endif; ?>
    </div>
</body>
</html>
</div>
