<?php
// sidebar.php
// Check if session is already started


require_once 'conn.php';

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$user = null;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get current page filename for highlighting active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="logo.jpg" alt="Logo" class="logo">
        <h2>SITIN MONITORING SYSTEM</h2>
    </div>

    <?php if ($user): ?>
    <div class="user-profile">
        <div class="profile-image">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname'] . '+' . $user['lastname']); ?>&background=4361ee&color=fff" alt="Profile">
            <?php endif; ?>
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
            <p class="user-role">Student</p>
        </div>
    </div>
    <?php endif; ?> 

    <nav class="nav-menu">
        <a href="index.php" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="reservation.php" class="nav-link <?php echo $current_page === 'reservation.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-plus"></i>
            <span>Make Reservation</span>
        </a>
        
        
        <a href="history.php" class="nav-link <?php echo $current_page === 'history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>History</span>
        </a>
        
        <a href="profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
/* Additional Sidebar Styles */
.sidebar {
    width: 280px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--dark-bg);
    padding: 20px;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
    z-index: 1000;
}

.sidebar-header {
    padding: 20px 0;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header .logo {
    width: 60px;
    height: 60px;
    margin-bottom: 10px;
}

.sidebar-header h2 {
    color: white;
    font-size: 1.5rem;
    margin: 0;
}

.user-profile {
    padding: 20px 0;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.profile-image {
    width: 80px;
    height: 80px;
    margin: 0 auto 15px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
}

.profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info h3 {
    color: white;
    font-size: 1rem;
    margin: 0 0 5px;
}

.user-role {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin: 0;
}

.nav-menu {
    flex: 1;
    margin-top: 20px;
    overflow-y: auto;
    overflow-x: hidden;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: var(--radius-md);
    transition: var(--transition);
    margin-bottom: var(--spacing-xs);
    font-size: 1rem;
    font-weight: 500;
    gap: 12px;
    min-height: 48px;
}

.nav-link i {
    width: 20px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-link.active {
    background: var(--primary-color);
    color: #fff;
}

.nav-link.active i {
    color: #fff;
}

.nav-link:not(.active):hover {
    background: rgba(37, 99, 235, 0.08);
    color: var(--primary-color);
}

.nav-link:not(.active):hover i {
    color: var(--primary-color);
}

.sidebar-footer {
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.logout-link {
    color: #dc3545 !important;
}

.logout-link:hover {
    background: #dc3545 !important;
    color: white !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
        padding: 10px;
    }

    .sidebar-header h2,
    .user-info,
    .nav-link span {
        display: none;
    }

    .profile-image {
        width: 40px;
        height: 40px;
        margin-bottom: 10px;
    }

    .nav-link {
        justify-content: center;
        padding: 12px;
    }

    .nav-link i {
        margin: 0;
        font-size: 1.2rem;
    }

    .main-content {
        margin-left: 70px;
    }
}
</style>
