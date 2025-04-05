<?php
session_start();
require_once 'conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch student data using session user_id
$sql = "SELECT id_number, lastname, firstname, middlename, course, year_level FROM users WHERE id = :user_id";
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
    $date = $_POST['date'];
    $lab = $_POST['lab'];
    $purpose = $_POST['purpose'];
    $remaining_session = 30; // Default value
    $status = 'Pending'; // Default status for new reservations

    // Validate date and time
    $current_date = date('Y-m-d');
    $selected_date = $_POST['date'];
    $selected_time = strtotime($_POST['time_in']);
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
        // Check for existing reservations for the same lab, date, and time
        $check_sql = "SELECT COUNT(*) FROM reservations WHERE lab = :lab AND date = :date AND time_in = :time_in";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([
            'lab' => $lab,
            'date' => $date,
            'time_in' => $time_in
        ]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "This time slot is already reserved for the selected lab.";
        } else {
            // Prepare SQL statement using PDO with status field
            $sql = "INSERT INTO reservations (id_number, lab, purpose, status, time_in, date, remaining_session) 
                    VALUES (:id_number, :lab, :purpose, :status, :time_in, :date, :remaining_session)";
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            $params = [
                'id_number' => $id_number,
                'lab' => $lab,
                'purpose' => $purpose,
                'status' => $status, // Added status parameter with default 'Pending'
                'time_in' => $time_in,
                'date' => $date,
                'remaining_session' => $remaining_session
            ];

            // Execute the statement
            if ($stmt->execute($params)) {
                // Refresh reservations list
                $reservation_stmt->execute(['id_number' => $student_data['id_number']]);
                $reservations = $reservation_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<script>alert('Reservation successfully created!');</script>";
            } else {
                $errors[] = "Failed to create reservation";
            }
        }
    }
}

        if (isset($_POST['id_number'])) {
            $id_number = $_POST['id_number'];
            $query = "SELECT * FROM students WHERE id_number = '$id_number'";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) > 0) {
                $student = mysqli_fetch_assoc($result);
                echo "<p>Name: " . $student['name'] . "</p>";
                echo "<p>ID: " . $student['id_number'] . "</p>";
                echo "<button class='btn btn-success' onclick='reserveStudent(" . $student['id_number'] . ")'>Reserve</button>";
            } else {
                echo "<p>No student found.</p>";
            }
        }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .reservation-table th, 
        .reservation-table td {
            text-align: center;
            vertical-align: middle;
        }
        .table-responsive {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .status-pending {
            background-color: #fff3cd;
        }
        .status-approved {
            background-color: #d4edda;
        }
        .status-rejected {
            background-color: #f8d7da;
        }
        .status-completed {
            background-color: #cce5ff;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php">Home</a>
            <a href="#">History</a>
            <a href="reservation.php" class="active">Reservation</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    <div class="container mt-5">
        <h2 class="mb-4">Make a Reservation</h2>
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
                    <option value="525">525</option>
                    <option value="526">526</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="purpose" class="form-label">Purpose:</label>
                <select class="form-control" id="purpose" name="purpose" required>
                    <option value="">Select Purpose</option>
                    <option value="C Programming">C Programming</option>
                    <option value="Java Programming">Java Programming</option>
                    <option value="Web Development">Web Development</option>
                    <option value="Database">Database</option>
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
                            // Get the status or set default to 'Pending'
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
                                <td><?php echo date('F d, Y', strtotime($reservation['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($reservation['time_in'])); ?></td>
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

    <script>

    </script>
</body>
</html>