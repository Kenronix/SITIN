<?php
// sidebar.php
// Check if session is already started


// Get current page filename for highlighting active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <h3 class="logo">Sit-in Monitoring System</h3>
    <ul class="nav">
        <li><a href="index.php" <?php echo ($current_page == 'index.php') ? 'class="active"' : ''; ?>>Home</a></li>
        <li><a href="profile.php" <?php echo ($current_page == 'profile.php') ? 'class="active"' : ''; ?>>Profile</a></li>
        <li><a href="reservation.php" <?php echo ($current_page == 'reservation.php') ? 'class="active"' : ''; ?>>Reservation</a></li>
        <li><a href="history.php" <?php echo ($current_page == 'history.php') ? 'class="active"' : ''; ?>>History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>