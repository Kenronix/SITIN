<?php include 'sidebar.php'; ?>

<div class="main-content">
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
$query = "SELECT * FROM users WHERE id_number = :id_number";
$stmt = $pdo->prepare($query);
$stmt->execute(['id_number' => $user_id]);
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
        $sql = "SELECT profile_picture FROM users WHERE id_number = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $old_picture = $stmt->fetchColumn();
        
        if ($old_picture && file_exists($target_dir . $old_picture)) {
            unlink($target_dir . $old_picture);
        }

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            // Update database with new filename
            $sql = "UPDATE users SET profile_picture = ? WHERE id_number = ?";
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
    $update_query = "UPDATE users SET lastname = :lastname, firstname = :firstname, middlename = :middlename, course = :course, year_level = :year_level, email = :email WHERE id_number = :id_number";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([
        'lastname' => $_POST['lastname'],
        'firstname' => $_POST['firstname'],
        'middlename' => $_POST['middlename'],
        'course' => $_POST['course'],
        'year_level' => $_POST['year_level'],
        'email' => $_POST['email'],
        'id_number' => $user_id
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
                $password_query = "UPDATE users SET password = :password WHERE id_number = :id_number";
                $password_stmt = $pdo->prepare($password_query);
                $password_stmt->execute([
                    'password' => $new_password,
                    'id_number' => $user_id
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
$sql = "SELECT profile_picture FROM users WHERE id_number = ?";
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
    <link rel="stylesheet" href="modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        /* Additional styles for profile page */
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-bottom: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e1e1e1;
        }

        .profile-image {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #f0f0f0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .edit-photo-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .edit-photo-btn:hover {
            background: #5a6fd6;
            transform: scale(1.1);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .student-id {
            color: #666;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .department {
            color: #667eea;
            font-size: 16px;
            font-weight: 500;
        }

        .section {
            margin-bottom: 40px;
        }

        .section h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-item label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .info-item input {
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .info-item input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .info-item input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .edit-btn, .save-btn, .cancel-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .edit-btn {
            background: #667eea;
            color: white;
        }

        .save-btn {
            background: #10b981;
            color: white;
        }

        .cancel-btn {
            background: #ef4444;
            color: white;
        }

        .edit-btn:hover, .save-btn:hover, .cancel-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success-message {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-image {
                margin: 0 auto;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 10px;
            }

            .profile-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-card">
            <?php if (isset($error_msg) && !empty($error_msg)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_msg) && !empty($success_msg)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-image">
                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" alt="Profile Photo">
                    <form id="profile-picture-form" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="file" name="profile_picture" id="profile-picture-input" accept="image/*">
                    </form>
                    <button class="edit-photo-btn" onclick="document.getElementById('profile-picture-input').click()">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['lastname']); ?></h1>
                    <p class="student-id">Student ID: <?= htmlspecialchars($user['id_number']); ?></p>
                    <p class="department">Department of Computer Studies</p>
                </div>
            </div>

            <div class="profile-content">
                <!-- Personal Information Form -->
                <form method="POST">
                    <div class="section">
                        <h2><i class="fas fa-user"></i> Personal Information</h2>
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
                        <button type="button" class="edit-btn" onclick="enableEditing()">
                            <i class="fas fa-edit"></i> Edit Information
                        </button>
                        <button type="submit" id="save-btn" name="save_changes" class="save-btn" style="display: none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" id="cancel-btn" class="cancel-btn" style="display: none;" onclick="cancelEditing()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>

                <!-- Password Change Form -->
                <form method="POST">
                    <div class="section">
                        <h2><i class="fas fa-lock"></i> Password Settings</h2>
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
                        <button type="button" id="change-password-btn" class="edit-btn" onclick="enablePasswordFields()">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                        <button type="submit" id="save-password-btn" name="change_password" class="save-btn" style="display: none;">
                            <i class="fas fa-save"></i> Save Password
                        </button>
                        <button type="button" id="cancel-password-btn" class="cancel-btn" style="display: none;" onclick="cancelPasswordChange()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
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

    function enableEditing() {
        const inputs = document.querySelectorAll('input[name="firstname"], input[name="middlename"], input[name="lastname"], input[name="course"], input[name="year_level"], input[name="email"]');
        inputs.forEach(input => input.disabled = false);
        document.querySelector('.edit-btn').style.display = 'none';
        document.getElementById('save-btn').style.display = 'inline-block';
        document.getElementById('cancel-btn').style.display = 'inline-block';
    }

    function cancelEditing() {
        const inputs = document.querySelectorAll('input[name="firstname"], input[name="middlename"], input[name="lastname"], input[name="course"], input[name="year_level"], input[name="email"]');
        inputs.forEach(input => input.disabled = true);
        document.querySelector('.edit-btn').style.display = 'inline-block';
        document.getElementById('save-btn').style.display = 'none';
        document.getElementById('cancel-btn').style.display = 'none';
    }

    function enablePasswordFields() {
        const passwordInputs = document.querySelectorAll('.password-input');
        passwordInputs.forEach(input => input.disabled = false);
        document.getElementById('change-password-btn').style.display = 'none';
        document.getElementById('save-password-btn').style.display = 'inline-block';
        document.getElementById('cancel-password-btn').style.display = 'inline-block';
    }

    function cancelPasswordChange() {
        const passwordInputs = document.querySelectorAll('.password-input');
        passwordInputs.forEach(input => {
            input.disabled = true;
            input.value = '';
        });
        document.getElementById('change-password-btn').style.display = 'inline-block';
        document.getElementById('save-password-btn').style.display = 'none';
        document.getElementById('cancel-password-btn').style.display = 'none';
    }
    </script>
</body>
</html>
</div>
