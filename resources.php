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
    <title>Lab Resources</title>
    <link rel="stylesheet" href="admins.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .resources-container {
            margin-top: 20px;
        }
        
        .upload-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .resource-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .resource-table th, .resource-table td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }
        
        .resource-table th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
        }
        
        .resource-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .resource-table tr:hover {
            background-color: #e9ecef;
        }
        
        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
        }
        
        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        
        .btn-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        .btn-success {
            color: #fff;
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .file-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-heading h3 {
            margin: 0;
            font-size: 22px;
            color: #333;
        }
        
        .resource-info {
            display: flex;
            align-items: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .file-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        /* File type colors */
        .file-type-pdf { background-color: #f40f02; color: white; }
        .file-type-doc, .file-type-docx { background-color: #2b579a; color: white; }
        .file-type-xls, .file-type-xlsx { background-color: #217346; color: white; }
        .file-type-ppt, .file-type-pptx { background-color: #d24726; color: white; }
        .file-type-txt { background-color: #5c5c5c; color: white; }
        .file-type-zip, .file-type-rar { background-color: #ffc107; color: #212529; }
        .file-type-jpg, .file-type-jpeg, .file-type-png { background-color: #6610f2; color: white; }
        
        .actions-column {
            white-space: nowrap;
            width: 150px;
        }
        
        @media (max-width: 768px) {
            .resource-table {
                display: block;
                overflow-x: auto;
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
            <li><a href="announcement.php">Announcement</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="labsched.php">Lab Schedule</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="content">
        <h2>Lab Resources Management</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="section-heading">
            <h3>Upload New Resource</h3>
        </div>
        
        <div class="upload-form">
            <form method="POST" action="resources.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="resource_name">Resource Name:</label>
                    <input type="text" id="resource_name" name="resource_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="resource_file">Select File:</label>
                    <input type="file" id="resource_file" name="resource_file" class="form-control" required>
                    <small style="display: block; margin-top: 5px; color: #6c757d;">
                        Allowed file types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, JPEG, PNG, ZIP, RAR (Max size: 10MB)
                    </small>
                </div>
                
                <button type="submit" name="upload_resource" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Resource
                </button>
            </form>
        </div>
        
        <div class="section-heading">
            <h3>Available Resources</h3>
        </div>
        
        <div class="resources-container">
            <?php if (count($resources) > 0): ?>
                <table class="resource-table">
                    <thead>
                        <tr>
                            <th>Resource Name</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Uploaded On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resource['resource_name']); ?></td>
                                <td>
                                    <div class="resource-info">
                                        <i class="fas <?php echo getFileIcon($resource['file_type']); ?> file-icon"></i>
                                        <?php echo htmlspecialchars($resource['file_name']); ?>
                                        <span class="file-type-badge file-type-<?php echo $resource['file_type']; ?>">
                                            <?php echo strtoupper($resource['file_type']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo formatFileSize($resource['file_size']); ?></td>
                                <td><?php echo date('M d, Y g:i A', strtotime($resource['upload_date'])); ?></td>
                                <td class="actions-column">
                                    <a href="<?php echo $resource['file_path']; ?>" class="btn btn-success" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="resources.php?delete=<?php echo $resource['id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this resource?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Resources Available</h3>
                    <p>There are no resources uploaded yet. Use the form above to upload your first resource.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.querySelector('form');
            const fileInput = document.getElementById('resource_file');
            
            uploadForm.addEventListener('submit', function(e) {
                // Check if file is selected
                if (fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a file to upload');
                    return;
                }
                
                const file = fileInput.files[0];
                
                // Validate file size (10MB max)
                const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File is too large. Maximum size is 10MB.');
                    return;
                }
                
                // Validate file extension
                const fileName = file.name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                const allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                
                if (!allowedExts.includes(fileExt)) {
                    e.preventDefault();
                    alert('Invalid file type. Allowed types: ' + allowedExts.join(', '));
                    return;
                }
            });
        });
    </script>
</body>
</html>