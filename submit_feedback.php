<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $sit_in_id = $_POST['sit_in_id'];
    $id_number = $_POST['id_number'];
    $lab = $_POST['lab'];
    $date = $_POST['date'];
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    
    try {
        // Validate data
        if (empty($sit_in_id) || empty($id_number) || empty($lab) || empty($date) || empty($comments)) {
            throw new Exception("Missing required fields");
        }
        
        // Insert feedback into database
        $stmt = $pdo->prepare("INSERT INTO feedback (sit_in_id, id_number, lab, date, comments) 
                             VALUES (?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([$sit_in_id, $id_number, $lab, $date, $comments]);
        
        if ($result) {
            // Success
            $_SESSION['success_message'] = "Thank you for your feedback!";
            header("Location: history.php");
            exit();
        } else {
            throw new Exception("Failed to submit feedback");
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: history.php");
        exit();
    }
} else {
    // Not a POST request
    header("Location: history.php");
    exit();
}
?>