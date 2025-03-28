<?php
include 'conn.php'; // Database connection
session_start();

$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current sit-ins (this was missing in your code)

$current_sitin_stmt = $pdo->query("SELECT s.id, s.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                s.lab, s.time_in, s.purpose 
                                FROM sit_ins s 
                                JOIN users u ON s.id_number = u.id_number 
                                WHERE s.time_out IS NULL 
                                ORDER BY s.time_in DESC");
$current_sitins = $current_sitin_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reservations (this was missing in your code)
$reservations_stmt = $pdo->query("SELECT r.id, r.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                r.lab, r.date, r.time_in, r.purpose, r.status 
                                FROM reservations r 
                                JOIN users u ON r.id_number = u.id_number 
                                ORDER BY r.date DESC, r.time_in DESC");
$reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    // Validation Rules
    if (empty($title) || empty($content)) {
        echo "<script>alert('Title and content cannot be empty.');</script>";
    } elseif (strlen($title) > 100) {
        echo "<script>alert('Title should not exceed 100 characters.');</script>";
    } elseif (strlen($content) > 500) {
        echo "<script>alert('Content should not exceed 500 characters.');</script>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
        if ($stmt->execute([$title, $content])) {
            header("Location: ".$_SERVER['PHP_SELF']); // Prevent resubmission
            exit();
        } else {
            echo "<script>alert('Error publishing announcement.');</script>";
        }
    }
}

// ADD THIS: AJAX endpoint to search for a student by ID
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $id_number = $_GET['search'];
    $stmt = $pdo->prepare("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, 
                          course, year_level, email, remaining_sessions 
                          FROM users 
                          WHERE id_number = ? AND role = 'student'");
    $stmt->execute([$id_number]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        // Return student data as JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    exit;
}



// NEW AJAX endpoint to view feedback details
if (isset($_GET['action']) && $_GET['action'] === 'view_feedback' && isset($_GET['feedback_id'])) {
    $feedback_id = $_GET['feedback_id'];
    
    // Validate feedback ID
    if (!is_numeric($feedback_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
        exit;
    }
    
    try {
        // Get feedback details
        $stmt = $pdo->prepare("SELECT f.*, CONCAT(u.firstname, ' ', u.lastname) AS student_name
                             FROM feedback f
                             JOIN users u ON f.id_number = u.id_number
                             WHERE f.id = ?");
        $stmt->execute([$feedback_id]);
        $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($feedback) {
            echo json_encode(['success' => true, 'feedback' => $feedback]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Feedback not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// NEW AJAX endpoint to handle reservation actions (approve, reject, view)
if (isset($_GET['action']) && isset($_GET['reservation_id'])) {
    $action = $_GET['action'];
    $reservation_id = $_GET['reservation_id'];
    
    // Validate reservation ID
    if (!is_numeric($reservation_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
        exit;
    }
    
    switch ($action) {
        case 'approve':
            // Start a transaction
            $pdo->beginTransaction();
            try {
                // Get reservation details
                $stmt = $pdo->prepare("SELECT id_number, lab, time_in, date, purpose FROM reservations WHERE id = ?");
                $stmt->execute([$reservation_id]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$reservation) {
                    throw new Exception("Reservation not found");
                }
                
                // Update the reservation status to Approved
                $stmt = $pdo->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
                $stmt->execute([$reservation_id]);
                
                // Reduce student's remaining sessions by 1
                $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0");
                $stmt->execute([$reservation['id_number']]);
                
                // Create a sit-in record for the approved reservation
                $stmt = $pdo->prepare("INSERT INTO sit_ins (id_number, lab, purpose, time_in, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $reservation['id_number'], 
                    $reservation['lab'], 
                    $reservation['purpose'],
                    $reservation['time_in'],
                    $reservation['date']
                ]);
                
                // Commit the transaction
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Reservation approved successfully. The student has been added to sit-ins and remaining sessions reduced.']);
            } catch (Exception $e) {
                // Roll back the transaction in case of error
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to approve reservation: ' . $e->getMessage()]);
            }
            break;
            
        case 'reject':
            // Update the reservation status to Rejected
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'Rejected' WHERE id = ?");
            if ($stmt->execute([$reservation_id])) {
                echo json_encode(['success' => true, 'message' => 'Reservation rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject reservation']);
            }
            break;
            
        case 'view':
            // Get reservation details
            $stmt = $pdo->prepare("SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                 u.course, u.year_level, u.email
                                 FROM reservations r
                                 JOIN users u ON r.id_number = u.id_number
                                 WHERE r.id = ?");
            $stmt->execute([$reservation_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                echo json_encode(['success' => true, 'reservation' => $reservation]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Reservation not found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="content-area">
        <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
        
        <div class="search-container">
            <div class="input-group mb-3">
                <input type="text" id="searchStudent" class="form-control" placeholder="Enter ID Number">
                <button id="searchButton" class="btn btn-primary">Search</button>
            </div>
            <div id="searchResult" class="mt-2"></div>
        </div>

        <div class="tab-container">
        <div class="tab-buttons">
                <button class="tab-btn active" data-tab="current-sitin">Current SitIn</button>
                <button class="tab-btn" data-tab="students">Students</button>
                <button class="tab-btn" data-tab="reservations">Reservations</button>
                <button class="tab-btn" data-tab="feedback">Feedback</button>
                <button class="tab-btn" data-tab="announcements">Announcements</button>
            </div>

            <div class="tab-content active"
            
            id="current-sitin">
                <h3>Current SitIn Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Student Name</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($current_sitins)): ?>
                            <?php foreach ($current_sitins as $sitin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sitin['id_number']) ?></td>
                                    <td><?= htmlspecialchars($sitin['student_name']) ?></td>
                                    <td><?= htmlspecialchars($sitin['lab']) ?></td>
                                    <td><?= htmlspecialchars(date('h:i A', strtotime($sitin['time_in']))) ?></td>
                                    <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                                    <td><span class="status status-active">Active</span></td>
                                    <td>
                                        <button class="action-btn reject-btn" data-id="<?= $sitin['id'] ?>"><i class="fas fa-sign-out-alt"></i> Logout</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">No active sit-ins at the moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Students Tab -->
            <div class="tab-content" id="students">
                <h3>Student List</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Email</th>
                            <th>Remaining Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id_number']) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['course']) ?></td>
                                <td><?= htmlspecialchars($user['year_level']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['remaining_sessions']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Reservations Tab -->
            <div class="tab-content" id="reservations">
            <h3>Reservation Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reservations)): ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php 
                            $statusClass = '';
                            switch($reservation['status']) {
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
                                <td><?= htmlspecialchars($reservation['id_number']) ?></td>
                                <td><?= htmlspecialchars($reservation['student_name']) ?></td>
                                <td><?= htmlspecialchars($reservation['lab']) ?></td>
                                <td><?= htmlspecialchars(date('F d, Y', strtotime($reservation['date']))) ?></td>
                                <td><?= htmlspecialchars(date('h:i A', strtotime($reservation['time_in']))) ?></td>
                                <td><?= htmlspecialchars($reservation['purpose']) ?></td>
                                <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($reservation['status']) ?></span></td>
                                <td>
                                    <button class="action-btn view-reservation-btn" data-id="<?= $reservation['id'] ?>"><i class="fas fa-eye"></i></button>
                                    <?php if ($reservation['status'] == 'Pending'): ?>
                                        <button class="action-btn approve-reservation-btn" data-id="<?= $reservation['id'] ?>"><i class="fas fa-check"></i></button>
                                        <button class="action-btn reject-reservation-btn" data-id="<?= $reservation['id'] ?>"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data">No reservations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Add this to the tab-content section, before the closing div for tab-container -->
<div class="tab-content" id="feedback">
    <h3>Student Feedback</h3>
    <table>
        <thead>
            <tr>
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Lab</th>
                <th>Date</th>
                <th>Rating</th>
                <th>Comments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Get feedback from database
            $feedback_stmt = $pdo->query("SELECT f.id, f.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                        f.lab, f.date, f.rating, f.comments
                                        FROM feedback f
                                        JOIN users u ON f.id_number = u.id_number
                                        ORDER BY f.date DESC");
            $feedbacks = $feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($feedbacks)): 
                foreach ($feedbacks as $feedback): 
            ?>
                <tr>
                    <td><?= htmlspecialchars($feedback['id_number']) ?></td>
                    <td><?= htmlspecialchars($feedback['student_name']) ?></td>
                    <td><?= htmlspecialchars($feedback['lab']) ?></td>
                    <td><?= htmlspecialchars(date('F d, Y', strtotime($feedback['date']))) ?></td>
                    <td>
                        <?php 
                        // Display stars based on rating
                        for($i = 1; $i <= 5; $i++) {
                            if($i <= $feedback['rating']) {
                                echo '<i class="fas fa-star text-warning"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($feedback['comments']) ?></td>
                    <td>
                        <button class="action-btn view-feedback-btn" data-id="<?= $feedback['id'] ?>">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            <?php 
                endforeach; 
            else: 
            ?>
                <tr>
                    <td colspan="7" class="no-data">No feedback records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
            <!-- Announcements Tab -->
            <div class="tab-content" id="announcements">
                <h3>Create New Announcement</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea class="form-control" id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" name="submit_announcement" class="btn btn-primary">Publish Announcement</button>
                </form>

            </div>
        </div>
        </div>
    </div>

    <!-- Modified Reservation Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reserve a Lab</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="reservationForm">
                        <div class="form-group mb-3">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" id="student_id" name="id_number" class="form-control" readonly>
                        </div>
                        <div class="form-group mb-3">
                            <label for="student_name" class="form-label">Student Name</label>
                            <input type="text" id="student_name" class="form-control" readonly>
                        </div>
                        <div class="form-group mb-3">
                            <label for="lab" class="form-label">Lab</label>
                            <select class="form-control" id="lab" name="lab" required>
                                <option value="">Select Lab</option>
                                <option value="524">524</option>
                                <option value="525">525</option>
                                <option value="526">526</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="purpose" class="form-label">Purpose</label>
                            <select class="form-control" id="purpose" name="purpose" required>
                                <option value="">Select Purpose</option>
                                <option value="C Programming">C Programming</option>
                                <option value="Java Programming">Java Programming</option>
                                <option value="Web Development">Web Development</option>
                                <option value="Database">Database</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="time_in" class="form-label">Time In</label>
                            <input type="time" class="form-control" id="time_in" name="time_in" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                        <button type="submit" class="btn btn-success">Reserve</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div class="modal fade" id="viewFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="feedback-details">
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Student:</div>
                            <div class="col-7" id="view-feedback-student"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Lab:</div>
                            <div class="col-7" id="view-feedback-lab"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Date:</div>
                            <div class="col-7" id="view-feedback-date"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Rating:</div>
                            <div class="col-7" id="view-feedback-rating"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Comments:</div>
                            <div class="col-7" id="view-feedback-comments"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Reservation Details Modal -->
    <div class="modal fade" id="viewReservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reservation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="reservation-details">
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">ID Number:</div>
                            <div class="col-7" id="view-id-number"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Student Name:</div>
                            <div class="col-7" id="view-student-name"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Course & Year:</div>
                            <div class="col-7" id="view-course-year"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Lab:</div>
                            <div class="col-7" id="view-lab"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Date:</div>
                            <div class="col-7" id="view-date"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Time:</div>
                            <div class="col-7" id="view-time"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Purpose:</div>
                            <div class="col-7" id="view-purpose"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Status:</div>
                            <div class="col-7" id="view-status"></div>
                        </div>
                    </div>
                    <div class="modal-footer" id="reservation-action-buttons">
                        <!-- Action buttons will be dynamically added here for pending reservations -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
            document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to the clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Handle announcement form submission
            const announcementForm = document.getElementById('announcementForm');
            if (announcementForm) {
                announcementForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const title = document.getElementById('title').value;
                    const content = document.getElementById('content').value;
                    
                    alert(`Announcement "${title}" has been published`);
                    
                    // Reset form
                    announcementForm.reset();
                });
            }
            
            // Add search functionality
            const searchButton = document.getElementById('searchButton');
            const searchInput = document.getElementById('searchStudent');
            const searchResult = document.getElementById('searchResult');
            const reservationModal = new bootstrap.Modal(document.getElementById('reservationModal'));
            const viewReservationModal = new bootstrap.Modal(document.getElementById('viewReservationModal'));
            
            searchButton.addEventListener('click', function() {
                const idNumber = searchInput.value.trim();
                if (idNumber === '') {
                    searchResult.innerHTML = '<div class="alert alert-warning">Please enter an ID number</div>';
                    return;
                }
                
                // AJAX request to search for student
                fetch(`admin.php?search=${idNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Student found, show reservation modal
                            const student = data.student;
                            document.getElementById('student_id').value = student.id_number;
                            document.getElementById('student_name').value = student.name;
                            
                            // Set minimum date to today
                            const today = new Date().toISOString().split('T')[0];
                            document.getElementById('date').min = today;
                            
                            // Show the modal
                            reservationModal.show();
                            
                            // Clear the search result
                            searchResult.innerHTML = '';
                        } else {
                            // Student not found
                            searchResult.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        searchResult.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                    });
            });
            
            // Handle view reservation button clicks
            document.querySelectorAll('.view-reservation-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    
                    // AJAX request to get reservation details
                    fetch(`admin.php?action=view&reservation_id=${reservationId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const reservation = data.reservation;
                                
                                // Populate the modal with reservation details
                                document.getElementById('view-id-number').textContent = reservation.id_number;
                                document.getElementById('view-student-name').textContent = reservation.student_name;
                                document.getElementById('view-course-year').textContent = `${reservation.course} - ${reservation.year_level}`;
                                document.getElementById('view-lab').textContent = reservation.lab;
                                document.getElementById('view-date').textContent = new Date(reservation.date).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                                document.getElementById('view-time').textContent = new Date(`2000-01-01T${reservation.time_in}`).toLocaleTimeString('en-US', {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                document.getElementById('view-purpose').textContent = reservation.purpose;
                                
                                // Set status with appropriate styling
                                const statusElement = document.getElementById('view-status');
                                statusElement.textContent = reservation.status;
                                statusElement.className = ''; // Reset classes
                                
                                switch(reservation.status) {
                                    case 'Pending':
                                        statusElement.classList.add('badge', 'bg-warning', 'text-dark');
                                        break;
                                    case 'Approved':
                                        statusElement.classList.add('badge', 'bg-success');
                                        break;
                                    case 'Rejected':
                                        statusElement.classList.add('badge', 'bg-danger');
                                        break;
                                    case 'Completed':
                                        statusElement.classList.add('badge', 'bg-primary');
                                        break;
                                }
                                
                                // Add action buttons for pending reservations
                                const actionButtonsContainer = document.getElementById('reservation-action-buttons');
                                actionButtonsContainer.innerHTML = ''; // Clear previous buttons
                                
                                if (reservation.status === 'Pending') {
                                    // Add approve button
                                    const approveButton = document.createElement('button');
                                    approveButton.className = 'btn btn-success me-2';
                                    approveButton.innerHTML = '<i class="fas fa-check"></i> Approve';
                                    approveButton.addEventListener('click', function() {
                                        handleReservationAction('approve', reservationId);
                                    });
                                    
                                    // Add reject button
                                    const rejectButton = document.createElement('button');
                                    rejectButton.className = 'btn btn-danger';
                                    rejectButton.innerHTML = '<i class="fas fa-times"></i> Reject';
                                    rejectButton.addEventListener('click', function() {
                                        handleReservationAction('reject', reservationId);
                                    });
                                    
                                    actionButtonsContainer.appendChild(approveButton);
                                    actionButtonsContainer.appendChild(rejectButton);
                                }
                                
                                // Show the modal
                                viewReservationModal.show();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while fetching reservation details');
                        });
                });
            });
            
            // Handle approve reservation button clicks
            document.querySelectorAll('.approve-reservation-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    handleReservationAction('approve', reservationId);
                });
            });
            
            // Handle reject reservation button clicks
            document.querySelectorAll('.reject-reservation-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    handleReservationAction('reject', reservationId);
                });
            });
            
            // Function to handle reservation actions (approve/reject)
            function handleReservationAction(action, reservationId) {
                const actionText = action === 'approve' ? 'approve' : 'reject';
                const confirmMessage = `Are you sure you want to ${actionText} this reservation?`;
                
                if (confirm(confirmMessage)) {
                    // AJAX request to perform the action
                    fetch(`admin.php?action=${action}&reservation_id=${reservationId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                
                                // Close the view modal if open
                                if (viewReservationModal._isShown) {
                                    viewReservationModal.hide();
                                }
                                
                                // Reload the page to refresh the reservations list
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert(`An error occurred while ${actionText}ing the reservation`);
                        });
                }
            }
            
            // Handle reservation form submission
            const reservationForm = document.getElementById('reservationForm');
            reservationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                // Validate form data
                const lab = formData.get('lab');
                const purpose = formData.get('purpose');
                const timeIn = formData.get('time_in');
                const date = formData.get('date');
                const idNumber = formData.get('id_number');
                
                if (!lab || !purpose || !timeIn || !date) {
                    alert('Please fill all required fields');
                    return;
                }
                
                // Directly create a sit-in record
                fetch('create_sitin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sit-in created successfully!');
                        reservationModal.hide();
                        
                        // Switch to current sit-in tab
                        const currentSitInTab = document.querySelector('.tab-btn[data-tab="current-sitin"]');
                        if (currentSitInTab) {
                            currentSitInTab.click(); // Programmatically click the current sit-in tab
                        }
                        
                        // Reload the page to refresh the sit-ins list
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the sit-in');
                });
            });

            // Announcement form validation
            document.querySelector('form[name="announcement_form"]').addEventListener('submit', function(event) {
                const title = document.getElementById('title').value.trim();
                const content = document.getElementById('content').value.trim();

                if (title === "" || content === "") {
                    alert("Title and content cannot be empty.");
                    event.preventDefault(); // Prevent form submission
                } else if (title.length > 100) {
                    alert("Title should not exceed 100 characters.");
                    event.preventDefault();
                } else if (content.length > 500) {
                    alert("Content should not exceed 500 characters.");
                    event.preventDefault();
                } else {
                    // Confirmation prompt before submission
                    const confirmPublish = confirm(`Are you sure you want to publish this announcement?\n\nTitle: ${title}`);
                    if (!confirmPublish) {
                        event.preventDefault(); // Stop the form from submitting
                    }
                }
            });

            // Handle view feedback button clicks
            document.querySelectorAll('.view-feedback-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const feedbackId = this.getAttribute('data-id');
                    
                    // AJAX request to get feedback details
                    fetch(`admin.php?action=view_feedback&feedback_id=${feedbackId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const feedback = data.feedback;
                                
                                // Populate the modal with feedback details
                                document.getElementById('view-feedback-student').textContent = feedback.student_name;
                                document.getElementById('view-feedback-lab').textContent = feedback.lab;
                                document.getElementById('view-feedback-date').textContent = new Date(feedback.date).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                                
                                // Display rating as stars
                                const ratingElement = document.getElementById('view-feedback-rating');
                                ratingElement.innerHTML = '';
                                for (let i = 1; i <= 5; i++) {
                                    const star = document.createElement('i');
                                    star.className = i <= feedback.rating ? 'fas fa-star text-warning' : 'far fa-star';
                                    ratingElement.appendChild(star);
                                }
                                
                                document.getElementById('view-feedback-comments').textContent = 
                                    feedback.comments ? feedback.comments : 'No comments provided';
                                
                                // Show the modal
                                new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while fetching feedback details');
                        });
                });
            });
        });
    </script>
</body>
</html>