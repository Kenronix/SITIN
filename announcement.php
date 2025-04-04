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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admins.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* General styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f8fa;
        }
        
        .content {
            padding: 20px;
        }
        
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            border: none;
            width: 60%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: modalFade 0.4s;
        }
        
        @keyframes modalFade {
            from {opacity: 0; transform: translateY(-30px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close:hover,
        .close:focus {
            color: #333;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        .form-group input[type="file"] {
            padding: 10px 0;
        }
        
        /* File upload styling */
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-label {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .file-upload-label:hover {
            background-color: #2980b9;
        }
        
        .file-name {
            margin-top: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        /* Button Styles */
        .create-btn, button[type="submit"] {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .create-btn i {
            margin-right: 8px;
        }
        
        .create-btn:hover, button[type="submit"]:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        button[type="submit"] {
            width: 100%;
            margin-top: 10px;
        }
        
        /* Success Message */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            animation: fadeOut 5s forwards;
            animation-delay: 3s;
        }
        
        .success-message i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        @keyframes fadeOut {
            from {opacity: 1;}
            to {opacity: 0; height: 0; padding: 0; margin: 0; border: 0;}
        }
        
        /* Announcement Cards */
        .announcements {
            margin-top: 30px;
        }
        
        .announcement {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }
        
        .announcement:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .announcement .title {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .announcement p {
            color: #555;
            line-height: 1.6;
        }
        
        .announcement-image {
            max-width: 100%;
            height: auto;
            margin: 15px 0;
            border-radius: 6px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .announcement .date {
            color: #7f8c8d;
            font-size: 14px;
            font-style: italic;
            text-align: right;
            margin-top: 15px;
            margin-bottom: 0;
        }
        
        /* Search and filter */
        .controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 300px;
            margin-right: 15px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .search-box {
                max-width: 100%;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .create-btn {
                width: 100%;
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
            <li><a href="announcement.php" class="active">Announcement</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="survey.php">Satisfaction Survey</a></li>
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