<?php
session_start();
include 'conn.php'; // Database connection

// Check if admin is logged in
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit;
// }

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_resource'])) {
    try {
        $resource_name = $_POST['resource_name'];
        
        // Check if resource name is provided
        if(empty($resource_name)) {
            throw new Exception("Please enter a resource name");
        }
        
        // Check if file was uploaded
        if(!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("Please select a file to upload");
        }
        
        $file = $_FILES['resource_file'];
        
        // Check for upload errors
        if($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error. Error code: " . $file['error']);
        }
        
        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024; // 10MB in bytes
        if($file['size'] > $max_size) {
            throw new Exception("File is too large. Maximum size is 10MB.");
        }
        
        // Get file extension
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        // Allowed file types
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        
        if(!in_array($extension, $allowed_extensions)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = 'uploads/resources/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $new_filename = time() . '_' . uniqid() . '.' . $extension;
        $destination = $upload_dir . $new_filename;
        
        // Move uploaded file to destination
        if(!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to save the uploaded file");
        }
        
        // Save file information to database
        $stmt = $pdo->prepare("INSERT INTO lab_resources (resource_name, file_name, file_path, file_type, file_size, upload_date) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $resource_name,
            $file['name'],
            $destination,
            $extension,
            $file['size']
        ]);
        
        $resource_id = $pdo->lastInsertId();
        
        $success_message = "Resource uploaded successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle delete resource
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $resource_id = $_GET['delete'];
        
        // Get file path before deleting the record
        $stmt = $pdo->prepare("SELECT file_path FROM lab_resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$resource) {
            throw new Exception("Resource not found");
        }
        
        // Delete the file from the filesystem
        if(file_exists($resource['file_path'])) {
            unlink($resource['file_path']);
        }
        
        // Delete the record from the database
        $stmt = $pdo->prepare("DELETE FROM lab_resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        
        $success_message = "Resource deleted successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all resources
$stmt = $pdo->query("SELECT * FROM lab_resources ORDER BY upload_date DESC");
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to get file icon based on file type
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image'
    ];
    
    return isset($icons[$fileType]) ? $icons[$fileType] : 'fa-file';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Resources | Admin Panel</title>
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
            <li><a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a></li>
            <li><a href="labsched.php"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php" class="active"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-book"></i> Lab Resources Management</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-upload"></i> Upload New Resource</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="resources.php" enctype="multipart/form-data" class="upload-form">
                    <div class="form-group">
                        <label for="resource_name" class="form-label">Resource Name</label>
                        <input type="text" id="resource_name" name="resource_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="resource_file" class="form-label">Select File</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="resource_file" name="resource_file" class="form-control" required>
                            <small class="form-text text-muted">Maximum file size: 10MB. Allowed types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, JPEG, PNG, ZIP, RAR</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="upload_resource" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Resource
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Available Resources</h3>
            </div>
            <div class="card-body">
                <?php if (empty($resources)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open fa-3x"></i>
                        <h3>No Resources Found</h3>
                        <p>Upload your first resource to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Resource Name</th>
                                    <th>File Type</th>
                                    <th>Size</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td>
                                            <div class="resource-info">
                                                <i class="fas <?php echo getFileIcon($resource['file_type']); ?>"></i>
                                                <span><?php echo htmlspecialchars($resource['resource_name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo strtoupper($resource['file_type']); ?></td>
                                        <td><?php echo formatFileSize($resource['file_size']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="download.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-info" title="Download">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <a href="resources.php?delete=<?php echo $resource['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this resource?')" title="Delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    /* Upload Form Styles */
    .upload-form {
        max-width: 600px;
        margin: 0 auto;
    }

    .file-upload-wrapper {
        position: relative;
        margin-bottom: 1rem;
    }

    .file-upload-wrapper input[type="file"] {
        padding: 0.5rem;
        border: 2px dashed var(--border-color);
        border-radius: 0.5rem;
        width: 100%;
        cursor: pointer;
    }

    .file-upload-wrapper input[type="file"]:hover {
        border-color: var(--primary-color);
    }

    .form-text {
        display: block;
        margin-top: 0.5rem;
        color: var(--text-secondary);
        font-size: 0.875rem;
    }

    /* Resource Table Styles */
    .resource-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .resource-info i {
        font-size: 1.25rem;
        color: var(--primary-color);
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn-info {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-info:hover {
        background-color: var(--primary-dark);
    }

    .btn-danger {
        background-color: var(--danger-color);
        color: white;
    }

    .btn-danger:hover {
        background-color: var(--danger-dark);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        margin-bottom: 1rem;
        color: var(--border-color);
    }

    .empty-state h3 {
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .upload-form {
            max-width: 100%;
        }

        .table-container {
            overflow-x: auto;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
    </style>
</body>
</html>