<?php
session_start();
include 'conn.php'; // Database connection

// Fetch all students
$stmt = $pdo->query("SELECT id_number, CONCAT(firstname, ' ', lastname) AS name, course, year_level, email, remaining_sessions FROM users WHERE role = 'student' ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch lab usage statistics
$labStmt = $pdo->query("SELECT lab, COUNT(*) as count FROM sit_ins GROUP BY lab ORDER BY lab ASC");
$labStats = $labStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch purpose statistics
$purposeStmt = $pdo->query("SELECT purpose, COUNT(*) as count FROM sit_ins GROUP BY purpose ORDER BY count DESC");
$purposeStats = $purposeStmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admins.css">
        <link rel="stylesheet" href="modal.css">
    <!-- Chart.js for graphs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        <h1>Welcome to Admin Dashboard</h1>
        
        <div class="search-container">
            <div class="input-group mb-3">
                <input type="text" id="searchStudent" class="form-control" placeholder="Enter ID Number">
                <button id="searchButton" class="btn btn-primary">Search</button>
            </div>
            <div id="searchResult" class="mt-2"></div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mt-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Lab Usage Statistics</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="labChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Purpose Statistics</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="purposeChart"></canvas>
                    </div>
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
                                <option value="526">526</option>
                                <option value="528">528</option>
                                <option value="530">530</option>
                                <option value="542">542</option>
                                <option value="544">544</option>
                                <option value="517">517</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="purpose" class="form-label">Purpose</label>
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
                        <div class="form-group mb-3">
                            <label for="time_in" class="form-label">Time In</label>
                            <input type="time" class="form-control" id="time_in" name="time_in" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                        <button type="submit" class="btn btn-success">Sit-in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap Modal
            const reservationModal = new bootstrap.Modal(document.getElementById('reservationModal'));
            
            // Add search functionality
            const searchButton = document.getElementById('searchButton');
            const searchInput = document.getElementById('searchStudent');
            const searchResult = document.getElementById('searchResult');
            
            searchButton.addEventListener('click', function() {
                const idNumber = searchInput.value.trim();
                if (idNumber === '') {
                    searchResult.innerHTML = '<div class="alert alert-warning">Please enter an ID number</div>';
                    return;
                }
                
                // AJAX request to search for student
                fetch(`admin_dashboard.php?search=${idNumber}`)
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
                            
                            // Show the modal automatically
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
                        
                        // Reload the page to refresh the data
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
            
            // Initialize Charts
            // Lab Usage Chart
            const labCtx = document.getElementById('labChart').getContext('2d');
            const labData = {
                labels: <?php echo json_encode(array_column($labStats, 'lab')); ?>,
                datasets: [{
                    label: 'Number of Sit-ins',
                    data: <?php echo json_encode(array_column($labStats, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(100, 200, 100, 0.6)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(100, 200, 100, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(labCtx, {
                type: 'bar',
                data: labData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Lab Usage Distribution'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Sit-ins'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Lab Number'
                            }
                        }
                    }
                }
            });
            
            // Purpose Chart
            const purposeCtx = document.getElementById('purposeChart').getContext('2d');
            const purposeData = {
                labels: <?php echo json_encode(array_column($purposeStats, 'purpose')); ?>,
                datasets: [{
                    label: 'Number of Sit-ins',
                    data: <?php echo json_encode(array_column($purposeStats, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(99, 255, 132, 0.6)',
                        'rgba(235, 54, 162, 0.6)',
                        'rgba(86, 255, 206, 0.6)',
                        'rgba(192, 75, 192, 0.6)',
                        'rgba(102, 153, 255, 0.6)',
                        'rgba(159, 255, 64, 0.6)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(purposeCtx, {
                type: 'pie',
                data: purposeData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: true,
                            text: 'Purpose Distribution'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>