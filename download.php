<?php
session_start();
include 'conn.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $resource_id = $_GET['id'];
        
        // Get file information from database
        $stmt = $pdo->prepare("SELECT * FROM lab_resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resource) {
            throw new Exception("Resource not found");
        }
        
        $file_path = $resource['file_path'];
        $file_name = $resource['resource_name'] . '.' . $resource['file_type'];
        
        // Check if file exists
        if (!file_exists($file_path)) {
            throw new Exception("File not found on server");
        }
        
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($file_path);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: resources.php');
        exit;
    }
} else {
    header('Location: resources.php');
    exit;
} 