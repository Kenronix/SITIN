<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["profile_picture"])) {
    $target_dir = "uploads/";
    $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $new_filename = "profile_" . $user_id . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    $upload_ok = 1;
    $error_msg = "";

    // Check if image file is actual image or fake image
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check === false) {
        $error_msg = "File is not an image.";
        $upload_ok = 0;
    }

    // Check file size (limit to 5MB)
    if ($_FILES["profile_picture"]["size"] > 5000000) {
        $error_msg = "Sorry, your file is too large. Maximum size is 5MB.";
        $upload_ok = 0;
    }

    // Allow certain file formats
    $allowed_types = array("jpg", "jpeg", "png", "gif");
    if (!in_array($file_extension, $allowed_types)) {
        $error_msg = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $upload_ok = 0;
    }

    if ($upload_ok == 1) {
        // Remove old profile picture if exists
        $sql = "SELECT profile_picture FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $old_picture = $stmt->fetchColumn();
        
        if ($old_picture && file_exists($target_dir . $old_picture)) {
            unlink($target_dir . $old_picture);
        }

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            // Update database with new filename
            $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_filename, $user_id]);
            
            $success_msg = "Profile picture has been updated successfully.";
            // Refresh the page to show new image
            header("Location: profile.php");
            exit();
        } else {
            $error_msg = "Sorry, there was an error uploading your file.";
        }
    }
}

// Handle profile information update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_changes'])) {
    $update_query = "UPDATE users SET lastname = :lastname, firstname = :firstname, middlename = :middlename, course = :course, year_level = :year_level, email = :email WHERE id = :id";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([
        'lastname' => $_POST['lastname'],
        'firstname' => $_POST['firstname'],
        'middlename' => $_POST['middlename'],
        'course' => $_POST['course'],
        'year_level' => $_POST['year_level'],
        'email' => $_POST['email'],
        'id' => $user_id
    ]);
    header("Location: profile.php");
    exit();
}



// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $error_msg = "";
    $success_msg = "";

    if ($current_password === $user['password']) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $password_query = "UPDATE users SET password = :password WHERE id = :id";
                $password_stmt = $pdo->prepare($password_query);
                $password_stmt->execute([
                    'password' => $new_password,
                    'id' => $user_id
                ]);
                $success_msg = "Password successfully updated!";
            } else {
                $error_msg = "New password must be at least 6 characters long.";
            }
        } else {
            $error_msg = "New password and confirmation do not match.";
        }
    } else {
        $error_msg = "Current password is incorrect.";
    }
}

// Get the current profile picture
$sql = "SELECT profile_picture FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$profile_picture = $stmt->fetchColumn();
$profile_picture_url = $profile_picture ? "uploads/" . $profile_picture : "profile.jpg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-image {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .edit-photo-btn {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .edit-photo-btn:hover {
            background-color: rgba(0, 0, 0, 0.9);
        }

        .error-message {
            color: #dc3545;
            margin: 10px 0;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }

        .success-message {
            color: #28a745;
            margin: 10px 0;
            padding: 10px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }
    </style>
    <script>
        function enableEditing() {
            document.querySelectorAll('.info-grid input').forEach(input => {
                if (!input.classList.contains('password-input')) {
                    input.removeAttribute('disabled');
                }
            });
            document.getElementById('save-btn').style.display = 'block';
            document.getElementById('cancel-btn').style.display = 'block';
        }
        
        function cancelEditing() {
            document.querySelectorAll('.info-grid input').forEach(input => {
                if (!input.classList.contains('password-input')) {
                    input.setAttribute('disabled', true);
                    input.value = input.defaultValue;
                }
            });
            document.getElementById('save-btn').style.display = 'none';
            document.getElementById('cancel-btn').style.display = 'none';
        }

        function enablePasswordFields() {
            document.querySelectorAll('.password-input').forEach(input => {
                input.removeAttribute('disabled');
            });
            document.getElementById('save-password-btn').style.display = 'block';
            document.getElementById('cancel-password-btn').style.display = 'block';
            document.getElementById('change-password-btn').style.display = 'none';
        }

        function cancelPasswordChange() {
            document.querySelectorAll('.password-input').forEach(input => {
                input.setAttribute('disabled', true);
                input.value = '';
            });
            document.getElementById('save-password-btn').style.display = 'none';
            document.getElementById('cancel-password-btn').style.display = 'none';
            document.getElementById('change-password-btn').style.display = 'block';
        }
    </script>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php">Home</a>
            <a href="#">History</a>
            <a href="reservation.php">Reservation</a>
            <a href="#" class="active">Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="profile-card">
            <?php if (isset($error_msg) && !empty($error_msg)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if (isset($success_msg) && !empty($success_msg)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <div class="profile-header">
                <div class="profile-image">
                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" alt="Profile Photo" class="logo">
                    <form id="profile-picture-form" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="file" name="profile_picture" id="profile-picture-input" accept="image/*">
                    </form>
                    <button class="edit-photo-btn" onclick="document.getElementById('profile-picture-input').click()">Edit Photo</button>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['lastname']); ?></h1>
                    <p class="student-id"><b>Student ID: <?= htmlspecialchars($user['id_number']); ?></b></p>
                    <p class="department">Department of Computer Studies</p><br><hr><br>
                </div>
            </div>
            
            
            
            <div class="profile-content">
                <!-- Personal Information Form -->
                <form method="POST">
                    <div class="section">
                        <h2>Personal Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>First Name</label>
                                <input type="text" name="firstname" value="<?= htmlspecialchars($user['firstname']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Middle Name</label>
                                <input type="text" name="middlename" value="<?= htmlspecialchars($user['middlename']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Last Name</label>
                                <input type="text" name="lastname" value="<?= htmlspecialchars($user['lastname']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Course</label>
                                <input type="text" name="course" value="<?= htmlspecialchars($user['course']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Year Level</label>
                                <input type="text" name="year_level" value="<?= htmlspecialchars($user['year_level']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            <div class="info-item">
                                <label>Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                        </div>
                        <button type="button" class="edit-btn" onclick="enableEditing()">Edit Information</button>
                        <button type="submit" id="save-btn" name="save_changes" class="save-btn" style="display: none;">Save Changes</button>
                        <button type="button" id="cancel-btn" class="cancel-btn" style="display: none;" onclick="cancelEditing()">Cancel</button>
                    </div>
                </form><br>
                <hr><br>

                <!-- Password Change Form -->
                <form method="POST">
                    <div class="section">
                        <h2>Password Settings</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="password-input" disabled>
                            </div>
                            <div class="info-item">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="password-input" disabled>
                            </div>
                            <div class="info-item">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="password-input" disabled>
                            </div>
                        </div>
                        <button type="button" id="change-password-btn" class="edit-btn" onclick="enablePasswordFields()">Change Password</button>
                        <button type="submit" id="save-password-btn" name="change_password" class="save-btn" style="display: none;">Save Password</button>
                        <button type="button" id="cancel-password-btn" class="cancel-btn" style="display: none;" onclick="cancelPasswordChange()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('profile-picture-input').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            if (confirm('Do you want to upload this new profile picture?')) {
                document.getElementById('profile-picture-form').submit();
            }
        }
    });
    </script>
</body>
</html>