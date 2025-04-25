<?php
session_start();
include 'conn.php'; // Database connection

if(isset($_POST['publish'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $imagePath = NULL; // Default NULL if no image is uploaded

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/"; // Folder where images will be stored
        $fileName = basename($_FILES['image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif']; // Allowed file types
        
        if (in_array($fileExt, $allowedExts)) {
            $newFileName = uniqid() . "." . $fileExt; // Rename file to avoid conflicts
            $targetFilePath = $targetDir . $newFileName;

            // Create directory if it doesn't exist
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $imagePath = $targetFilePath; // Save image path
            } else {
                echo "Error uploading the image.";
                exit();
            }
        } else {
            echo "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            exit();
        }
    }

    // Insert into database using PDO
    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, image, date_published) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$title, $content, $imagePath]);

        header("Location: announcement.php?success=1");
        exit();
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY date_published DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement</title>
    <link rel="stylesheet" href="admins.css">
    <link rel="stylesheet" href="announcement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <li><a href="announcement.php" class="active">Announcement</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="labsched.php">Lab Schedule</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="content">
        <h1>Announcements</h1>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                Announcement published successfully!
            </div>
        <?php endif; ?>
        
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchAnnouncements" placeholder="Search announcements...">
            </div>
            
            <!-- Button to open the modal -->
            <button class="create-btn" id="openModalBtn">
                <i class="fas fa-plus"></i> Create New Announcement
            </button>
        </div>
        
        <!-- The Modal -->
        <div id="announcementModal" class="modal">
            <!-- Modal content -->
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Create an Announcement</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" placeholder="Enter announcement title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" placeholder="Enter announcement details..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Upload Image (Optional)</label>
                        <div class="file-upload">
                            <label class="file-upload-label" for="image">
                                <i class="fas fa-cloud-upload-alt"></i> Choose Image
                            </label>
                            <input type="file" id="image" name="image" accept="image/*">
                            <div class="file-name" id="file-name-display">No file chosen</div>
                        </div>
                    </div>

                    <button type="submit" name="publish">
                        <i class="fas fa-paper-plane"></i> Publish Announcement
                    </button>
                </form>
            </div>
        </div>
        
        <div class="announcements">
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <p>No announcements have been posted yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $row): ?>
                    <div class="announcement">
                        <p class="title"><?php echo htmlspecialchars($row['title']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>
                        <?php if (!empty($row['image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['image']); ?>" class="announcement-image" alt="Announcement image">
                        <?php endif; ?>
                        <p class="date">
                            <i class="far fa-calendar-alt"></i> 
                            Posted on: <?php echo date('F j, Y \a\t g:i A', strtotime($row['date_published'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            var modal = document.getElementById("announcementModal");
            var btn = document.getElementById("openModalBtn");
            var span = document.getElementsByClassName("close")[0];
            
            btn.onclick = function() {
                modal.style.display = "block";
            }
            
            span.onclick = function() {
                modal.style.display = "none";
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
            
            // File upload display
            document.getElementById('image').addEventListener('change', function() {
                var fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                document.getElementById('file-name-display').textContent = fileName;
            });
            
            // Search functionality
            document.getElementById('searchAnnouncements').addEventListener('keyup', function() {
                var searchTerm = this.value.toLowerCase();
                var announcements = document.querySelectorAll('.announcement');
                
                announcements.forEach(function(announcement) {
                    var title = announcement.querySelector('.title').textContent.toLowerCase();
                    var content = announcement.querySelectorAll('p')[1].textContent.toLowerCase();
                    
                    if (title.includes(searchTerm) || content.includes(searchTerm)) {
                        announcement.style.display = 'block';
                    } else {
                        announcement.style.display = 'none';
                    }
                });
            });
            
            // Auto-dismiss success message
            setTimeout(function() {
                var successMsg = document.querySelector('.success-message');
                if (successMsg) {
                    successMsg.style.display = 'none';
                }
            }, 8000);
        });
    </script>
</body>
</html>