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
    
    try {
        // Create notification for reward point
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at, is_read) VALUES (?, ?, 'reward', NOW(), FALSE)");
        $notif_stmt->execute([$student_id, "You received a reward point! You now have " . $new_points . " points."]);
    } catch (PDOException $e) {
        error_log("Error creating reward notification: " . $e->getMessage());
        throw new Exception("Failed to create reward notification: " . $e->getMessage());
    }
    
    // Process session grant if applicable
    $session_granted = false;
    if ($new_points % 3 == 0) {
        try {
            // Add 1 session to the student's remaining_sessions
            $session_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions + 1 WHERE id_number = ?");
            $session_stmt->execute([$student_id]);
            $session_granted = true;
            
            // Create notification for bonus session
            $bonus_notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at, is_read) VALUES (?, ?, 'bonus_session', NOW(), FALSE)");
            $bonus_notif_stmt->execute([$student_id, "Congratulations! You earned a bonus session for reaching " . $new_points . " reward points."]);
        } catch (PDOException $e) {
            error_log("Error processing bonus session: " . $e->getMessage());
            throw new Exception("Failed to process bonus session: " . $e->getMessage());
        }
    }
    
    try {
        // Now log the student out automatically
        // 1. Update sit_in record with time_out
        $update_sitin_stmt = $pdo->prepare("UPDATE sit_ins SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
        $update_sitin_stmt->execute([$sit_in_id]);
        
        // 1.5. Set the PC as available again in lab_pcs
        $pc_stmt = $pdo->prepare("SELECT lab, pc_number FROM sit_ins WHERE id = ?");
        $pc_stmt->execute([$sit_in_id]);
        $pc_info = $pc_stmt->fetch(PDO::FETCH_ASSOC);
        if ($pc_info && !empty($pc_info['lab']) && !empty($pc_info['pc_number'])) {
            $set_pc_stmt = $pdo->prepare("UPDATE lab_pcs SET is_available = 1 WHERE lab = ? AND pc_number = ?");
            $set_pc_stmt->execute([$pc_info['lab'], $pc_info['pc_number']]);
        }
        
        // 2. Reduce remaining_sessions by 1 (just like a regular logout)
        $reduce_sessions_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0");
        $reduce_sessions_stmt->execute([$student_id]);
    } catch (PDOException $e) {
        error_log("Error logging out student: " . $e->getMessage());
        throw new Exception("Failed to log out student: " . $e->getMessage());
    }
    
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
    
    // Log the error
    error_log("Error in process_reward.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage(),
        'error_details' => $e->getMessage() // Include error details for debugging
    ]);
}
?>