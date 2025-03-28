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
            <li><a href="survey.php">Satisfaction Survey</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
    <h1>Create an Announcement</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="image">Upload Image</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>

            <button type="submit" name="publish">Create</button>
        </form>
    </div>

    <div class="announcements">
        <h2>Announcements</h2>
        <?php if (isset($_GET['success'])): ?>
            <p class="success-message">Announcement published successfully!</p>
        <?php endif; ?>
        
        <div class="container">
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
        </div>
    </div>
</body>
</html>
