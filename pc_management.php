<?php
session_start();
include 'conn.php';

// List of labs
$labs = [524, 526, 528, 530, 542, 544, 517];

// Handle AJAX update
if (isset($_POST['lab'], $_POST['pc_number'], $_POST['is_available'])) {
    $lab = $_POST['lab'];
    $pc_number = $_POST['pc_number'];
    $is_available = $_POST['is_available'] ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE lab_pcs SET is_available = ? WHERE lab = ? AND pc_number = ?");
    $stmt->execute([$is_available, $lab, $pc_number]);
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all PC statuses
$pc_status = [];
$stmt = $pdo->query("SELECT lab, pc_number, is_available FROM lab_pcs");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pc_status[$row['lab']][$row['pc_number']] = $row['is_available'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Management | Admin Panel</title>
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
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php" class="active"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-desktop"></i> PC Management</h1>
            <p>Toggle the availability of each PC in every lab.</p>
        </div>
        <?php foreach ($labs as $lab): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-building"></i> Lab <?php echo $lab; ?></h3>
            </div>
            <div class="card-body">
                <div class="pc-grid">
                    <?php for ($i = 1; $i <= 50; $i++): 
                        $available = isset($pc_status[$lab][$i]) ? $pc_status[$lab][$i] : 1;
                    ?>
                    <div class="pc-item" data-lab="<?php echo $lab; ?>" data-pc="<?php echo $i; ?>">
                        <div class="pc-label">PC <?php echo $i; ?></div>
                        <button class="pc-btn <?php echo $available ? 'available' : 'unavailable'; ?>" onclick="togglePC(this, <?php echo $lab; ?>, <?php echo $i; ?>, <?php echo $available ? 1 : 0; ?>)">
                            <span class="pc-text">
                                <i class="fas fa-<?php echo $available ? 'check-circle' : 'times-circle'; ?>"></i>
                            </span>
                        </button>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
    function togglePC(btn, lab, pc, current) {
        const newStatus = current ? 0 : 1;
        fetch('pc_management.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lab=${lab}&pc_number=${pc}&is_available=${newStatus}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.classList.toggle('available', !!newStatus);
                btn.classList.toggle('unavailable', !newStatus);
                btn.querySelector('i').className = 'fas fa-' + (newStatus ? 'check-circle' : 'times-circle');
                btn.setAttribute('onclick', `togglePC(this, ${lab}, ${pc}, ${newStatus})`);
            }
        });
    }
    </script>
    <style>
                
        /* PC Management Styles */
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .pc-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .pc-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.15rem;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .pc-btn {
            width: 58px;
            height: 38px;
            border: none;
            border-radius: 0.7rem;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            cursor: pointer;
            transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.15s;
            box-shadow: 0 2px 8px rgba(37,99,235,0.08), 0 1.5px 4px rgba(0,0,0,0.07);
            outline: none;
            background: #f3f4f6;
            color: #222;
            border: 2px solid #e0e7ef;
            position: relative;
            overflow: hidden;
        }

        .pc-btn::before {
            content: '\f108'; /* FontAwesome desktop icon */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 50%;
            top: 50%;
            font-size: 2.2rem;
            color: #dbeafe;
            opacity: 0.18;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
        }

        .pc-btn .pc-text {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .pc-btn.available {
            background: #e6fbe8;
            color: #1e7e34;
            border-color: #b6e7c9;
        }

        .pc-btn.unavailable {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .pc-btn.available:hover {
            background: #c6f6d5;
            color: #166534;
            border-color: #86efac;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 4px 16px rgba(34,197,94,0.13);
        }

        .pc-btn.unavailable:hover {
            background: #fecaca;
            color: #7f1d1d;
            border-color: #f87171;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 4px 16px rgba(239,68,68,0.13);
        }

        .pc-btn i {
            margin-left: 0.2rem;
            font-size: 1.1em;
            z-index: 1;
        }

        @media (max-width: 600px) {
            .pc-grid {
                grid-template-columns: repeat(auto-fit, minmax(40px, 1fr));
            }
            .pc-btn {
                width: 40px;
                height: 32px;
                font-size: 0.8rem;
                padding: 0.2rem;
            }
            .pc-label {
                font-size: 0.65rem;
            }
        } 
    </style>
</body>
</html> 