<?php
require_once 'conn.php';

try {
    // Create lab_schedule table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lab VARCHAR(10) NOT NULL,
        day VARCHAR(10) NOT NULL,
        time_slot VARCHAR(20) NOT NULL,
        status ENUM('available', 'unavailable') DEFAULT 'available',
        UNIQUE KEY unique_schedule (lab, day, time_slot)
    )");

    // Get all labs
    $labs = ['524', '526', '528', '530', '542', '544', '517'];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $time_slots = [
        '7:30-8:30',
        '8:30-9:30',
        '9:30-10:30',
        '10:30-11:30',
        '11:30-12:30',
        '12:30-1:30',
        '1:30-2:30',
        '2:30-3:30',
        '3:30-4:30',
        '4:30-5:30'
    ];

    // Prepare insert statement
    $stmt = $pdo->prepare("INSERT IGNORE INTO lab_schedule (lab, day, time_slot, status) VALUES (?, ?, ?, 'available')");

    // Insert default available times for each lab
    foreach ($labs as $lab) {
        foreach ($days as $day) {
            foreach ($time_slots as $time_slot) {
                $stmt->execute([$lab, $day, $time_slot]);
            }
        }
    }

    echo "Lab schedule table created and populated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 