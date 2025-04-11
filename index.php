<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$remaining_sessions = $user['remaining_sessions'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
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
    <link rel="stylesheet" href="overallstyle.css">
    <link rel="stylesheet" href="modal.css">
    <title>Dashboard</title>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="session-info">Remaining Session: <?php echo $remaining_sessions; ?></div>
            <div class="section">
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
            <div class="section">
                <h2>Sit-In Rules</h2>
                <div class="box">Lorem ipsum dolor sit amet consectetur. Netus lectus massa scelerisque nibh sit. Pulvinar sapien nullam consectetur nec purus. Praesent eu viverra amet blandit eu tortor orci pulvinar. Sollicitudin morbi in viverra mauris nulla.</div>
            </div>
            <div class="section">
                <h2>Laboratory Rules and Regulation</h2>
                <div class="box"><b>To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</b><br><br>

                    1. Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.<br>

                    2. Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.<br>

                    3. Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.<br>

                    4. Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.<br>

                    5. Deleting computer files and changing the set-up of the computer is a major offense.<br>

                    6. Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".<br>

                    7. Observe proper decorum while inside the laboratory.<br>

                        <li>Do not use the lab for personal purposes.</li>
                        <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                        <li>Follow the seating arrangement of your instructor.</li>
                        <li>At the end of class, all software programs must be closed.</li>
                        <li>Return all chairs to their proper places after using.</li>
                        
                    8. Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.<br>

                    9. Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.<br>

                    10. Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.<br>

                    11. For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.<br>

                    12. Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.<br><br>


                    <b>DISCIPLINARY ACTION</b><br><br>

                    <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                    <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                </div>
            </div>
        </div>
    </div>
</body>
</html>