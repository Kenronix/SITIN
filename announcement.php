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
    <title>Announcements | Admin Panel</title>
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
            <li><a href="announcement.php" class="active"><i class="fas fa-bullhorn"></i> Announcement</a></li>
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
            <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
            <button class="btn btn-primary" id="openModalBtn">
                <i class="fas fa-plus"></i> Create New Announcement
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                Announcement published successfully!
            </div>
        <?php endif; ?>

        <div class="search-filter-container">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchAnnouncements" placeholder="Search announcements...">
            </div>
        </div>

        <div class="announcements-container">
            <?php if (empty($announcements)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-bullhorn fa-3x mb-3" style="color: var(--text-secondary);"></i>
                        <h3>No Announcements Yet</h3>
                        <p class="text-secondary">Create your first announcement to get started.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $row): ?>
                    <div class="card announcement-card fade-in">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                            <span class="date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('F j, Y \a\t g:i A', strtotime($row['date_published'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                            </div>
                            <?php if (!empty($row['image'])): ?>
                                <div class="announcement-image mt-4">
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="Announcement image">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- The Modal -->
        <div id="announcementModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-plus"></i> Create New Announcement</h2>
                    <span class="close">&times;</span>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="title">Title</label>
                            <input type="text" id="title" name="title" class="form-control" placeholder="Enter announcement title" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="content">Content</label>
                            <textarea id="content" name="content" class="form-control" rows="5" placeholder="Enter announcement details..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="image">Upload Image (Optional)</label>
                            <div class="file-upload">
                                <label class="btn btn-primary" for="image">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Image
                                </label>
                                <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                                <div class="file-name mt-2" id="file-name-display">No file chosen</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="publish" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Publish Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const modal = document.getElementById("announcementModal");
            const btn = document.getElementById("openModalBtn");
            const span = document.getElementsByClassName("close")[0];
            const cancelBtn = document.querySelector('[data-dismiss="modal"]');
            
            function openModal() {
                modal.style.display = "block";
                document.body.style.overflow = "hidden";
            }
            
            function closeModal() {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            }
            
            btn.onclick = openModal;
            span.onclick = closeModal;
            cancelBtn.onclick = closeModal;
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    closeModal();
                }
            }
            
            // File upload display
            document.getElementById('image').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                document.getElementById('file-name-display').textContent = fileName;
            });
            
            // Search functionality
            document.getElementById('searchAnnouncements').addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const announcements = document.querySelectorAll('.announcement-card');
                
                announcements.forEach(function(announcement) {
                    const title = announcement.querySelector('h3').textContent.toLowerCase();
                    const content = announcement.querySelector('.announcement-content').textContent.toLowerCase();
                    
                    if (title.includes(searchTerm) || content.includes(searchTerm)) {
                        announcement.style.display = 'block';
                    } else {
                        announcement.style.display = 'none';
                    }
                });
            });
            
            // Auto-dismiss success message
            const successMsg = document.querySelector('.alert-success');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    setTimeout(() => successMsg.remove(), 300);
                }, 5000);
            }
        });
    </script>

    <style>
        /* Additional styles specific to announcements */
        .announcement-card {
            transition: transform 0.2s;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
        }
        
        .announcement-content {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        .announcement-image {
            margin-top: 1rem;
            text-align: center;
        }
        
        .announcement-image img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 0.375rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .file-upload {
            border: 2px dashed var(--border-color);
            padding: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            transition: border-color 0.2s;
        }
        
        .file-upload:hover {
            border-color: var(--primary-color);
        }
        
        .file-name {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .alert {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: opacity 0.3s;
        }
        
        .alert i {
            font-size: 1.25rem;
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.4); }
        .modal-content { background: #fff; margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 500px; position: relative; }
        .close { position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; }
        .search-results { margin-top: 1rem; }
        .student-list { list-style: none; padding: 0; }
        .student-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .student-item i { margin-right: 0.5rem; color: var(--primary-color); }
        .student-id { color: #888; font-size: 0.9em; margin-left: 0.5rem; }
        .reserveBtn { margin-left: 1rem; }

        /* Add these styles to the existing style section */
        .student-info {
          display: flex;
          flex-direction: column;
          gap: 0.25rem;
        }

        .student-id {
          color: var(--text-secondary);
          font-size: 0.9em;
        }

        .student-name {
          font-weight: 500;
        }

        .student-sessions {
          color: var(--text-secondary);
          font-size: 0.85em;
        }
    </style>
</body>
</html>