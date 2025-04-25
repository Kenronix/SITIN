<?php
session_start();
include 'conn.php'; // Database connection

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if student_id and sit_in_id are provided
if (!isset($_POST['student_id']) || empty($_POST['student_id']) || !isset($_POST['sit_in_id']) || empty($_POST['sit_in_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Student ID and Sit-in ID are required']);
    exit;
}

$student_id = $_POST['student_id'];
$sit_in_id = $_POST['sit_in_id'];
$admin_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if student exists in the leaderboard table
    $check_stmt = $pdo->prepare("SELECT points FROM leaderboard WHERE student_id = ?");
    $check_stmt->execute([$student_id]);
    $student_points = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student_points) {
        // Student exists in leaderboard, update points
        $current_points = $student_points['points'];
        $new_points = $current_points + 1;
        
        $update_stmt = $pdo->prepare("UPDATE leaderboard SET points = ? WHERE student_id = ?");
        $update_stmt->execute([$new_points, $student_id]);
    } else {
        // Student doesn't exist in leaderboard, insert new record
        $insert_stmt = $pdo->prepare("INSERT INTO leaderboard (student_id, points) VALUES (?, 1)");
        $insert_stmt->execute([$student_id]);
        $new_points = 1;
    }
    
    // Record the reward action in logs
    $log_stmt = $pdo->prepare("INSERT INTO reward_logs (student_id, admin_id, points_given, timestamp) VALUES (?, ?, 1, NOW())");
    $log_stmt->execute([$student_id, $admin_id]);
    
    // Process session grant if applicable
    $session_granted = false;
    if ($new_points % 3 == 0) {
        // Add 1 session to the student's remaining_sessions
        $session_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions + 1 WHERE id_number = ?");
        $session_stmt->execute([$student_id]);
        $session_granted = true;
    }
    
    // Now log the student out automatically
    // 1. Update sit_in record with time_out
    $update_sitin_stmt = $pdo->prepare("UPDATE sit_ins SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
    $update_sitin_stmt->execute([$sit_in_id]);
    
    // 2. Reduce remaining_sessions by 1 (just like a regular logout)
    $reduce_sessions_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0");
    $reduce_sessions_stmt->execute([$student_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success message
    header('Content-Type: application/json');
    
    if ($session_granted) {
        echo json_encode([
            'success' => true, 
            'message' => 'Reward point added and student logged out successfully! The student now has ' . $new_points . ' points. They earned a bonus session for reaching ' . $new_points . ' points, but one session was used for this visit.',
            'auto_logout' => true
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'Reward point added and student logged out successfully! The student now has ' . $new_points . ' points. One session was used for this visit.',
            'auto_logout' => true
        ]);
    }
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>